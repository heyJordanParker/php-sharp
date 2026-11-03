//! Statements and expressions: `$` leaves each name, `->` and `::` become `.`, and each construct whose PHP# form is not
//! a token's rewrite builds its text from its children's, rendered with their own edits.

use mago_codex::ttype::SharpType;
use mago_codex::ttype::atomic::TAtomic;
use mago_codex::ttype::get_sharp_type;
use mago_codex::ttype::union::TUnion;
use mago_span::HasSpan;
use mago_syntax::cst::Access;
use mago_syntax::cst::Argument;
use mago_syntax::cst::ArgumentList;
use mago_syntax::cst::ArrayElement;
use mago_syntax::cst::Assignment;
use mago_syntax::cst::AssignmentOperator;
use mago_syntax::cst::Binary;
use mago_syntax::cst::BinaryOperator;
use mago_syntax::cst::Block;
use mago_syntax::cst::Call;
use mago_syntax::cst::ClassLikeConstantSelector;
use mago_syntax::cst::ClassLikeMemberSelector;
use mago_syntax::cst::Expression;
use mago_syntax::cst::FunctionCall;
use mago_syntax::cst::IfBody;
use mago_syntax::cst::Literal;
use mago_syntax::cst::LiteralStringKind;
use mago_syntax::cst::Statement;
use mago_syntax::cst::UnaryPrefixOperator;
use mago_syntax::cst::Variable;

use crate::Converter;
use crate::library::Form;
use crate::library::Receiver;
use crate::library::Slot;
use crate::library::literal_value;
use crate::types::type_text;

impl Converter<'_, '_> {
    pub(crate) fn block(&mut self, block: &Block<'_>) {
        for statement in &block.statements {
            self.statement(statement);
        }
    }

    fn statement(&mut self, statement: &Statement<'_>) {
        match statement {
            Statement::Expression(statement) => self.expression(statement.expression, None),
            Statement::Return(r#return) => {
                if let Some(value) = r#return.value {
                    self.expression(value, None);
                }
            }
            Statement::Block(block) => self.block(block),
            Statement::If(r#if) => {
                self.condition(r#if.condition);
                let IfBody::Statement(body) = &r#if.body else {
                    self.refuse(r#if.span(), "an `if` with `:` and `endif` is not converted yet");
                    return;
                };
                self.braced(body.statement);
                for clause in &body.else_if_clauses {
                    self.edits.replace(clause.elseif.span, "else if");
                    self.condition(clause.condition);
                    self.braced(clause.statement);
                }
                if let Some(clause) = &body.else_clause {
                    self.braced(clause.statement);
                }
            }
            _ => self.refuse(statement.span(), "this statement is not converted yet"),
        }
    }

    /// The body of an `if`, `else` or loop, which PHP# writes in braces (spec section 17).
    fn braced(&mut self, statement: &Statement<'_>) {
        match statement {
            Statement::Block(block) => self.block(block),
            _ => self.refuse(statement.span(), "a body without braces is not converted yet"),
        }
    }

    /// A condition, which PHP# takes only as a `bool` (spec section 21).
    fn condition(&mut self, condition: &Expression<'_>) {
        if !self.is_bool(condition) {
            self.refuse(
                condition.span(),
                "a condition that is no `bool` reads PHP's truthiness, which is not converted yet",
            );
            return;
        }

        self.expression(condition, None);
    }

    fn type_of(&self, expression: &Expression<'_>) -> Option<&TUnion> {
        self.input.artifacts.get_expression_type(expression)
    }

    fn is_bool(&self, expression: &Expression<'_>) -> bool {
        self.type_of(expression).is_some_and(|union| {
            !union.types.is_empty()
                && union.types.iter().all(|atomic| matches!(get_sharp_type(atomic), Some(SharpType::Bool)))
        })
    }

    fn is_string(&self, expression: &Expression<'_>) -> bool {
        self.type_of(expression).is_some_and(|union| {
            !union.types.is_empty()
                && union.types.iter().all(|atomic| matches!(get_sharp_type(atomic), Some(SharpType::String)))
        })
    }

    fn render(&mut self, expression: &Expression<'_>) -> String {
        let span = expression.span();
        self.edits.render(self.source, span.start.offset, span.end.offset)
    }

    /// `expression` in PHP#. `expected` is the type of the place the value goes, when a literal's PHP# form depends on
    /// it, as `[]` is an empty `Map`, `[:]`, where a `Map` is declared (spec section 12).
    pub(crate) fn expression(&mut self, expression: &Expression<'_>, expected: Option<&TUnion>) {
        match expression {
            Expression::Variable(Variable::Direct(variable)) => {
                self.edits.replace(variable.span, Self::variable_name(variable.name));
            }
            Expression::Variable(_) => {
                self.refuse(expression.span(), "a variable named by a variable is removed (spec section 2)");
            }
            Expression::Parenthesized(parenthesized) => self.expression(parenthesized.expression, expected),
            Expression::Literal(literal) => self.literal(literal),
            Expression::Identifier(identifier) => {
                if identifier.value().contains(&b'\\') {
                    self.refuse(
                        identifier.span(),
                        "a qualified class name in code is not converted yet: import the class",
                    );
                }
            }
            Expression::Self_(keyword) => {
                let name = self.class.as_ref().map(|class| class.name.clone()).unwrap_or_default();
                self.edits.replace(keyword.span, name);
            }
            Expression::Static(keyword) => self.edits.replace(keyword.span, "Self"),
            Expression::Parent(keyword) => self.edits.replace(keyword.span, "super"),
            Expression::Access(access) => self.access(expression, access),
            Expression::Call(call) => self.call(call),
            Expression::Instantiation(instantiation) => {
                self.expression(instantiation.class, None);
                if instantiation.type_arguments.is_none()
                    && let Expression::Identifier(identifier) = instantiation.class
                    && let Some(class) = self.input.codebase.get_class_like(self.input.names.get(identifier))
                    && !class.template_types.is_empty()
                {
                    // `new` names a generic class's type arguments (spec section 11): the ones the analysis gives the
                    // object, which the checker checks, as it checks every expression.
                    let arguments = self.type_of(expression).and_then(|union| match union.types.as_ref() {
                        [TAtomic::Object(object)] => object.get_type_parameters().map(<[TUnion]>::to_vec),
                        _ => None,
                    });
                    match arguments {
                        Some(arguments) => {
                            let mut written = Vec::new();
                            for argument in &arguments {
                                match type_text(argument, self.input.codebase, &mut self.imports) {
                                    Ok(text) => written.push(text),
                                    Err(reason) => return self.refuse(expression.span(), reason),
                                }
                            }
                            self.edits.insert(identifier.span().end.offset, format!("<{}>", written.join(", ")));
                        }
                        None => self.refuse(expression.span(), "the analysis gives this object no type arguments"),
                    }
                }
                if let Some(arguments) = &instantiation.argument_list {
                    self.arguments(arguments);
                }
            }
            Expression::Binary(binary) => self.binary(expression, binary),
            Expression::UnaryPrefix(unary) => {
                match &unary.operator {
                    UnaryPrefixOperator::ErrorControl(_) => {
                        return self.refuse(expression.span(), "the `@` silence operator has no PHP# form yet");
                    }
                    UnaryPrefixOperator::Reference(_) => {
                        return self.refuse(expression.span(), "a reference has no PHP# form (spec section 14.3)");
                    }
                    UnaryPrefixOperator::BoolCast(..)
                    | UnaryPrefixOperator::BooleanCast(..)
                    | UnaryPrefixOperator::ArrayCast(..)
                    | UnaryPrefixOperator::ObjectCast(..)
                    | UnaryPrefixOperator::UnsetCast(..)
                    | UnaryPrefixOperator::IntegerCast(..)
                    | UnaryPrefixOperator::DoubleCast(..)
                    | UnaryPrefixOperator::RealCast(..)
                    | UnaryPrefixOperator::BinaryCast(..)
                    | UnaryPrefixOperator::VoidCast(..) => {
                        return self.refuse(expression.span(), "this cast has no PHP# form (spec section 24)");
                    }
                    UnaryPrefixOperator::Not(_) if !self.is_bool(unary.operand) => {
                        return self.refuse(expression.span(), "`!` on a value that is no `bool` is not converted yet");
                    }
                    _ => {}
                }
                self.expression(unary.operand, None);
            }
            Expression::UnaryPostfix(unary) => self.expression(unary.operand, None),
            Expression::Assignment(assignment) => self.assignment(expression, assignment),
            Expression::Array(array) => {
                let is_map = expected.is_some_and(|union| {
                    union.types.iter().any(|atomic| matches!(get_sharp_type(atomic), Some(SharpType::Map(..))))
                });
                if array.elements.is_empty() {
                    if is_map {
                        self.edits.replace(expression.span(), "[:]");
                    }
                    return;
                }
                for element in &array.elements {
                    match element {
                        ArrayElement::KeyValue(element) => {
                            self.expression(element.key, None);
                            self.edits.replace(element.double_arrow, ":");
                            self.expression(element.value, None);
                        }
                        ArrayElement::Value(element) => self.expression(element.value, None),
                        ArrayElement::Variadic(element) => self.expression(element.value, None),
                        ArrayElement::Missing(_) => self.refuse(element.span(), "a skipped element has no PHP# form"),
                    }
                }
            }
            Expression::ArrayAccess(access) => {
                self.expression(access.array, None);
                self.expression(access.index, None);
            }
            Expression::Conditional(conditional) => {
                let Some(then) = conditional.then else {
                    return self.refuse(expression.span(), "`?:` has no PHP# form: `??` covers null (spec section 21)");
                };
                self.condition(conditional.condition);
                self.expression(then, expected);
                self.expression(conditional.r#else, expected);
            }
            Expression::Throw(throw) => self.expression(throw.exception, None),
            _ => self.refuse(expression.span(), "this expression is not converted yet"),
        }
    }

    fn literal(&mut self, literal: &Literal<'_>) {
        match literal {
            Literal::True(keyword) => self.edits.replace(keyword.span, "true"),
            Literal::False(keyword) => self.edits.replace(keyword.span, "false"),
            Literal::Null(keyword) => self.edits.replace(keyword.span, "null"),
            Literal::String(string)
                if string.kind == LiteralStringKind::DoubleQuoted && string.raw.contains(&b'\\') =>
            {
                self.refuse(string.span, "a double-quoted string with an escape is not converted yet");
            }
            Literal::String(_) | Literal::Integer(_) | Literal::Float(_) => {}
        }
    }

    fn access(&mut self, expression: &Expression<'_>, access: &Access<'_>) {
        match access {
            Access::Property(access) => {
                self.expression(access.object, None);
                self.edits.replace(access.arrow, ".");
                self.member(&access.property);
            }
            Access::NullSafeProperty(access) => {
                self.expression(access.object, None);
                self.edits.replace(access.question_mark_arrow, "?.");
                self.member(&access.property);
            }
            Access::StaticProperty(access) => {
                self.expression(access.class, None);
                self.edits.replace(access.double_colon, ".");
                match &access.property {
                    Variable::Direct(variable) => self.edits.replace(variable.span, Self::variable_name(variable.name)),
                    _ => self
                        .refuse(access.property.span(), "a property named by a variable has no form (spec section 25)"),
                }
            }
            Access::ClassConstant(access) => {
                let ClassLikeConstantSelector::Identifier(constant) = &access.constant else {
                    return self.refuse(expression.span(), "a constant named by an expression has no form");
                };
                self.expression(access.class, None);
                if constant.value.eq_ignore_ascii_case(b"class") {
                    // `X::class` is the class value `typeof(X)` (spec section 25).
                    let class = self.render(access.class);
                    self.edits.replace(expression.span(), format!("typeof({class})"));
                } else {
                    self.edits.replace(access.double_colon, ".");
                }
            }
        }
    }

    fn member(&mut self, selector: &ClassLikeMemberSelector<'_>) {
        if !matches!(selector, ClassLikeMemberSelector::Identifier(_)) {
            self.refuse(selector.span(), "a member named by a variable has no PHP# form");
        }
    }

    fn arguments(&mut self, arguments: &ArgumentList<'_>) {
        for argument in &arguments.arguments {
            match argument {
                Argument::Positional(argument) => self.expression(argument.value, None),
                Argument::Named(argument) => self.expression(argument.value, None),
            }
        }
    }

    fn call(&mut self, call: &Call<'_>) {
        match call {
            Call::Method(call) => {
                self.expression(call.object, None);
                self.edits.replace(call.arrow, ".");
                self.member(&call.method);
                self.arguments(&call.argument_list);
            }
            Call::NullSafeMethod(call) => {
                self.expression(call.object, None);
                self.edits.replace(call.question_mark_arrow, "?.");
                self.member(&call.method);
                self.arguments(&call.argument_list);
            }
            Call::StaticMethod(call) => {
                self.expression(call.class, None);
                self.edits.replace(call.double_colon, ".");
                self.member(&call.method);
                self.arguments(&call.argument_list);
            }
            Call::Function(call) => self.function_call(call),
            Call::NullSafeFunction(_) => self.refuse(call.span(), "`?.(…)` on a function is not converted yet"),
        }
    }

    /// A bare PHP function call, which PHP# writes as the form a declaration gives it (see `library`), or refuses.
    fn function_call(&mut self, call: &FunctionCall<'_>) {
        let span = call.span();
        let Expression::Identifier(identifier) = call.function else {
            return self.refuse(span, "a call of a value is not converted yet");
        };
        let name = identifier.value().strip_prefix(b"\\").unwrap_or(identifier.value());
        if name.contains(&b'\\') {
            return self.refuse(span, "a call of a library's namespaced function is not converted yet");
        }
        let display = String::from_utf8_lossy(name).into_owned();
        let mut values = Vec::new();
        for argument in &call.argument_list.arguments {
            match argument {
                Argument::Positional(argument) if argument.ellipsis.is_none() => values.push(argument.value),
                _ => {
                    return self
                        .refuse(span, format!("`{display}` with a named or spread argument is not converted yet"));
                }
            }
        }

        for form in self.input.library.forms(name, self.input.codebase) {
            match form {
                Form::TypeTest(target) if values.len() == 1 => {
                    self.expression(values[0], None);
                    let value = self.render(values[0]);
                    let text = if target.is_null() {
                        format!("{value} == null")
                    } else {
                        match type_text(&target, self.input.codebase, &mut self.imports) {
                            Ok(written) => format!("{value} is {written}"),
                            Err(reason) => {
                                return self
                                    .refuse(span, format!("`{display}` tests a type PHP# can't write: {reason}"));
                            }
                        }
                    };
                    return self.edits.replace(span, text);
                }
                Form::Member { class, member, receiver, arguments } if arguments.len() == values.len() => {
                    let fits = arguments.iter().zip(&values).all(|(slot, value)| match slot {
                        Slot::Constant(constant) => match value {
                            Expression::Literal(literal) => {
                                literal_value(literal).as_deref() == Some(constant.as_slice())
                            }
                            Expression::ConstantAccess(access) => {
                                access.name.value().strip_prefix(b"\\").unwrap_or(access.name.value())
                                    == constant.as_slice()
                            }
                            _ => false,
                        },
                        Slot::Value(_) => true,
                    });
                    let receiver_fits = receiver.as_ref().is_none_or(|receiver| {
                        arguments
                            .iter()
                            .zip(&values)
                            .any(|(slot, value)| *slot == Slot::Value(0) && self.receives(value, receiver))
                    });
                    if !fits || !receiver_fits {
                        continue;
                    }

                    let mut slots: Vec<(usize, String)> = Vec::new();
                    for (slot, value) in arguments.iter().zip(&values) {
                        if let Slot::Value(index) = slot {
                            self.expression(value, None);
                            let rendered = self.render(value);
                            slots.push((
                                *index,
                                if *index == 0 && receiver.is_some() && !is_postfix(value) {
                                    format!("({rendered})")
                                } else {
                                    rendered
                                },
                            ));
                        }
                    }
                    slots.sort_by_key(|(index, _)| *index);
                    let text = if receiver.is_some() {
                        let others: Vec<&str> = slots.iter().skip(1).map(|(_, text)| text.as_str()).collect();
                        format!("{}.{member}({})", slots[0].1, others.join(", "))
                    } else {
                        let class = match self.imports.name_of(&class) {
                            Ok(class) => class,
                            Err(reason) => return self.refuse(span, reason),
                        };
                        let all: Vec<&str> = slots.iter().map(|(_, text)| text.as_str()).collect();
                        format!("{class}.{member}({})", all.join(", "))
                    };
                    return self.edits.replace(span, text);
                }
                _ => {}
            }
        }

        self.refuse(span, format!(
            "`{display}` has no PHP# form: no standard library member runs it, and the prelude declares no type test for it"
        ));
    }

    /// Whether `value` is of the kind a standard library extension's receiver is.
    fn receives(&self, value: &Expression<'_>, receiver: &Receiver) -> bool {
        let Some(union) = self.type_of(value) else {
            return false;
        };
        !union.types.is_empty()
            && union.types.iter().all(|atomic| match (get_sharp_type(atomic), receiver) {
                (Some(SharpType::String), Receiver::String)
                | (Some(SharpType::Int), Receiver::Int)
                | (Some(SharpType::Float), Receiver::Float)
                | (Some(SharpType::List(_)), Receiver::List)
                | (Some(SharpType::Map(..)), Receiver::Map)
                | (Some(SharpType::Set(_)), Receiver::Set) => true,
                (Some(SharpType::List(element)), Receiver::ListOfStrings) => {
                    element.types.iter().all(|atomic| matches!(get_sharp_type(atomic), Some(SharpType::String)))
                }
                _ => false,
            })
    }

    fn binary(&mut self, expression: &Expression<'_>, binary: &Binary<'_>) {
        match &binary.operator {
            BinaryOperator::StringConcat(_) => return self.concatenation(expression),
            BinaryOperator::LowAnd(_) | BinaryOperator::LowOr(_) | BinaryOperator::LowXor(_) => {
                return self.refuse(expression.span(), "`and`, `or` and `xor` are removed (spec section 21)");
            }
            BinaryOperator::Identical(span) | BinaryOperator::NotIdentical(span) => {
                // PHP#'s `===` asks for the same object (spec section 19), and its `==` compares other values strictly.
                let is_object =
                    |union: Option<&TUnion>| union.is_some_and(|union| union.types.iter().all(TAtomic::is_object_type));
                if !is_object(self.type_of(binary.lhs)) || !is_object(self.type_of(binary.rhs)) {
                    let equal = matches!(binary.operator, BinaryOperator::Identical(_));
                    self.edits.replace(*span, if equal { "==" } else { "!=" });
                }
            }
            BinaryOperator::Equal(_) | BinaryOperator::NotEqual(_) | BinaryOperator::AngledNotEqual(_) => {
                return self.refuse(expression.span(), "PHP's loose `==` is not converted yet");
            }
            BinaryOperator::Instanceof(keyword) => self.edits.replace(keyword.span, "is"),
            _ => {}
        }
        self.expression(binary.lhs, None);
        self.expression(binary.rhs, None);
    }

    /// A chain of `.`: `+` between strings (spec section 18), or a template when a part is a number.
    fn concatenation(&mut self, expression: &Expression<'_>) {
        let mut parts = Vec::new();
        flatten_concatenation(expression, &mut parts);

        if parts.iter().all(|part| self.is_string(part)) {
            let mut operators = Vec::new();
            collect_concatenation_operators(expression, &mut operators);
            for span in operators {
                self.edits.replace(span, "+");
            }
            for part in parts {
                self.expression(part, None);
            }
            return;
        }

        let mut template = String::from("`");
        for part in parts {
            match part {
                Expression::Literal(Literal::String(string))
                    if string.kind == LiteralStringKind::SingleQuoted
                        && string
                            .value
                            .is_some_and(|value| !value.iter().any(|byte| matches!(byte, b'`' | b'$' | b'\\'))) =>
                {
                    template.push_str(&String::from_utf8_lossy(string.value.unwrap_or_default()));
                }
                _ => {
                    let shown = self.type_of(part).is_some_and(|union| {
                        !union.types.is_empty()
                            && union.types.iter().all(|atomic| {
                                matches!(
                                    get_sharp_type(atomic),
                                    Some(SharpType::Int | SharpType::String | SharpType::Float)
                                )
                            })
                    });
                    if !shown {
                        return self.refuse(
                            part.span(),
                            "a template shows only `int`, `float` and `string` here (spec section 18)",
                        );
                    }
                    self.expression(part, None);
                    let rendered = self.render(part);
                    template.push_str(&format!("${{{rendered}}}"));
                }
            }
        }
        template.push('`');
        self.edits.replace(expression.span(), template);
    }

    fn assignment(&mut self, expression: &Expression<'_>, assignment: &Assignment<'_>) {
        if let Expression::ArrayAppend(append) = assignment.lhs {
            if !assignment.operator.is_assign() {
                return self.refuse(expression.span(), "a compound write to `[]` has no PHP# form");
            }
            // `$list[] = $value` appends, which PHP# writes `list.add(value)` (spec section 12).
            self.expression(append.array, None);
            self.expression(assignment.rhs, None);
            let list = self.render(append.array);
            let value = self.render(assignment.rhs);
            return self.edits.replace(expression.span(), format!("{list}.add({value})"));
        }
        if matches!(assignment.lhs, Expression::Variable(Variable::Direct(variable)) if variable.name != b"$this") {
            return self.refuse(expression.span(), "a local variable is not converted yet: it needs `let` or `const`");
        }
        if let AssignmentOperator::Concat(span) = assignment.operator {
            if !self.is_string(assignment.rhs) {
                return self.refuse(expression.span(), "`.=` of a value that is no string is not converted yet");
            }
            self.edits.replace(span, "+=");
        }

        let expected = self.type_of(assignment.lhs).cloned();
        self.expression(assignment.lhs, None);
        self.expression(assignment.rhs, expected.as_ref());
    }
}

fn flatten_concatenation<'expression, 'arena>(
    expression: &'expression Expression<'arena>,
    parts: &mut Vec<&'expression Expression<'arena>>,
) {
    match expression {
        Expression::Binary(Binary { lhs, operator: BinaryOperator::StringConcat(_), rhs }) => {
            flatten_concatenation(lhs, parts);
            flatten_concatenation(rhs, parts);
        }
        _ => parts.push(expression),
    }
}

fn collect_concatenation_operators(expression: &Expression<'_>, operators: &mut Vec<mago_span::Span>) {
    if let Expression::Binary(Binary { lhs, operator: BinaryOperator::StringConcat(span), rhs }) = expression {
        collect_concatenation_operators(lhs, operators);
        operators.push(*span);
        collect_concatenation_operators(rhs, operators);
    }
}

/// Whether `.` after `expression` reads a member of the whole of it, with no parentheses.
fn is_postfix(expression: &Expression<'_>) -> bool {
    matches!(
        expression,
        Expression::Variable(_)
            | Expression::Access(_)
            | Expression::Call(_)
            | Expression::ArrayAccess(_)
            | Expression::Parenthesized(_)
            | Expression::Literal(_)
            | Expression::Array(_)
    )
}
