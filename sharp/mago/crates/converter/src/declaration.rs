//! Classes and their members: the header, properties and fields, methods and constructors, and their parameters.
//!
//! PHP# defaults a class to `internal` and a member to `private` (spec section 5), so the converter writes `public`
//! wherever PHP leaves the visibility out. PHP's methods are open unless `final`, and PHP#'s are closed unless
//! `virtual` (spec section 22), so an open PHP method is `virtual`, and one that replaces its parent's is `override`.

use mago_codex::ttype::get_mixed;
use mago_codex::ttype::union::TUnion;
use mago_span::HasSpan;
use mago_span::Span;
use mago_syntax::cst::AttributeList;
use mago_syntax::cst::Call;
use mago_syntax::cst::ClassLikeMember;
use mago_syntax::cst::ClassLikeMemberSelector;
use mago_syntax::cst::Expression;
use mago_syntax::cst::FunctionLikeParameter;
use mago_syntax::cst::Method;
use mago_syntax::cst::MethodBody;
use mago_syntax::cst::Modifier;
use mago_syntax::cst::PartialArgument;
use mago_syntax::cst::PlainProperty;
use mago_syntax::cst::Property;
use mago_syntax::cst::PropertyItem;
use mago_syntax::cst::Sequence;
use mago_syntax::cst::Statement;
use mago_syntax::cst::StaticMethodCall;

use crate::Class;
use crate::Converter;

/// The PHPDoc tags whose types the converter writes in the declaration, which leave its docblock.
const WRITTEN_TAGS: [&str; 7] =
    ["@var", "@param", "@return", "@template", "@template-covariant", "@template-contravariant", "@psalm-template"];

impl Converter<'_, '_> {
    pub(crate) fn class(&mut self, class: &mago_syntax::cst::Class<'_>) {
        let name = String::from_utf8_lossy(class.name.value).into_owned();
        let full_name = self.input.names.get(&class.name);
        let Some(metadata) = self.input.codebase.get_class_like(full_name) else {
            self.refuse(class.name.span, "the analysis holds no class of this name");
            return;
        };

        self.attributes(&class.attribute_lists);
        if let Some(readonly) = class.modifiers.iter().find(|modifier| matches!(modifier, Modifier::Readonly(_))) {
            self.refuse(readonly.span(), "a `readonly` class is not converted yet: each property becomes `{ get; }`");
        }
        let header_start = class.modifiers.first().map_or(class.class.span, HasSpan::span).start.offset;
        self.edits.insert(header_start, "public ");

        if let Some(parameters) = self.type_parameters(metadata, class.name.span) {
            if !parameters.is_empty() {
                self.edits.insert(class.name.span.end.offset, parameters);
            }
        }

        match (&class.extends, &class.implements) {
            (Some(extends), Some(implements)) => {
                self.edits.replace(extends.extends.span, ":");
                self.names(extends.types.iter().map(HasSpan::span));
                let last = extends.types.last().map_or(extends.extends.span, HasSpan::span);
                self.edits.replace_range(last.end.offset, implements.implements.span.end.offset, ",");
                self.names(implements.types.iter().map(HasSpan::span));
            }
            (Some(extends), None) => {
                self.edits.replace(extends.extends.span, ":");
                self.names(extends.types.iter().map(HasSpan::span));
            }
            (None, Some(implements)) => {
                self.edits.replace(implements.implements.span, ":");
                self.names(implements.types.iter().map(HasSpan::span));
            }
            (None, None) => {}
        }
        self.docblock(class.span());

        let is_final = class.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Final(_)));
        self.class = Some(Class { name, metadata, is_final });
        for member in &class.members {
            match member {
                ClassLikeMember::Method(method) => self.method(method),
                ClassLikeMember::Property(Property::Plain(property)) => self.property(property),
                ClassLikeMember::Constant(constant) => {
                    self.refuse(constant.span(), "a class constant is not converted yet");
                }
                ClassLikeMember::TraitUse(trait_use) => {
                    self.refuse(trait_use.span(), "a trait in a class header is not converted yet");
                }
                _ => self.refuse(member.span(), "this member is not converted yet"),
            }
        }
        self.class = None;
    }

    /// A qualified class name in a header, written with `.` between its parts.
    fn names(&mut self, spans: impl Iterator<Item = Span>) {
        for span in spans {
            let text = String::from_utf8_lossy(self.text(span)).into_owned();
            if text.contains('\\') {
                self.refuse(span, "a qualified class name is not converted yet: import the class");
            }
        }
    }

    /// PHP's `#[…]` attributes as PHP#'s `[…]` (spec section 1). Each stays, with its arguments.
    pub(crate) fn attributes(&mut self, lists: &Sequence<'_, AttributeList<'_>>) {
        for list in lists {
            self.edits.replace(list.hash_left_bracket, "[");
            for attribute in &list.attributes {
                let Some(arguments) = &attribute.argument_list else {
                    continue;
                };
                for argument in &arguments.arguments {
                    match argument {
                        PartialArgument::Positional(argument) => self.expression(argument.value, None),
                        PartialArgument::Named(argument) => self.expression(argument.value, None),
                        _ => self.refuse(argument.span(), "a placeholder argument has no PHP# form"),
                    }
                }
            }
        }
    }

    /// Takes each tag whose type the declaration now writes out of the docblock right before `declaration`, and the
    /// whole docblock when nothing else is left in it. Its prose and every other tag stay.
    fn docblock(&mut self, declaration: Span) {
        let start = declaration.start.offset;
        let Some(docblock) = self.input.program.trivia.iter().rev().find(|trivia| {
            trivia.kind.is_docblock()
                && trivia.span.end.offset <= start
                && self.source[trivia.span.end.offset as usize..start as usize].iter().all(u8::is_ascii_whitespace)
        }) else {
            return;
        };

        let text = String::from_utf8_lossy(docblock.value).into_owned();
        let mut kept: Vec<&str> = Vec::new();
        let mut taken = false;
        for line in text.lines() {
            let content = line.trim().trim_start_matches("/**").trim_start_matches('*').trim();
            if WRITTEN_TAGS.iter().any(|tag| content.split_whitespace().next() == Some(*tag)) {
                taken = true;
                continue;
            }
            kept.push(line);
        }
        if !taken {
            return;
        }

        let is_empty = |line: &str| {
            let content = line.trim().trim_start_matches("/**").trim_end_matches("*/").trim_start_matches('*').trim();
            content.is_empty()
        };
        if kept.iter().all(|line| is_empty(line)) {
            self.edits.delete(docblock.span.start.offset, start);
            return;
        }
        // A blank line left right before the closing `*/` goes too.
        while kept.len() > 2 && is_empty(kept[kept.len() - 2]) && !kept[kept.len() - 2].contains("/**") {
            kept.remove(kept.len() - 2);
        }
        self.edits.replace(docblock.span, kept.join("\n"));
    }

    fn visibility_start(modifiers: &Sequence<'_, Modifier<'_>>, keyword: Span) -> Option<u32> {
        let has_visibility = modifiers
            .iter()
            .any(|modifier| matches!(modifier, Modifier::Public(_) | Modifier::Protected(_) | Modifier::Private(_)));

        (!has_visibility).then(|| modifiers.first().map_or(keyword, HasSpan::span).start.offset)
    }

    fn property(&mut self, property: &PlainProperty<'_>) {
        let Some(class) = self.class.as_ref() else {
            return;
        };
        let metadata = class.metadata;
        let [item] = property.items.as_slice() else {
            self.refuse(property.span(), "a field or a property declares one name (spec section 6)");
            return;
        };
        let variable = item.variable();
        // A type the codex marks `inferred` here is inherited: declared in the parent, which the override rules need.
        let Some(declared) = metadata.properties.get(&mago_word::word(variable.name)) else {
            self.refuse(variable.span, "the analysis holds no property of this name");
            return;
        };

        self.attributes(&property.attribute_lists);
        let union = declared.type_metadata.as_ref().map_or_else(get_mixed, |metadata| metadata.type_union.clone());
        let Some(written) = self.type_text(&union, variable.span) else {
            return;
        };

        if let Some(var) = &property.var {
            self.edits.replace(var.span, "public");
        }
        let is_public =
            property.var.is_some() || property.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Public(_)));
        let readonly = property.modifiers.iter().find(|modifier| matches!(modifier, Modifier::Readonly(_)));
        if let Some(readonly) = readonly {
            let end = self.skip_whitespace(readonly.span().end.offset);
            self.edits.delete(readonly.span().start.offset, end);
        }
        let is_static = property.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Static(_)));
        if property.var.is_none()
            && let Some(at) = Self::visibility_start(&property.modifiers, variable.span)
        {
            self.edits.insert(at, "public ");
        }

        match &property.hint {
            Some(hint) => self.edits.replace(hint.span(), written.clone()),
            None => self.edits.insert(variable.span.start.offset, format!("{written} ")),
        }
        self.edits.replace(variable.span, Self::variable_name(variable.name));

        // A field is storage, and PHP#'s API is a property (spec section 6): a public PHP property, and a `readonly`
        // one, which PHP# writes get-only.
        let accessors = match (is_public || is_static, readonly.is_some()) {
            (_, true) => Some(" { get; }"),
            (true, false) => Some(" { get; set; }"),
            (false, false) => None,
        };
        if let Some(accessors) = accessors {
            self.edits.insert(variable.span.end.offset, accessors);
        }

        // A property's initial value follows its accessors, which go right after the name.
        match item {
            PropertyItem::Concrete(concrete) => self.expression(concrete.value, Some(&union)),
            // PHP gives an untyped property null, which PHP# writes, since a member has no implicit value (spec
            // section 6).
            PropertyItem::Abstract(_) if property.hint.is_none() => {
                self.edits.insert(variable.span.end.offset, " = null");
            }
            PropertyItem::Abstract(_) => {}
        }
        self.docblock(property.span());
    }

    fn method(&mut self, method: &Method<'_>) {
        let Some(class) = self.class.as_ref() else {
            return;
        };
        let (class_name, metadata, class_is_final) = (class.name.clone(), class.metadata, class.is_final);
        let name = method.name.value;
        let Some(declared) = self.input.codebase.get_method(metadata.name.as_bytes(), name) else {
            self.refuse(method.name.span, "the analysis holds no method of this name");
            return;
        };
        if let Some(ampersand) = method.ampersand {
            self.refuse(ampersand, "a method that returns by reference has no PHP# form: PHP# has no references");
            return;
        }
        let is_constructor = name.eq_ignore_ascii_case(b"__construct");
        if name.starts_with(b"__") && !is_constructor {
            self.refuse(
                method.name.span,
                format!("the magic method `{}` is not converted yet", String::from_utf8_lossy(name)),
            );
            return;
        }
        let Some(function) = method.function else {
            return;
        };

        self.attributes(&method.attribute_lists);
        if let Some(at) = Self::visibility_start(&method.modifiers, function.span) {
            self.edits.insert(at, "public ");
        }

        let mut skipped = 0;
        if is_constructor {
            self.edits.replace_range(function.span.start.offset, method.name.span.end.offset, class_name);
            if metadata.direct_parent_class.is_some() {
                let Some(skip) = self.super_call(method, metadata.name.as_bytes()) else {
                    return;
                };
                skipped = skip;
            }
        } else {
            // A return the codex marks `inferred` is inherited from the parent, which is declared. A return taken from the
            // body is the analysis's inference.
            if declared.return_from_body.is_some() {
                self.refuse(method.name.span, format!(
                    "`{}`'s return is taken from its body, which the analysis infers, and the converter writes no inferred type",
                    String::from_utf8_lossy(name)
                ));
                return;
            }
            let returned =
                declared.return_type_metadata.as_ref().map_or_else(get_mixed, |metadata| metadata.type_union.clone());
            let Some(written) = self.type_text(&returned, method.name.span) else {
                return;
            };

            let is_private = method.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Private(_)));
            let is_final = method.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Final(_)));
            let is_abstract = method.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Abstract(_)));
            let overrides =
                metadata.overridden_method_ids.get(&mago_word::ascii_lowercase_word(name)).is_some_and(|parents| {
                    parents.keys().any(|parent| {
                        self.input
                            .codebase
                            .get_class_like(parent.as_bytes())
                            .is_some_and(|parent| !parent.kind.is_interface())
                    })
                });
            let opening = if overrides {
                "override "
            } else if !class_is_final && !is_private && !is_final && !is_abstract {
                "virtual "
            } else {
                ""
            };
            self.edits.replace(function.span, format!("{opening}{written}"));
            if let Some(hint) = &method.return_type_hint {
                self.edits.delete(method.parameter_list.right_parenthesis.end.offset, hint.span().end.offset);
            }
        }

        for (index, parameter) in method.parameter_list.parameters.iter().enumerate() {
            let metadata = declared.parameters.get(index).and_then(|parameter| parameter.type_metadata.clone());
            self.parameter(parameter, metadata.map(|metadata| metadata.type_union));
        }

        self.returning = (!is_constructor).then(|| {
            declared.return_type_metadata.as_ref().map_or_else(get_mixed, |metadata| metadata.type_union.clone())
        });
        match &method.body {
            MethodBody::Concrete(block) => {
                for statement in block.statements.iter().skip(skipped) {
                    self.statement(statement);
                }
            }
            MethodBody::Abstract(_) => {}
            MethodBody::Expression(_) => self.refuse(method.body.span(), "an expression body is PHP# only"),
        }
        self.returning = None;
        self.docblock(method.span());
    }

    /// Writes `: super(…)` after a subclass constructor's parameters, which PHP# writes on every one (spec section 9),
    /// and gives how many of the body's statements it took: the `parent::__construct(…)` call the body starts with, whose
    /// arguments it takes, or none when no class above `class` declares a constructor, for `: super()`. `None` when PHP
    /// runs the parent's constructor later or never, which `: super(…)` would run first.
    fn super_call(&mut self, method: &Method<'_>, class: &[u8]) -> Option<usize> {
        let MethodBody::Concrete(block) = &method.body else {
            return Some(0);
        };
        let after = method.parameter_list.right_parenthesis.end.offset;
        let first = block.statements.first();
        if let Some(statement) = first
            && let Some(call) = parent_call(statement)
        {
            self.arguments(&call.argument_list);
            let list = call.argument_list.span();
            let arguments = self.edits.render(self.source, list.start.offset, list.end.offset);
            self.edits.insert(after, format!(" : super{arguments}"));
            let (start, end) = self.lines_of(statement.span());
            self.edits.delete(start, end);
            if let Some(later) = block.statements.iter().skip(1).find(|statement| parent_call(statement).is_some()) {
                self.refuse(
                    later.span(),
                    "a second call of the parent's constructor has no PHP# form (spec section 9)",
                );
                return None;
            }

            return Some(1);
        }
        if let Some(later) = block.statements.iter().find(|statement| parent_call(statement).is_some()) {
            self.refuse(
                later.span(),
                "the parent's constructor runs after other statements here, and `: super(…)` runs it first (spec section 9)",
            );
            return None;
        }
        if self.input.codebase.get_parent_constructor(class).is_some() {
            self.refuse(
                method.name.span,
                "PHP never runs the parent's constructor here, and PHP# runs it through `: super(…)` (spec section 9)",
            );
            return None;
        }
        self.edits.insert(after, " : super()");

        Some(0)
    }

    fn parameter(&mut self, parameter: &FunctionLikeParameter<'_>, declared: Option<TUnion>) {
        let name = String::from_utf8_lossy(parameter.variable.name).into_owned();
        if parameter.ampersand.is_some() {
            self.refuse(
                parameter.span(),
                format!(
                    "a PHP# parameter is never taken by reference, so `{name}` can't be: return the new value instead"
                ),
            );
            return;
        }
        if parameter.hooks.is_some() {
            self.refuse(parameter.span(), "a parameter with property hooks is not converted yet");
            return;
        }

        self.attributes(&parameter.attribute_lists);
        let promoted = parameter.is_promoted_property();
        // A promoted parameter declares one type for the parameter and its property, and the analysis keeps the
        // property's, which a refinement may have given.
        let declared = if promoted {
            self.class
                .as_ref()
                .and_then(|class| class.metadata.properties.get(&mago_word::word(parameter.variable.name)))
                .and_then(|property| property.type_metadata.as_ref())
                .map(|metadata| metadata.type_union.clone())
                .or(declared)
        } else {
            declared
        };
        let union = declared.unwrap_or_else(get_mixed);
        let Some(written) = self.type_text(&union, parameter.variable.span) else {
            return;
        };

        match &parameter.hint {
            Some(hint) => self.edits.replace(hint.span(), written),
            None => {
                let at = parameter.ellipsis.unwrap_or(parameter.variable.span).start.offset;
                self.edits.insert(at, format!("{written} "));
            }
        }
        self.edits.replace(parameter.variable.span, Self::variable_name(parameter.variable.name));

        if promoted {
            let readonly = parameter.modifiers.iter().find(|modifier| matches!(modifier, Modifier::Readonly(_)));
            if let Some(readonly) = readonly {
                let end = self.skip_whitespace(readonly.span().end.offset);
                self.edits.delete(readonly.span().start.offset, end);
            }
            let is_public = parameter.modifiers.iter().any(|modifier| matches!(modifier, Modifier::Public(_)));
            let accessors = match (is_public, readonly.is_some()) {
                (_, true) => Some(" { get; }"),
                (true, false) => Some(" { get; set; }"),
                (false, false) => None,
            };
            if let Some(accessors) = accessors {
                self.edits.insert(parameter.variable.span.end.offset, accessors);
            }
        }

        if let Some(default) = &parameter.default_value {
            self.expression(default.value, Some(&union));
        }
    }
}

/// The `parent::__construct(…)` call `statement` is, when it is one.
fn parent_call<'statement, 'arena>(
    statement: &'statement Statement<'arena>,
) -> Option<&'statement StaticMethodCall<'arena>> {
    let Statement::Expression(statement) = statement else {
        return None;
    };
    let Expression::Call(Call::StaticMethod(call)) = statement.expression else {
        return None;
    };
    let is_constructor = matches!(&call.method, ClassLikeMemberSelector::Identifier(name) if name.value.eq_ignore_ascii_case(b"__construct"));

    (matches!(call.class, Expression::Parent(_)) && is_constructor).then_some(call)
}
