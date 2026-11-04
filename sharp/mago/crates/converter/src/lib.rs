//! Raises one plain PHP file into PHP#, the other way from `sharp-bridge`, which lowers PHP# into the engine's PHP.
//!
//! Each declaration's type is the one the project's analysis holds for it: its native declaration, its PHPDoc, an
//! extension's refinement, or the type it inherits from its parent, and never a type the analysis inferred from a body.
//! A declaration with no type anywhere is `mixed`, which PHP# writes `Any?`. The output is edits on the file's own text,
//! so its comments and layout stay.
//!
//! A file converts whole or not at all: each construct with no PHP# form yet, or none at all, is a [`Refusal`] that
//! names its place and its reason, and the file then stays plain PHP.

use mago_analyzer::artifacts::AnalysisArtifacts;
use mago_codex::metadata::CodebaseMetadata;
use mago_codex::metadata::class_like::ClassLikeMetadata;
use mago_codex::ttype::template::variance::Variance;
use mago_codex::ttype::union::TUnion;
use mago_database::file::File;
use mago_names::ResolvedNames;
use mago_span::HasSpan;
use mago_span::Span;
use mago_syntax::cst::Program;
use mago_syntax::cst::Statement;
use mago_syntax::cst::UseItems;
use mago_syntax::cst::UseType;
use mago_syntax::token::is_sharp_keyword;

use crate::edits::Edits;
use crate::library::Library;
use crate::types::Imports;
use crate::types::type_text;

mod body;
mod declaration;
pub mod edits;
pub mod library;
pub mod types;

/// What the conversion of one file reads: the file and its syntax tree, and the project's analysis of it.
pub struct Input<'input, 'arena> {
    pub file: &'input File,
    pub program: &'input Program<'arena>,
    pub names: &'input ResolvedNames<'arena>,
    pub codebase: &'input CodebaseMetadata,
    pub artifacts: &'input AnalysisArtifacts,
    pub library: &'input Library,
}

/// A construct the converter does not convert, at `span`, and why.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Refusal {
    pub span: Span,
    pub reason: String,
}

/// The PHP# text of `input`'s file, or every construct that keeps it plain PHP.
///
/// # Errors
///
/// Returns each [`Refusal`] the file holds.
pub fn convert(input: &Input<'_, '_>) -> Result<Vec<u8>, Vec<Refusal>> {
    let mut converter = Converter {
        input,
        source: &input.file.contents,
        edits: Edits::default(),
        imports: Imports::new(""),
        refusals: Vec::new(),
        class: None,
        returning: None,
        imports_at: None,
    };
    converter.program();
    if !converter.refusals.is_empty() {
        return Err(converter.refusals);
    }

    let added: Vec<String> = converter.imports.added().collect();
    if !added.is_empty() {
        let at = converter.imports_at.unwrap_or(0);
        let lines: String = added.iter().map(|import| format!("\nimport {import};")).collect();
        converter.edits.insert(at, lines);
    }

    converter.edits.finish(converter.source).ok_or_else(|| {
        vec![Refusal { span: input.program.span(), reason: "two of the converter's rewrites overlap".to_owned() }]
    })
}

/// The class a member belongs to.
struct Class<'input> {
    /// The class's name as the file declares it.
    name: String,
    metadata: &'input ClassLikeMetadata,
    is_final: bool,
}

struct Converter<'input, 'arena> {
    input: &'input Input<'input, 'arena>,
    source: &'input [u8],
    edits: Edits,
    imports: Imports,
    refusals: Vec<Refusal>,
    class: Option<Class<'input>>,
    /// The declared return of the method being converted, which a returned `new` takes its type arguments from.
    returning: Option<TUnion>,
    /// Where a new `import` line goes: after the last `use` line, else after the `namespace` line.
    imports_at: Option<u32>,
}

impl<'input> Converter<'input, '_> {
    fn refuse(&mut self, span: Span, reason: impl Into<String>) {
        self.refusals.push(Refusal { span, reason: reason.into() });
    }

    fn text(&self, span: Span) -> &'input [u8] {
        &self.source[span.start.offset as usize..span.end.offset as usize]
    }

    /// The offset of the next byte after `offset` that is no whitespace.
    fn skip_whitespace(&self, offset: u32) -> u32 {
        let mut end = offset as usize;
        while end < self.source.len() && self.source[end].is_ascii_whitespace() {
            end += 1;
        }
        end as u32
    }

    /// The start and end of the whole lines `span` fills, its indentation and its line break included, so deleting
    /// them leaves the lines around it as they were. Only `span` when other code shares its lines.
    fn lines_of(&self, span: Span) -> (u32, u32) {
        let (mut start, mut end) = (span.start.offset as usize, span.end.offset as usize);
        while start > 0 && matches!(self.source[start - 1], b' ' | b'\t') {
            start -= 1;
        }
        while end < self.source.len() && matches!(self.source[end], b' ' | b'\t' | b'\r') {
            end += 1;
        }
        let whole =
            (start == 0 || self.source[start - 1] == b'\n') && self.source.get(end).is_none_or(|byte| *byte == b'\n');
        if !whole {
            return (span.start.offset, span.end.offset);
        }

        (start as u32, (end + 1).min(self.source.len()) as u32)
    }

    /// `union` in PHP#, or a refusal at `span` with the type PHP# can't write.
    fn type_text(&mut self, union: &TUnion, span: Span) -> Option<String> {
        match type_text(union, self.input.codebase, &mut self.imports) {
            Ok(text) => Some(text),
            Err(reason) => {
                self.refuse(span, reason);
                None
            }
        }
    }

    /// The PHP# name of a variable `$name`: the name alone, with `@` before a PHP# keyword (spec section 2). `$this`
    /// is the keyword `this`.
    fn variable_name(name: &[u8]) -> String {
        let bare = name.strip_prefix(b"$").unwrap_or(name);
        let bare = String::from_utf8_lossy(bare).into_owned();
        if bare != "this" && is_sharp_keyword(bare.as_bytes()) { format!("@{bare}") } else { bare }
    }

    fn program(&mut self) {
        let program = self.input.program;
        if !program.errors.is_empty() {
            self.refuse(program.span(), "the file does not parse");
            return;
        }

        for statement in &program.statements {
            self.file_statement(statement);
        }
    }

    fn file_statement(&mut self, statement: &Statement<'_>) {
        match statement {
            Statement::OpeningTag(tag) => {
                let end = self.skip_whitespace(tag.span().end.offset);
                self.edits.delete(tag.span().start.offset, end);
            }
            Statement::Declare(declare) => {
                let strict = declare.items.iter().all(|item| item.name.value.eq_ignore_ascii_case(b"strict_types"));
                if !strict {
                    self.refuse(declare.span(), "a `declare` other than `strict_types`, which PHP# always is");
                    return;
                }
                let end = self.skip_whitespace(declare.span().end.offset);
                self.edits.delete(declare.span().start.offset, end);
            }
            Statement::Namespace(namespace) => {
                if let Some(name) = &namespace.name {
                    let dotted = String::from_utf8_lossy(name.value()).replace('\\', ".");
                    self.edits.replace(name.span(), dotted);
                    self.imports = Imports::new(&String::from_utf8_lossy(name.value()));
                }
                self.imports_at = Some(namespace.body.span().start.offset + 1);
                for statement in namespace.statements() {
                    self.file_statement(statement);
                }
            }
            Statement::Use(r#use) => {
                let end = self.skip_whitespace(r#use.span().end.offset);
                let sequence = match &r#use.items {
                    UseItems::Sequence(sequence) => sequence,
                    // A function is called through its namespace, which each call imports (spec section 23).
                    UseItems::TypedSequence(typed) if matches!(typed.r#type, UseType::Function(_)) => {
                        self.edits.delete(r#use.span().start.offset, end);
                        return;
                    }
                    _ => {
                        self.refuse(r#use.span(), "a `use const` or grouped `use` line is not converted yet");
                        return;
                    }
                };
                // A root class of `Builtins.sharp` is named bare, with no import (spec section 23).
                let defaults = sequence
                    .items
                    .iter()
                    .filter(|item| item.alias.is_none() && Imports::is_default(&String::from_utf8_lossy(item.name.value())))
                    .count();
                if defaults == sequence.items.len() {
                    self.edits.delete(r#use.span().start.offset, end);
                    return;
                }
                if defaults > 0 {
                    self.refuse(r#use.span(), "a `use` line that names a root class beside another is not converted yet");
                    return;
                }
                self.edits.replace(r#use.r#use.span, "import");
                for item in &sequence.items {
                    let name = String::from_utf8_lossy(item.name.value()).trim_start_matches('\\').to_owned();
                    self.edits.replace(item.name.span(), name.replace('\\', "."));
                    let alias = item.alias.as_ref().map_or_else(
                        || name.rsplit('\\').next().unwrap_or(&name).to_owned(),
                        |alias| String::from_utf8_lossy(alias.identifier.value).into_owned(),
                    );
                    self.imports.add_use(&alias, &name);
                }
                self.imports_at = Some(r#use.span().end.offset);
            }
            Statement::Class(class) => self.class(class),
            Statement::Interface(_) | Statement::Enum(_) => {
                self.refuse(statement.span(), "an interface or an enum is not converted yet");
            }
            Statement::Trait(_) => {
                self.refuse(statement.span(), "a trait has no PHP# form: PHP# declares no traits (spec section 22)");
            }
            _ => self.refuse(statement.span(), "a file-level statement or function has no PHP# form: a .sharp file declares one type (spec section 27)"),
        }
    }

    /// The type parameter list of a class whose analysis gives it type parameters, from PHPDoc or a refinement.
    fn type_parameters(&mut self, class: &ClassLikeMetadata, span: Span) -> Option<String> {
        if class.template_types.is_empty() {
            return Some(String::new());
        }

        let mut written = Vec::new();
        for (index, (name, template)) in class.template_types.iter().enumerate() {
            let variance = match class.template_variance.get(index) {
                Some(Variance::Covariant) => "out ",
                Some(Variance::Contravariant) => "in ",
                _ => "",
            };
            if template.constraint.is_mixed() {
                written.push(format!("{variance}{name}"));
            } else {
                let bound = self.type_text(&template.constraint, span)?;
                written.push(format!("{variance}{name} : {bound}"));
            }
        }

        Some(format!("<{}>", written.join(", ")))
    }
}
