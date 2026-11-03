//! The PHP# form of a bare PHP function call, read from declarations and never from a table of the converter's own.
//!
//! Two declarations give a form:
//!
//! - The prelude's stub of a type test, whose return is `($value is T ? true : false)`, as `is_string`'s is, gives
//!   `value is T`, the pattern of spec section 21, or `value == null` for `null`.
//! - A standard library member whose body is one call of a PHP function through `global`, with each of its receiver
//!   and parameters read once and every other argument a constant, as `contains(string other) =>
//!   global.str_contains(text, other)` is, gives that member, called with the call's arguments in its slots. This is
//!   the inlining rule of the bridge's `inline_forms`, read the other way.
//!
//! A function no declaration gives a form has none, and the converter refuses the file with its name.

use foldhash::HashMap;

use mago_allocator::LocalArena;
use mago_codex::metadata::CodebaseMetadata;
use mago_codex::ttype::atomic::TAtomic;
use mago_codex::ttype::atomic::scalar::TScalar;
use mago_codex::ttype::union::TUnion;
use mago_database::file::File;
use mago_span::HasSpan;
use mago_syntax::cst::Access;
use mago_syntax::cst::Argument;
use mago_syntax::cst::Call;
use mago_syntax::cst::ClassLikeMember;
use mago_syntax::cst::ClassLikeMemberSelector;
use mago_syntax::cst::Expression;
use mago_syntax::cst::FunctionLikeParameter;
use mago_syntax::cst::Literal;
use mago_syntax::cst::Method;
use mago_syntax::cst::MethodBody;
use mago_syntax::cst::Modifier;
use mago_syntax::cst::Statement;
use mago_syntax::parser::parse_file;

/// What the receiver of a standard library extension is, so a call's first argument picks the member of its kind.
#[derive(Debug, Clone, PartialEq, Eq)]
pub enum Receiver {
    String,
    Int,
    Float,
    List,
    ListOfStrings,
    Map,
    Set,
}

/// One argument of a member's body call: one of the member's slots, or a constant every call must pass as it is.
#[derive(Debug, Clone, PartialEq, Eq)]
pub enum Slot {
    Value(usize),
    Constant(Vec<u8>),
}

#[derive(Debug, Clone)]
pub enum Form {
    /// `value is T`, from the prelude's type test.
    TypeTest(TUnion),
    /// A standard library member: an extension member of `receiver`, or a static member of `class`.
    Member { class: String, member: String, receiver: Option<Receiver>, arguments: Vec<Slot> },
}

#[derive(Debug, Default)]
pub struct Library {
    /// Each PHP function's lowercase name, and the member forms that run it.
    members: HashMap<Vec<u8>, Vec<Form>>,
}

impl Library {
    /// The member forms of the standard library files among `files`.
    pub fn new<'file>(files: impl IntoIterator<Item = &'file File>) -> Self {
        let mut members: HashMap<Vec<u8>, Vec<Form>> = HashMap::default();
        for file in files.into_iter().filter(|file| file.is_standard_library) {
            let arena = LocalArena::new();
            let program = parse_file(&arena, file);
            let namespace = program
                .statements
                .iter()
                .find_map(|statement| match statement {
                    Statement::Namespace(namespace) => namespace.name.as_ref().map(|name| name.value()),
                    _ => None,
                })
                .unwrap_or_default();
            let classes = program.statements.iter().filter_map(|statement| match statement {
                Statement::Class(class) => Some(class),
                Statement::Namespace(namespace) => {
                    namespace.statements().iter().find_map(|statement| match statement {
                        Statement::Class(class) => Some(class),
                        _ => None,
                    })
                }
                _ => None,
            });
            for class in classes {
                let class_name = format!(
                    "{}\\{}",
                    String::from_utf8_lossy(namespace).replace('.', "\\"),
                    String::from_utf8_lossy(class.name.value)
                );
                for member in &class.members {
                    match member {
                        ClassLikeMember::Method(method) if is_public_static(method) => {
                            if let Some((function, form)) = member_form(&file.contents, &class_name, method, None) {
                                members.entry(function).or_default().push(form);
                            }
                        }
                        ClassLikeMember::Extension(extension) => {
                            let [receiver] = extension.parameter_list.parameters.as_slice() else {
                                continue;
                            };
                            for member in &extension.members {
                                if let ClassLikeMember::Method(method) = member
                                    && let Some((function, form)) =
                                        member_form(&file.contents, &class_name, method, Some(receiver))
                                {
                                    members.entry(function).or_default().push(form);
                                }
                            }
                        }
                        _ => {}
                    }
                }
            }
        }

        Self { members }
    }

    /// The forms of the PHP function `name`: its prelude type test, then each standard library member that runs it.
    pub fn forms(&self, name: &[u8], codebase: &CodebaseMetadata) -> Vec<Form> {
        let name = name.to_ascii_lowercase();
        let mut forms: Vec<Form> = type_test(&name, codebase).into_iter().collect();
        forms.extend(self.members.get(&name).into_iter().flatten().cloned());

        forms
    }
}

fn is_public_static(method: &Method) -> bool {
    method.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Static(_)))
        && method.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Public(_)))
}

/// The type test the prelude declares for `name`: one parameter, and a return of `($value is T ? true : false)`.
fn type_test(name: &[u8], codebase: &CodebaseMetadata) -> Option<Form> {
    let function = codebase.get_function(name)?;
    if function.parameters.len() != 1 {
        return None;
    }
    let returned = &function.return_type_metadata.as_ref()?.type_union;
    let [TAtomic::Conditional(conditional)] = returned.types.as_ref() else {
        return None;
    };
    let is_literal = |union: &TUnion, value: bool| matches!(union.types.as_ref(), [TAtomic::Scalar(TScalar::Bool(boolean))] if boolean.value == Some(value));
    if conditional.negated || !is_literal(&conditional.then, true) || !is_literal(&conditional.otherwise, false) {
        return None;
    }

    Some(Form::TypeTest((*conditional.target).clone()))
}

/// The PHP function `method`'s body calls, and its form, when the body is one `global.f(…)` call that reads each slot,
/// the receiver first, once.
fn member_form(
    contents: &[u8],
    class: &str,
    method: &Method,
    receiver: Option<&FunctionLikeParameter>,
) -> Option<(Vec<u8>, Form)> {
    let MethodBody::Expression(body) = &method.body else {
        return None;
    };
    let Expression::Call(Call::Method(call)) = body.expression else {
        return None;
    };
    let Expression::ConstantAccess(object) = call.object else {
        return None;
    };
    if object.name.value() != b"global" {
        return None;
    }
    let ClassLikeMemberSelector::Identifier(function) = &call.method else {
        return None;
    };

    let slots: Vec<&[u8]> =
        receiver.into_iter().chain(method.parameter_list.parameters.iter()).map(|p| p.variable.name).collect();
    let mut read = vec![false; slots.len()];
    let mut arguments = Vec::new();
    for argument in &call.argument_list.arguments {
        let Argument::Positional(argument) = argument else {
            return None;
        };
        if argument.ellipsis.is_some() {
            return None;
        }
        arguments.push(match argument.value {
            Expression::ConstantAccess(name) => {
                let slot = slots.iter().position(|slot| *slot == name.name.value())?;
                if std::mem::replace(&mut read[slot], true) {
                    return None;
                }
                Slot::Value(slot)
            }
            Expression::Literal(literal) => Slot::Constant(literal_value(literal)?),
            Expression::Access(Access::Property(access))
                if matches!(access.object, Expression::ConstantAccess(global) if global.name.value() == b"global") =>
            {
                let ClassLikeMemberSelector::Identifier(constant) = &access.property else {
                    return None;
                };
                Slot::Constant(constant.value.to_vec())
            }
            _ => return None,
        });
    }
    if read.iter().any(|read| !read) {
        return None;
    }

    let receiver = match receiver {
        None => None,
        Some(receiver) => Some(receiver_of(contents, receiver)?),
    };

    Some((
        function.value.to_ascii_lowercase(),
        Form::Member {
            class: class.to_owned(),
            member: String::from_utf8_lossy(method.name.value).into_owned(),
            receiver,
            arguments,
        },
    ))
}

fn receiver_of(contents: &[u8], receiver: &FunctionLikeParameter) -> Option<Receiver> {
    let span = receiver.hint.as_ref()?.span();
    let text = String::from_utf8_lossy(&contents[span.start.offset as usize..span.end.offset as usize])
        .split_whitespace()
        .collect::<String>();

    Some(match text.as_str() {
        _ if text.starts_with("List<string>") => Receiver::ListOfStrings,
        _ if text.starts_with("List<") => Receiver::List,
        _ if text.starts_with("Map<") => Receiver::Map,
        _ if text.starts_with("Set<") => Receiver::Set,
        "string" => Receiver::String,
        "int" => Receiver::Int,
        "float" => Receiver::Float,
        _ => return None,
    })
}

/// The value a literal writes: a string's text without its quotes, or a number's digits.
pub fn literal_value(literal: &Literal) -> Option<Vec<u8>> {
    match literal {
        Literal::String(string) => string.value.map(<[u8]>::to_vec),
        Literal::Integer(integer) => Some(integer.raw.to_vec()),
        _ => None,
    }
}
