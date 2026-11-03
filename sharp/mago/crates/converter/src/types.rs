//! Writes an analysis type in PHP#, with the file's own imports.
//!
//! A class is written by the name the file imports it under, by its short name in the file's own namespace, or by a
//! new `import` line the conversion adds. A type PHP# can't write, as `get_sharp_type` reads it, is the reason the file
//! is refused.

use std::collections::BTreeSet;

use foldhash::HashMap;

use mago_codex::metadata::CodebaseMetadata;
use mago_codex::ttype::SharpType;
use mago_codex::ttype::TType;
use mago_codex::ttype::atomic::TAtomic;
use mago_codex::ttype::atomic::scalar::class_like_string::TClassLikeString;
use mago_codex::ttype::get_sharp_type;
use mago_codex::ttype::union::TUnion;
use mago_word::Word;

/// The names a file reaches classes by: its namespace, its `use` lines, and the imports the conversion adds.
#[derive(Debug)]
pub struct Imports {
    namespace: String,
    /// Each `use` line's name, lowercase, and the class it names.
    used: HashMap<String, (String, String)>,
    added: BTreeSet<String>,
}

impl Imports {
    pub fn new(namespace: &str) -> Self {
        Self { namespace: namespace.trim_matches('\\').to_owned(), used: HashMap::default(), added: BTreeSet::new() }
    }

    pub fn add_use(&mut self, alias: &str, class: &str) {
        self.used.insert(alias.to_ascii_lowercase(), (alias.to_owned(), class.trim_start_matches('\\').to_owned()));
    }

    /// The imports the conversion adds, by their full dotted names.
    pub fn added(&self) -> impl Iterator<Item = String> + '_ {
        self.added.iter().map(|class| class.replace('\\', "."))
    }

    /// The name this file writes for `class`, a full PHP name, or the reason no name reaches it.
    pub fn name_of(&mut self, class: &str) -> Result<String, String> {
        let class = class.trim_start_matches('\\');
        let (namespace, short) = class.rsplit_once('\\').unwrap_or(("", class));
        if namespace.eq_ignore_ascii_case(&self.namespace) {
            return Ok(short.to_owned());
        }
        if let Some((alias, _)) = self.used.values().find(|(_, used)| used.eq_ignore_ascii_case(class)) {
            return Ok(alias.clone());
        }
        if let Some((_, used)) = self.used.get(&short.to_ascii_lowercase()) {
            return Err(format!("`{short}` names `{used}` in this file, so `{class}` has no name here"));
        }
        if namespace.is_empty() {
            return Err(format!("`{class}` is a PHP built-in class, which PHP# names only in the standard library"));
        }

        self.added.insert(class.to_owned());
        Ok(short.to_owned())
    }
}

/// `union` in PHP#'s spelling, or the reason PHP# can't write it.
pub fn type_text(union: &TUnion, codebase: &CodebaseMetadata, imports: &mut Imports) -> Result<String, String> {
    if union.types.iter().any(TAtomic::is_mixed) {
        return Ok(
            if union.types.iter().any(|atomic| matches!(atomic, TAtomic::Mixed(mixed) if mixed.is_non_null())) {
                "Any".to_owned()
            } else {
                "Any?".to_owned()
            },
        );
    }

    let mut members = Vec::new();
    for atomic in union.types.iter().filter(|atomic| !atomic.is_null()) {
        let text = atomic_text(atomic, codebase, imports)?;
        if !members.contains(&text) {
            members.push(text);
        }
    }

    Ok(match (union.has_null(), members.as_slice()) {
        (_, []) => return Err("`null` alone is no PHP# type".to_owned()),
        (false, [member]) => member.clone(),
        (false, _) => members.join("|"),
        (true, [member]) => format!("{member}?"),
        (true, _) => format!("({})?", members.join("|")),
    })
}

fn atomic_text(atomic: &TAtomic, codebase: &CodebaseMetadata, imports: &mut Imports) -> Result<String, String> {
    if atomic.get_intersection_types().is_some_and(|intersected| !intersected.is_empty()) {
        return Err(format!("the intersection `{}` is not converted yet", atomic.get_id()));
    }
    let Some(sharp_type) = get_sharp_type(atomic) else {
        return Err(format!("PHP# has no type `{}`", atomic.get_id()));
    };
    let mut nested = |union: &TUnion| type_text(union, codebase, imports);

    Ok(match sharp_type {
        SharpType::Int => "int".to_owned(),
        SharpType::Float => "float".to_owned(),
        SharpType::Bool => "bool".to_owned(),
        SharpType::String => "string".to_owned(),
        SharpType::Void => "void".to_owned(),
        SharpType::Object => "Object".to_owned(),
        SharpType::Enum(name) => class_name(name, codebase, imports)?,
        SharpType::Class(name, arguments) => {
            let class = class_name(name, codebase, imports)?;
            if arguments.is_empty() {
                class
            } else {
                let mut written = Vec::with_capacity(arguments.len());
                for argument in arguments {
                    written.push(type_text(argument, codebase, imports)?);
                }
                format!("{class}<{}>", written.join(", "))
            }
        }
        SharpType::List(element) => format!("List<{}>", nested(element)?),
        SharpType::Set(element) => format!("Set<{}>", nested(element)?),
        SharpType::Map(key, value) => format!("Map<{}, {}>", nested(key)?, nested(value)?),
        SharpType::Iterable(value) => format!("Iterable<{}>", nested(value)?),
        SharpType::Parameter(parameter) => parameter.parameter_name.to_string(),
        SharpType::ClassValue(TClassLikeString::Literal { value }) => {
            format!("Class<{}>", class_name(*value, codebase, imports)?)
        }
        SharpType::ClassValue(TClassLikeString::OfType { constraint, .. }) => format!("Class<{}>", nested(constraint)?),
        SharpType::ClassValue(_) | SharpType::Tuple(_) | SharpType::Function(_) => {
            return Err(format!("the type `{}` is not converted yet", atomic.get_id()));
        }
    })
}

fn class_name(name: Word, codebase: &CodebaseMetadata, imports: &mut Imports) -> Result<String, String> {
    let declared = codebase.get_class_like(name.as_bytes()).map_or(name, |class| class.original_name);

    imports.name_of(&declared.as_str_lossy())
}
