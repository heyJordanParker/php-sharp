//! `mago convert <paths>`: raises each plain PHP file the paths name into PHP#, in place, as `Foo.php` becoming
//! `Foo.sharp`, or reports each construct that keeps it plain PHP.
//!
//! The types come from the project's own analysis, run as `mago analyze` runs it. Before it writes a file, the command
//! analyzes the converted text against the whole project and compiles it in memory, and keeps the file plain PHP when
//! any file gains an error, a warning decision 9 doesn't expect, or a refused compile, the converted one included, with
//! no `ignore` entry or baseline, which hide no language error in a `.sharp` file (spec section 27). The extension hosts
//! load the project's classes, so each file it writes that their compile takes in starts the analysis again. Git is the
//! undo: the command moves each tracked file with `git mv`, so the index holds the rename alone, git's history follows
//! the file through it, and the `.php` file `mago compile` later writes at the old path is never the tracked one.

use std::borrow::Cow;
use std::collections::BTreeMap;
use std::collections::HashMap;
use std::collections::HashSet;
use std::path::Path;
use std::path::PathBuf;
use std::process::ExitCode;

use clap::ColorChoice;
use clap::Parser;
use diffy::PatchFormatter;

use mago_allocator::LocalArena;
use mago_composer::AutoloadPsr4value;
use mago_composer::ComposerPackage;
use mago_converter::Input;
use mago_converter::Refusal;
use mago_converter::convert;
use mago_converter::library::Library;
use mago_database::DatabaseReader;
use mago_database::error::DatabaseError;
use mago_database::error::IOAction;
use mago_database::file::File;
use mago_database::file::FileId;
use mago_database::file::FileType;
use mago_database::loader::mark_sharp_file;
use mago_names::resolver::NameResolver;
use mago_orchestrator::Orchestrator;
use mago_orchestrator::service::incremental_analysis::compile::Compilation;
use mago_prelude::Prelude;
use mago_reporting::IssueCollection;
use mago_reporting::Level;
use mago_server::Server;
use mago_sharp_bridge::unit;
use mago_syntax::cst::Statement;
use mago_syntax::parser::parse_file;

use crate::commands::analyze::analyze_the_whole_workspace_without_paths;
use crate::commands::analyze::decode_prelude;
use crate::commands::analyze::server_settings;
use crate::commands::compile::compile_for_hosts;
use crate::commands::compile::standard_library;
use crate::commands::outcome::CommandOutcome;
use crate::config::Configuration;
use crate::consts::PRELUDE_BYTES;
use crate::error::Error;
use crate::extensions::start_external_analyzer;
use crate::utils::create_orchestrator;
use crate::utils::git;
use crate::utils::should_use_colors;
use crate::utils::write_by_rename;

/// Command that raises plain PHP files into PHP#.
#[derive(Parser, Debug)]
#[command(name = "convert")]
pub struct ConvertCommand {
    /// The plain PHP files, and folders of them, to convert.
    #[arg(required = true)]
    pub paths: Vec<PathBuf>,

    /// Print each conversion as a diff, and change no file.
    #[arg(long, short = 'd')]
    pub dry_run: bool,
}

/// The warnings a conversion adds by design, decision 9 of `docs/architecture/slices/convert.md`: a member the converter
/// wrote `public` to keep PHP's reach that nothing outside its namespace uses, and a plain PHP caller's PHPDoc type
/// that the converted class now declares itself.
const EXPECTED_WARNINGS: [&str; 2] = ["too-wide-member", "redundant-docblock-type"];

/// An issue as a later analysis finds it again: its file, level, code, message and line.
type IssueKey = (Vec<u8>, Level, Option<String>, String, u32);

/// Each issue of an analysis, with how many times it is reported.
type Issues = HashMap<IssueKey, usize>;

/// One analysis of the project with its extension hosts as they are on disk, which the conversions are proven against.
struct Session {
    server: Server,
    issues: Issues,
    /// The files `mago compile` refuses before any conversion of this session.
    refused: HashSet<FileId>,
    library: Library,
    standard_library: unit::Library,
}

/// What the conversions of the command found.
#[derive(Default)]
struct Outcome {
    refused: BTreeMap<Vec<u8>, Vec<String>>,
    converted: Vec<(Vec<u8>, Vec<u8>, Vec<u8>)>,
    /// The warnings decision 9 expects, each as the summary lists it.
    expected: Vec<String>,
}

impl ConvertCommand {
    pub fn execute(self, mut configuration: Configuration, color_choice: ColorChoice) -> Result<CommandOutcome, Error> {
        configuration.extension_hosts = compile_for_hosts(&configuration)?.0;
        let workspace = configuration.source.workspace.clone();
        let root = workspace.canonicalize().map_err(|error| Error::CanonicalizingPath(workspace.clone(), error))?;
        let roots = psr4_roots(&workspace)?;
        let named: Vec<PathBuf> = self.paths.iter().map(|path| relative(&workspace, path)).collect();

        let mut outcome = Outcome::default();
        let mut done: HashSet<Vec<u8>> = HashSet::new();
        // The extension hosts load the project's own classes, so a converted file they load starts a new session, whose
        // hosts see it (spec section 27).
        'sessions: loop {
            let mut orchestrator = create_orchestrator(&configuration, color_choice, false, false, false);
            let Some(mut session) = Session::start(&configuration, &mut orchestrator, &root)? else {
                return Ok(ExitCode::FAILURE.into());
            };
            let mut candidates: Vec<(FileId, Vec<u8>)> = session
                .server
                .database()
                .files()
                .filter(|file| file.file_type == FileType::Host && file.name.ends_with(b".php"))
                .filter(|file| !done.contains(file.name.as_ref()))
                .filter(|file| {
                    let name = Path::new(std::str::from_utf8(&file.name).unwrap_or_default());
                    named.iter().any(|path| name.starts_with(path))
                })
                .map(|file| (file.id, file.name.to_vec()))
                .collect();
            candidates.sort_by(|a, b| a.1.cmp(&b.1));

            for (file_id, name) in candidates {
                done.insert(name.clone());
                let file = session.server.database().get(&file_id)?;
                if let Err(reason) = shape(&file, &roots) {
                    outcome.refused.entry(name).or_default().push(format!("{}: {reason}", display(&file.name)));
                    continue;
                }

                let text = session.convert(&file);
                match text.and_then(|text| session.verify(&workspace, &root, &file, text)) {
                    Ok((text, expected)) => {
                        outcome.expected.extend(expected);
                        outcome.converted.push((file.name.to_vec(), file.contents.to_vec(), text.clone()));
                        if self.dry_run {
                            continue;
                        }
                        write(&workspace, &file.name, &text)?;
                        if compile_for_hosts(&configuration)?.1 {
                            let written = display(&sharp_name(&file.name));
                            tracing::info!("The extension hosts load {written}, so the analysis starts again.");
                            continue 'sessions;
                        }
                    }
                    Err(reasons) => outcome.refused.entry(name).or_default().extend(reasons),
                }
            }

            break;
        }

        outcome.report(self.dry_run, color_choice);

        Ok(if outcome.refused.is_empty() { ExitCode::SUCCESS } else { ExitCode::FAILURE }.into())
    }
}

impl Session {
    /// Analyzes the project with its extension hosts, as `mago analyze` does, and compiles it in memory, as `mago
    /// compile` does. `None` when the standard library's package can't be found, which the log names.
    fn start<'configuration>(
        configuration: &'configuration Configuration,
        orchestrator: &mut Orchestrator<'configuration>,
        root: &Path,
    ) -> Result<Option<Self>, Error> {
        analyze_the_whole_workspace_without_paths(orchestrator);
        orchestrator.add_exclude_patterns(configuration.analyzer.excludes.iter());
        if let Some(external_analyzer) = start_external_analyzer(
            &configuration.extension_hosts,
            configuration.php_version,
            configuration.threads,
            &configuration.analyzer.plugins,
            configuration.analyzer.disable_default_plugins,
        ) {
            orchestrator.set_external_analyzer_handle(external_analyzer);
        }
        let mut database = orchestrator.load_database(&configuration.source.workspace, true, None, None)?;
        database.merge_base(Prelude::decode_database(PRELUDE_BYTES).expect("Failed to decode embedded prelude"));
        let standard_library = match standard_library(root, &database) {
            Ok(library) => library,
            Err(reason) => {
                tracing::error!("{reason}");
                return Ok(None);
            }
        };
        let mut server = Server::new(database.into_static(), decode_prelude, server_settings(orchestrator));
        let analysis = server.analyze()?;
        let issues = keys(&server, &analysis.issues);
        let library = Library::new(&server.standard_library_forms()?, server.codebase());
        let mut session = Self { server, issues, refused: HashSet::new(), library, standard_library };
        session.refused = session.compile(root)?;

        Ok(Some(session))
    }

    /// The PHP# text of `file`, or each construct that keeps it plain PHP, by its place.
    fn convert(&self, file: &File) -> Result<Vec<u8>, Vec<String>> {
        let arena = LocalArena::new();
        let program = parse_file(&arena, file);
        let names = NameResolver::new(&arena).resolve(program);
        let Some((_, artifacts)) = self.server.analyze_file_with_artifacts(file.id) else {
            return Err(vec![format!("{}: the analysis holds no such file", display(&file.name))]);
        };
        let input = Input {
            file,
            program,
            names: &names,
            codebase: self.server.codebase(),
            artifacts: &artifacts,
            library: &self.library,
        };

        convert(&input).map_err(|refusals| refusals.iter().map(|refusal| place(file, refusal)).collect())
    }

    /// The files `mago compile` refuses, with the analysis as it is: lowered in memory, with no `ignore` entry or
    /// baseline, since neither hides a language error in a `.sharp` file (spec section 27). A converted file states no
    /// law, so there is none to prove.
    fn compile(&mut self, root: &Path) -> Result<HashSet<FileId>, Error> {
        let compilations = self.server.compile(
            |path| unit::stamp(root, path),
            |_| Ok(Default::default()),
            &self.standard_library,
            |issues| issues,
        )?;

        Ok(compilations
            .into_iter()
            .filter_map(|(file_id, compilation)| matches!(compilation, Compilation::Refused(_)).then_some(file_id))
            .collect())
    }

    /// Puts `text` in the place of `file`, under its `.sharp` name, analyzes the project again, and compiles it in
    /// memory. Keeps it when no file gains an error, or a warning but the ones decision 9 expects, each compared by its
    /// file, code, message and line, and when the compile refuses no file it accepted before. Every issue of the
    /// converted file counts, since nothing hides a language error there. Gives the text and the expected warnings, or
    /// puts `file` back and gives each issue.
    fn verify(
        &mut self,
        workspace: &Path,
        root: &Path,
        file: &File,
        text: Vec<u8>,
    ) -> Result<(Vec<u8>, Vec<String>), Vec<String>> {
        let name = sharp_name(&file.name);
        let mut converted = File::new(
            Cow::Owned(name.clone()),
            FileType::Host,
            file.path.as_ref().map(|path| path.with_extension("sharp")),
            Cow::Owned(text.clone()),
        );
        mark_sharp_file(&mut converted, workspace);
        let converted_id = converted.id;
        self.server.database_mut().delete(file.id);
        self.server.database_mut().add(converted);

        let mut gained = Vec::new();
        let mut expected = Vec::new();
        let issues = match self.server.analyze_incremental(&[converted_id]) {
            Ok(result) => keys(&self.server, &result.issues),
            Err(error) => {
                gained.push(format!("{}: the analysis of the converted file failed: {error}", display(&name)));
                Issues::default()
            }
        };
        for (key, count) in &issues {
            let new = count.saturating_sub(self.issues.get(key).copied().unwrap_or(0));
            if new == 0 || key.1 == Level::Help || key.1 == Level::Note {
                continue;
            }
            let (file, level, code, message, line) = key;
            let code = code.clone().unwrap_or_default();
            let shown = format!("{}:{line}: {level} {code}: {message}", display(file));
            if level == &Level::Warning && EXPECTED_WARNINGS.contains(&code.as_str()) {
                expected.push(shown);
            } else {
                gained.push(format!("{shown}, which the converted file would add"));
            }
        }
        if gained.is_empty() {
            match self.compile(root) {
                Ok(refused) => {
                    for file_id in refused.difference(&self.refused) {
                        let refused = self.server.database().get(file_id).map(|file| display(&file.name));
                        gained.push(format!(
                            "{}: mago compile would refuse it",
                            refused.unwrap_or_else(|_| display(&name))
                        ));
                    }
                }
                Err(error) => gained.push(format!("{}: the compile failed: {error}", display(&name))),
            }
        }
        if gained.is_empty() {
            self.issues = issues;
            return Ok((text, expected));
        }

        self.server.database_mut().delete(converted_id);
        let original = File::new(file.name.clone(), file.file_type, file.path.clone(), file.contents.clone());
        let original_id = self.server.database_mut().add(original);
        if let Err(error) = self.server.analyze_incremental(&[original_id]) {
            gained.push(format!("{}: the analysis with the file put back failed: {error}", display(&file.name)));
        }

        Err(gained)
    }
}

impl Outcome {
    fn report(&self, dry_run: bool, color_choice: ColorChoice) {
        for (name, reasons) in &self.refused {
            let name = display(name);
            tracing::warn!("{name} stays plain PHP:");
            for reason in reasons {
                tracing::warn!("  {reason}");
            }
        }
        if dry_run {
            let mut formatter = PatchFormatter::new();
            if should_use_colors(color_choice) {
                formatter = formatter.with_color();
            }
            for (name, original, text) in &self.converted {
                let (original, text) = (String::from_utf8_lossy(original), String::from_utf8_lossy(text));
                let patch = diffy::create_patch(&original, &text);
                println!("{} -> {}", display(name), display(&sharp_name(name)));
                println!("{}", formatter.fmt_patch(&patch));
            }
        }
        for warning in &self.expected {
            tracing::info!("Expected after converting, decision 9: {warning}");
        }

        let converted = self.converted.len();
        let refused = self.refused.len();
        tracing::info!(
            "{} {converted} {} to PHP#, and {refused} {} plain PHP.",
            if dry_run { "Would convert" } else { "Converted" },
            if converted == 1 { "file" } else { "files" },
            if refused == 1 { "file stays" } else { "files stay" },
        );
        if converted > 0 {
            tracing::info!(
                "PHPDoc types are now checked while the code runs, so run the project's tests on the PHP# engine and compare them test by test."
            );
        }
    }
}

/// Moves the plain PHP file `name` to its `.sharp` name, with `git mv` when git tracks it, then writes `text` there.
fn write(workspace: &Path, name: &[u8], text: &[u8]) -> Result<(), Error> {
    let (source, target) = (PathBuf::from(display(name)), PathBuf::from(display(&sharp_name(name))));
    if !git::move_tracked(workspace, &source, &target)? {
        let source = workspace.join(&source);
        std::fs::remove_file(&source).map_err(|error| DatabaseError::io(IOAction::DeleteFile, &source, error))?;
    }
    write_by_rename(&workspace.join(&target), text)?;

    Ok(())
}

/// Each issue of `issues` by its file, level, code, message and line, with how many times it is reported.
fn keys(server: &Server, issues: &IssueCollection) -> Issues {
    let mut keys = Issues::default();
    for issue in issues.iter() {
        let (file, line) = issue
            .primary_span()
            .and_then(|span| {
                let file = server.database().get(&span.file_id).ok()?;
                Some((file.name.to_vec(), file.line_number(span.start.offset) + 1))
            })
            .unwrap_or_default();
        *keys.entry((file, issue.level, issue.code.clone(), issue.message.clone(), line)).or_default() += 1;
    }

    keys
}

/// Whether `file` is one the converter takes: one class-like, named like the file, under a PSR-4 root of
/// `composer.json`'s `autoload` (spec section 27). The reason it is not, otherwise.
fn shape(file: &File, roots: &[PathBuf]) -> Result<(), String> {
    let name = display(&file.name);
    if !roots.iter().any(|folder| Path::new(&name).starts_with(folder)) {
        return Err("it is under no PSR-4 root of composer.json's autoload, so it is a script or a test".to_owned());
    };

    let arena = LocalArena::new();
    let program = parse_file(&arena, file);
    let mut statements: Vec<&Statement> = Vec::new();
    for statement in &program.statements {
        match statement {
            Statement::Namespace(namespace) => statements.extend(namespace.statements().iter()),
            statement => statements.push(statement),
        }
    }
    let declared: Vec<&[u8]> = statements
        .iter()
        .filter_map(|statement| match statement {
            Statement::Class(class) => Some(class.name.value),
            Statement::Interface(interface) => Some(interface.name.value),
            Statement::Trait(r#trait) => Some(r#trait.name.value),
            Statement::Enum(r#enum) => Some(r#enum.name.value),
            _ => None,
        })
        .collect();
    let stem = Path::new(&name).file_stem().map(|stem| stem.to_string_lossy().into_owned()).unwrap_or_default();
    match declared.as_slice() {
        [] => Err("it declares no class, interface or enum, so it is a script".to_owned()),
        [only] if *only == stem.as_bytes() => Ok(()),
        [only] => Err(format!(
            "it declares `{}`, and a .sharp file declares the type it is named after (spec section 27)",
            String::from_utf8_lossy(only)
        )),
        several => {
            Err(format!("it declares {} types, and a .sharp file declares one (spec section 27)", several.len()))
        }
    }
}

/// Each PSR-4 folder of `composer.json`'s `autoload`, relative to the workspace. A test under `autoload-dev` is not
/// taken until a test runner finds a converted test.
fn psr4_roots(workspace: &Path) -> Result<Vec<PathBuf>, Error> {
    let path = workspace.join("composer.json");
    let Ok(contents) = std::fs::read_to_string(&path) else {
        return Ok(Vec::new());
    };
    let composer: ComposerPackage = contents.parse()?;
    let mut roots = Vec::new();
    for (_, value) in composer.autoload.map(|autoload| autoload.psr_4).unwrap_or_default() {
        let folders = match value {
            AutoloadPsr4value::String(folder) => vec![folder],
            AutoloadPsr4value::Array(folders) => folders,
        };
        roots.extend(folders.iter().map(|folder| PathBuf::from(folder.trim_end_matches('/'))));
    }

    Ok(roots)
}

fn relative(workspace: &Path, path: &Path) -> PathBuf {
    let absolute =
        if path.is_absolute() { path.to_path_buf() } else { std::env::current_dir().unwrap_or_default().join(path) };
    let absolute = absolute.canonicalize().unwrap_or(absolute);
    let workspace = workspace.canonicalize().unwrap_or_else(|_| workspace.to_path_buf());

    absolute.strip_prefix(&workspace).map(Path::to_path_buf).unwrap_or(absolute)
}

fn sharp_name(name: &[u8]) -> Vec<u8> {
    [name.strip_suffix(b".php").unwrap_or(name), b".sharp"].concat()
}

fn display(name: &[u8]) -> String {
    String::from_utf8_lossy(name).into_owned()
}

/// `file:line:column: reason` for a refusal in `file`.
fn place(file: &File, refusal: &Refusal) -> String {
    let line = file.line_number(refusal.span.start.offset) + 1;
    let column = file.column_number(refusal.span.start.offset) + 1;

    format!("{}:{line}:{column}: {}", display(&file.name), refusal.reason)
}
