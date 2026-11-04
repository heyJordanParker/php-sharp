//! The PHP# form of a bare PHP function call, read from declarations and never from a table of the converter's own.
//!
//! - A PHP built-in has a form two ways. The prelude's stub of a type test, whose return is `($value is T ? true :
//!   false)`, as `is_string`'s is, gives `value is T` (spec section 21). A standard library member whose inline form,
//!   as the bridge's `inline_forms` takes it, is one call of exactly that function gives that member, called with the
//!   call's arguments in its slots. The bridge owns the forms, so the library is never read a second time here.
//! - A function a plain PHP library declares in a namespace, or in PHP's root namespace beside the classes of one, is
//!   called through that namespace (spec section 23), which the body rewrite imports.
//!
//! A member runs a built-in with PHP's meaning only when every constant it passes is the call's own, its return holds
//! the function's, and it feeds no by-reference parameter but a `ref` receiver. Any other built-in has no form, and the
//! converter refuses its file, naming the function and the difference.

use foldhash::HashMap;

use mago_codex::metadata::CodebaseMetadata;
use mago_codex::metadata::function_like::FunctionLikeMetadata;
use mago_codex::ttype::TType;
use mago_codex::ttype::atomic::TAtomic;
use mago_codex::ttype::atomic::scalar::TScalar;
use mago_codex::ttype::comparator::ComparisonResult;
use mago_codex::ttype::comparator::union_comparator::is_contained_by;
use mago_codex::ttype::union::TUnion;
use mago_sharp_bridge::InlineForms;
use mago_sharp_bridge::PhpCall;

/// How a standard library member is called.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Shape {
    /// `Class.member(…)`.
    Static,
    /// `receiver.member(…)`, an extension method (spec section 26).
    Method,
    /// `receiver.member`, an extension's computed property.
    Property,
}

/// A standard library member whose inline form is one call of a plain PHP function.
#[derive(Debug, Clone)]
pub struct Member {
    /// Its class's full PHP name, as `Sharp\Encoding\Base64`.
    pub class: String,
    pub name: String,
    pub shape: Shape,
    /// What its form passes the PHP function.
    pub call: PhpCall,
    /// How many slots its form reads in order before its call, as the bridge's `InlineForm::in_order` gives.
    pub in_order: usize,
}

#[derive(Debug, Default)]
pub struct Library {
    /// Each PHP function's lowercase name, and the members whose form calls it.
    members: HashMap<Vec<u8>, Vec<Member>>,
}

impl Library {
    /// The members of `forms` whose form is one call of a plain PHP function.
    pub fn new(forms: &InlineForms, codebase: &CodebaseMetadata) -> Self {
        let mut members: HashMap<Vec<u8>, Vec<Member>> = HashMap::default();
        for (class, member, form) in forms.iter() {
            let (Some(call), Some(class), Some(method)) =
                (form.php_call(), codebase.get_class_like(class), codebase.get_method(class, member))
            else {
                continue;
            };
            let shape = if method.flags.is_extension_property() {
                Shape::Property
            } else if method.flags.is_extension_method() {
                Shape::Method
            } else {
                Shape::Static
            };
            members.entry(call.function.to_ascii_lowercase()).or_default().push(Member {
                class: class.original_name.as_str_lossy().into_owned(),
                name: method.original_name.as_str_lossy().into_owned(),
                shape,
                call: call.clone(),
                in_order: form.in_order(),
            });
        }
        for candidates in members.values_mut() {
            candidates.sort_by(|a, b| (&a.class, &a.name).cmp(&(&b.class, &b.name)));
        }

        Self { members }
    }

    /// The members whose form calls the PHP function `name`.
    pub fn members(&self, name: &[u8]) -> &[Member] {
        self.members.get(&name.to_ascii_lowercase()).map_or(&[], Vec::as_slice)
    }
}

/// The type the prelude's type test `function` tests for: one parameter, and a return of `($value is T ? true :
/// false)`.
pub fn type_test(function: &FunctionLikeMetadata) -> Option<&TUnion> {
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

    Some(&conditional.target)
}

/// Why `member` doesn't run the PHP built-in `function` with PHP's meaning, or `None` when it does: its return holds
/// the function's, and no slot of it feeds a by-reference parameter but a `ref` receiver.
pub fn differs(member: &Member, function: &FunctionLikeMetadata, codebase: &CodebaseMetadata) -> Option<String> {
    let method = codebase.get_method(member.class.as_bytes(), member.name.as_bytes())?;
    let name = function.original_name.as_str_lossy();
    if let (Some(php), Some(sharp)) = (&function.return_type_metadata, &method.return_type_metadata)
        && !is_contained_by(
            codebase,
            &branches(&php.type_union),
            &sharp.type_union,
            false,
            false,
            false,
            &mut ComparisonResult::new(),
        )
    {
        return Some(format!(
            "`{name}` returns `{}`, and `{}.{}` returns `{}`, which doesn't hold it",
            php.type_union.get_id(),
            short(&member.class),
            member.name,
            sharp.type_union.get_id()
        ));
    }
    for (position, argument) in member.call.arguments.iter().enumerate() {
        let mago_sharp_bridge::PhpCallArgument::Slot(slot) = argument else {
            continue;
        };
        let by_reference = function.parameters.get(position).is_some_and(|parameter| parameter.flags.is_by_reference());
        let ref_receiver = *slot == 0
            && member.shape != Shape::Static
            && method.parameters.first().is_some_and(|receiver| receiver.flags.is_by_reference());
        if by_reference && !ref_receiver {
            return Some(format!("`{name}` takes its argument {} by reference", position + 1));
        }
    }

    None
}

/// `union` with each conditional type replaced by both of its branches, which together are every value it gives, as
/// `count`'s `($value is non-empty-array ? positive-int : non-negative-int)` gives an `int`.
fn branches(union: &TUnion) -> TUnion {
    let mut types = Vec::new();
    for atomic in union.types.iter() {
        match atomic {
            TAtomic::Conditional(conditional) => {
                types.extend(branches(&conditional.then).types.iter().cloned());
                types.extend(branches(&conditional.otherwise).types.iter().cloned());
            }
            atomic => types.push(atomic.clone()),
        }
    }

    TUnion::from_vec(types)
}

/// The last part of a full PHP name.
pub fn short(name: &str) -> &str {
    name.rsplit('\\').next().unwrap_or(name)
}
