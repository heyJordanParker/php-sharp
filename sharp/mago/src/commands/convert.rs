//! `mago convert <paths>`: raises each plain PHP file the paths name into PHP#, in place, as `Foo.php` becoming
//! `Foo.sharp`, or reports each construct that keeps it plain PHP.
//!
//! The types come from the project's own analysis, run as `mago analyze` runs it. Before it writes a file, the command
//! analyzes the converted text against the whole project, and keeps the file plain PHP when any file gains an error,
//! the converted one included, where no `ignore` entry or baseline hides a language error (spec section 27). Git is the
//! undo: the command moves each tracked file with `git mv`, so the index holds the rename alone, git's history follows
//! the file through it, and the `.php` file `mago compile` later writes at the old path is never the tracked one.

use std::borrow::Cow;
use std::collections::BTreeMap;
use std::collections::HashSet;
use std::path::Path;
use std::path::PathBuf;
use std::process::ExitCode;
use std::sync::Arc;

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
use mago_prelude::Prelude;
use mago_reporting::IssueCollection;
use mago_reporting::Level;
use mago_server::Server;
use mago_syntax::cst::Statement;
use mago_syntax::parser::parse_file;

use crate::commands::analyze::analyze_the_whole_workspace_without_paths;
use crate::commands::analyze::decode_prelude;
use crate::commands::analyze::server_settings;
use crate::commands::compile::compile_for_hosts;
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

/// An issue as a later analysis finds it again: its file, code and message, whatever its line.
type IssueKey = (Vec<u8>, Option<String>, String);

impl ConvertCommand {
    pub fn execute(self, mut configuration: Configuration, color_choice: ColorChoice) -> Result<CommandOutcome, Error> {
        configuration.extension_hosts = compile_for_hosts(&configuration)?.0;
        let workspace = configuration.source.workspace.clone();

        let mut orchestrator = create_orchestrator(&configuration, color_choice, false, false, false);
        analyze_the_whole_workspace_without_paths(&mut orchestrator);
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
        let mut database = orchestrator.load_database(&workspace, true, None, None)?;
        database.merge_base(Prelude::decode_database(PRELUDE_BYTES).expect("Failed to decode embedded prelude"));
        let mut server = Server::new(database.into_static(), decode_prelude, server_settings(&orchestrator));
        let analysis = server.analyze()?;
        let before: HashSet<IssueKey> = errors(&server, &analysis.issues).into_iter().collect();

        let roots = psr4_roots(&workspace)?;
        let named: Vec<PathBuf> = self.paths.iter().map(|path| relative(&workspace, path)).collect();
        let mut candidates: Vec<(FileId, Vec<u8>)> = server
            .database()
            .files()
            .filter(|file| file.file_type == FileType::Host && file.name.ends_with(b".php"))
            .filter(|file| {
                let name = Path::new(std::str::from_utf8(&file.name).unwrap_or_default());
                named.iter().any(|path| name.starts_with(path))
            })
            .map(|file| (file.id, file.name.to_vec()))
            .collect();
        candidates.sort_by(|a, b| a.1.cmp(&b.1));
        let standard_library: Vec<_> = server.database().files().filter(|file| file.is_standard_library).collect();
        let library = Library::new(standard_library.iter().map(AsRef::as_ref));

        let mut refused: BTreeMap<Vec<u8>, Vec<String>> = BTreeMap::new();
        let mut converted: Vec<(Arc<File>, Vec<u8>)> = Vec::new();
        for (file_id, name) in candidates {
            let file = server.database().get(&file_id)?;
            if let Err(reason) = shape(&file, &roots) {
                refused.entry(name).or_default().push(format!("{}: {reason}", display(&file.name)));
                continue;
            }

            let text = {
                let arena = LocalArena::new();
                let program = parse_file(&arena, &file);
                let names = NameResolver::new(&arena).resolve(program);
                let Some((_, artifacts)) = server.analyze_file_with_artifacts(file_id) else {
                    continue;
                };
                let input = Input {
                    file: &file,
                    program,
                    names: &names,
                    codebase: server.codebase(),
                    artifacts: &artifacts,
                    library: &library,
                };
                convert(&input).map_err(|refusals| refusals.iter().map(|refusal| place(&file, refusal)).collect())
            };
            match text.and_then(|text| verify(&mut server, &workspace, &file, text, &before)) {
                Ok(text) => converted.push((file, text)),
                Err(reasons) => {
                    refused.entry(name).or_default().extend::<Vec<String>>(reasons);
                }
            }
        }

        for (name, reasons) in &refused {
            let name = display(name);
            tracing::warn!("{name} stays plain PHP:");
            for reason in reasons {
                tracing::warn!("  {reason}");
            }
        }

        if self.dry_run {
            let mut formatter = PatchFormatter::new();
            if should_use_colors(color_choice) {
                formatter = formatter.with_color();
            }
            for (file, text) in &converted {
                let (original, modified) = (String::from_utf8_lossy(&file.contents), String::from_utf8_lossy(text));
                let patch = diffy::create_patch(&original, &modified);
                println!("{} -> {}", display(&file.name), display(&sharp_name(&file.name)));
                println!("{}", formatter.fmt_patch(&patch));
            }
        } else {
            for (file, text) in &converted {
                let (source, target) =
                    (PathBuf::from(display(&file.name)), PathBuf::from(display(&sharp_name(&file.name))));
                if !git::move_tracked(&workspace, &source, &target)? {
                    let source = workspace.join(&source);
                    std::fs::remove_file(&source)
                        .map_err(|error| DatabaseError::io(IOAction::DeleteFile, &source, error))?;
                }
                write_by_rename(&workspace.join(&target), text)?;
            }
        }

        let summary = format!(
            "{} {} {} to PHP#, and {} {} plain PHP.",
            if self.dry_run { "Would convert" } else { "Converted" },
            converted.len(),
            if converted.len() == 1 { "file" } else { "files" },
            refused.len(),
            if refused.len() == 1 { "file stays" } else { "files stay" },
        );
        tracing::info!("{summary}");

        Ok(if refused.is_empty() { ExitCode::SUCCESS } else { ExitCode::FAILURE }.into())
    }
}

/// Each error of `issues`, by its file's name, code and message.
fn errors(server: &Server, issues: &IssueCollection) -> Vec<IssueKey> {
    issues
        .iter()
        .filter(|issue| issue.level == Level::Error)
        .map(|issue| {
            let file = issue
                .primary_span()
                .and_then(|span| server.database().get(&span.file_id).ok())
                .map(|file| file.name.to_vec())
                .unwrap_or_default();
            (file, issue.code.clone(), issue.message.clone())
        })
        .collect()
}

/// Puts `text` in the place of `file`, under its `.sharp` name, and analyzes the project again. Keeps it when no file
/// gains an error, and every error of the converted file counts, since nothing hides a language error there. Otherwise
/// puts `file` back and gives each error.
fn verify(
    server: &mut Server,
    workspace: &Path,
    file: &File,
    text: Vec<u8>,
    before: &HashSet<IssueKey>,
) -> Result<Vec<u8>, Vec<String>> {
    let name = sharp_name(&file.name);
    let mut converted = File::new(
        Cow::Owned(name.clone()),
        FileType::Host,
        file.path.as_ref().map(|path| path.with_extension("sharp")),
        Cow::Owned(text.clone()),
    );
    mark_sharp_file(&mut converted, workspace);
    let converted_id = converted.id;

    server.database_mut().delete(file.id);
    server.database_mut().add(converted);
    let issues = match server.analyze_incremental(&[converted_id]) {
        Ok(result) => result.issues,
        Err(error) => {
            return Err(vec![format!("{}: the analysis of the converted file failed: {error}", display(&name))]);
        }
    };
    let mut gained: Vec<String> = errors(server, &issues)
        .into_iter()
        .filter(|key| key.0 == name || !before.contains(key))
        .map(|(file, code, message)| {
            format!("{} would have the error {}: {message}", display(&file), code.unwrap_or_default())
        })
        .collect();
    if gained.is_empty() {
        return Ok(text);
    }

    server.database_mut().delete(converted_id);
    let original = File::new(file.name.clone(), file.file_type, file.path.clone(), file.contents.clone());
    let original_id = server.database_mut().add(original);
    if let Err(error) = server.analyze_incremental(&[original_id]) {
        gained.push(format!("{}: the analysis with the file put back failed: {error}", display(&file.name)));
    }

    Err(gained)
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
