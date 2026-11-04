//! Statements and expressions: `$` leaves each name, `->` and `::` become `.`, and each construct whose PHP# form is not
//! a token's rewrite builds its text from its children's, rendered with their own edits.

use mago_codex::metadata::function_like::FunctionLikeMetadata;
use mago_codex::ttype::SharpType;
use mago_codex::ttype::TType;
use mago_codex::ttype::atomic::TAtomic;
use mago_codex::ttype::comparator::ComparisonResult;
use mago_codex::ttype::comparator::union_comparator::is_contained_by;
use mago_codex::ttype::get_sharp_type;
use mago_codex::ttype::union::TUnion;
use mago_sharp_bridge::PhpCallArgument;
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
use crate::library::Member;
use crate::library::Shape;
use crate::library::differs;
use crate::library::short;
use crate::library::type_test;
use crate::types::type_text;

impl<'input> Converter<'input, '_> {
    pub(crate) fn block(&mut self, block: &Block<'_>) {
        for statement in &block.statements {
            self.statement(statement);
        }
    }

    pub(crate) fn statement(&mut self, statement: &Statement<'_>) {
        match statement {
            Statement::Expression(statement) => self.expression(statement.expression, None),
            Statement::Return(r#return) => {
                if let Some(value) = r#return.value {
                    let returning = self.returning.clone();
                    self.expression(value, returning.as_ref());
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
                    // `new` names a generic class's type arguments (spec section 11), and generics are reified, so an
                    // argument the analysis inferred would be a runtime `TypeError` when it is wrong. The arguments are
                    // the declared type's the value goes into, or the class's bounds, which plain PHP's `new` has.
                    let declared = expected.and_then(|union| {
                        let mut objects = union.types.iter().filter(|atomic| !atomic.is_null());
                        match (objects.next(), objects.next()) {
                            (Some(TAtomic::Object(object)), None)
                                if object.get_name().is_some_and(|name| {
                                    name.as_bytes().eq_ignore_ascii_case(class.name.as_bytes())
                                }) =>
                            {
                                object.get_type_parameters().map(<[TUnion]>::to_vec)
                            }
                            _ => None,
                        }
                    });
                    let arguments = declared.unwrap_or_else(|| {
                        class.template_types.values().map(|template| template.constraint.clone()).collect()
                    });
                    let mut written = Vec::new();
                    for argument in &arguments {
                        match type_text(argument, self.input.codebase, &mut self.imports) {
                            Ok(text) => written.push(text),
                            Err(reason) => return self.refuse(expression.span(), reason),
                        }
                    }
                    self.edits.insert(identifier.span().end.offset, format!("<{}>", written.join(", ")));
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
                        return self.refuse(expression.span(), "a reference has no PHP# form: PHP# has no references");
                    }
                    UnaryPrefixOperator::BoolCast(..)
                    | UnaryPrefixOperator::BooleanCast(..)
                    | UnaryPrefixOperator::ArrayCast(..)
                    | UnaryPrefixOperator::ObjectCast(..)
                    | UnaryPrefixOperator::UnsetCast(..)
                    | UnaryPrefixOperator::VoidCast(..) => {
                        return self.refuse(expression.span(), "this cast has no PHP# form (spec section 24)");
                    }
                    // PHP's cast aliases are its `(int)`, `(float)` and `(string)` (spec section 24).
                    UnaryPrefixOperator::IntegerCast(span, _) => self.edits.replace(*span, "(int)"),
                    UnaryPrefixOperator::DoubleCast(span, _) | UnaryPrefixOperator::RealCast(span, _) => {
                        self.edits.replace(*span, "(float)");
                    }
                    UnaryPrefixOperator::BinaryCast(span, _) => self.edits.replace(*span, "(string)"),
                    UnaryPrefixOperator::Not(_) if !self.is_bool(unary.operand) => {
                        return self.refuse(expression.span(), "`!` on a value that is no `bool` is not converted yet");
                    }
                    // `!is_string($x)` and `!$x instanceof A` are `x is not string` and `x is not A` (spec section 21).
                    UnaryPrefixOperator::Not(_) => {
                        if let Some((value, tested)) = self.test(unary.operand) {
                            return self.edits.replace(expression.span(), format!("{value} is not {tested}"));
                        }
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

    /// The value and the type `expression` tests, each converted, when it is `$x instanceof A`, or a call of the
    /// prelude's type test of a type PHP# writes, as `is_string($x)`.
    fn test(&mut self, expression: &Expression<'_>) -> Option<(String, String)> {
        match expression {
            Expression::Parenthesized(parenthesized) => self.test(parenthesized.expression),
            Expression::Binary(Binary { lhs, operator: BinaryOperator::Instanceof(_), rhs }) => {
                self.expression(lhs, None);
                self.expression(rhs, None);

                Some((self.render(lhs), self.render(rhs)))
            }
            Expression::Call(Call::Function(call)) => {
                let target = type_test(self.function_of(call)?)?;
                if target.is_null() {
                    return None;
                }
                let tested = type_text(target, self.input.codebase, &mut self.imports).ok()?;
                let [Argument::Positional(value)] = call.argument_list.arguments.as_slice() else {
                    return None;
                };
                if value.ellipsis.is_some() {
                    return None;
                }
                self.expression(value.value, None);

                Some((self.render(value.value), tested))
            }
            _ => None,
        }
    }

    /// The function a bare call runs: the one its resolved name names, or PHP's own of the name it writes, as PHP falls
    /// back to a function of the root namespace.
    fn function_of(&self, call: &FunctionCall<'_>) -> Option<&'input FunctionLikeMetadata> {
        let Expression::Identifier(identifier) = call.function else {
            return None;
        };
        let codebase = self.input.codebase;

        codebase.get_function(self.input.names.get(identifier)).or_else(|| {
            (!identifier.value().contains(&b'\\')).then(|| codebase.get_function(identifier.value())).flatten()
        })
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

    pub(crate) fn arguments(&mut self, arguments: &ArgumentList<'_>) {
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

    /// A bare PHP function call, as the form a declaration gives it (see `library`), or a refusal that names the
    /// function and why it has none.
    fn function_call(&mut self, call: &FunctionCall<'_>) {
        let span = call.span();
        if !matches!(call.function, Expression::Identifier(_)) {
            return self.refuse(span, "a call of a value is not converted yet");
        }
        let Some(function) = self.function_of(call) else {
            return self.refuse(span, "the analysis holds no function this call runs");
        };
        let name = function.original_name.as_str_lossy().into_owned();

        // A library's function is called through its namespace, and one in PHP's root namespace through the namespace
        // of the library's classes beside it (spec section 23).
        if !function.flags.is_built_in() {
            let namespace = match name.rsplit_once('\\') {
                Some((namespace, _)) => namespace.to_owned(),
                None => match self.input.codebase.folder_namespaces(function.span).first() {
                    Some(namespace) => namespace.as_str_lossy().into_owned(),
                    None => {
                        return self.refuse(span, format!(
                            "`{name}` is declared in PHP's root namespace beside no class of its library, so it has no namespace to be called through (spec section 23)"
                        ));
                    }
                },
            };
            self.arguments(&call.argument_list);
            let list = call.argument_list.span();
            let arguments = self.edits.render(self.source, list.start.offset, list.end.offset);
            let alias = self.imports.import(&namespace);
            let member = name.rsplit('\\').next().unwrap_or(&name);
            return self.edits.replace(span, format!("{alias}.{member}{arguments}"));
        }

        let mut values = Vec::new();
        for argument in &call.argument_list.arguments {
            match argument {
                Argument::Positional(argument) if argument.ellipsis.is_none() => values.push(argument.value),
                _ => {
                    return self.refuse(span, format!("`{name}` with a named or spread argument is not converted yet"));
                }
            }
        }
        if let Some(target) = type_test(function) {
            let [value] = values.as_slice() else {
                return self.refuse(span, format!("`{name}` takes one argument"));
            };
            let text = if target.is_null() {
                "null".to_owned()
            } else {
                match type_text(target, self.input.codebase, &mut self.imports) {
                    Ok(text) => text,
                    Err(reason) => {
                        return self.refuse(span, format!("`{name}` tests a type PHP# can't write: {reason}"));
                    }
                }
            };
            self.expression(value, None);
            let value = self.render(value);
            let test = if target.is_null() { format!("{value} == null") } else { format!("{value} is {text}") };
            return self.edits.replace(span, test);
        }

        let mut differences = Vec::new();
        for member in self.input.library.members(name.as_bytes()) {
            match self.fits(member, function, &values) {
                Ok(()) => return self.member_call(span, member, &values),
                Err(difference) => differences.push(difference),
            }
        }
        let reason = if differences.is_empty() {
            format!("no standard library member runs `{name}` with PHP's meaning (decision 083)")
        } else {
            differences.join(", and ")
        };
        self.refuse(span, format!("`{name}` has no PHP# form: {reason}"));
    }

    /// Whether the call of the PHP built-in `function` with `values` runs as `member` with PHP's meaning, or the
    /// difference.
    fn fits(&self, member: &Member, function: &FunctionLikeMetadata, values: &[&Expression<'_>]) -> Result<(), String> {
        let shown = format!("`{}.{}`", short(&member.class), member.name);
        let arguments = &member.call.arguments;
        if arguments.len() != values.len() {
            return Err(format!(
                "{shown} runs it with {} arguments, and this call passes {}",
                arguments.len(),
                values.len()
            ));
        }
        for (position, (argument, value)) in arguments.iter().zip(values).enumerate() {
            let fixed = match argument {
                PhpCallArgument::Slot(_) => continue,
                PhpCallArgument::Literal(text) | PhpCallArgument::Constant(text) => text,
            };
            let passed = self.text(value.span());
            let same = match value {
                Expression::Literal(_) => passed.eq_ignore_ascii_case(fixed),
                Expression::ConstantAccess(_) => passed.strip_prefix(b"\\").unwrap_or(passed) == fixed.as_slice(),
                _ => false,
            };
            if !same {
                return Err(format!(
                    "{shown} runs it with `{}` as argument {}, and this call passes `{}`",
                    String::from_utf8_lossy(fixed),
                    position + 1,
                    String::from_utf8_lossy(passed)
                ));
            }
        }
        if member.shape != Shape::Static
            && let Some((_, receiver)) =
                arguments.iter().zip(values).find(|(argument, _)| **argument == PhpCallArgument::Slot(0))
        {
            let method = self.input.codebase.get_method(member.class.as_bytes(), member.name.as_bytes());
            let takes = method.and_then(|method| method.parameters.first()?.type_metadata.as_ref());
            let fits = match (self.type_of(receiver), takes) {
                (Some(given), Some(takes)) => is_contained_by(
                    self.input.codebase,
                    given,
                    &takes.type_union,
                    false,
                    false,
                    false,
                    &mut ComparisonResult::new(),
                ),
                _ => false,
            };
            if !fits {
                return Err(format!("{shown} takes a receiver this call's argument doesn't fit"));
            }
        }
        if let Some(difference) = differs(member, function, self.input.codebase) {
            return Err(difference);
        }
        // PHP runs the arguments in the call's order, and PHP# in the member's, so an argument that moves runs nothing.
        let order: Vec<usize> = arguments
            .iter()
            .filter_map(|argument| match argument {
                PhpCallArgument::Slot(slot) => Some(*slot),
                _ => None,
            })
            .collect();
        if order.windows(2).any(|pair| pair[0] > pair[1])
            && let Some(position) = values.iter().position(|value| !is_plain_read(value))
        {
            return Err(format!(
                "{shown} runs the arguments in another order, and argument {} runs code",
                position + 1
            ));
        }

        Ok(())
    }

    /// The call of `member` that runs the call at `span`, with `values` in their slots.
    fn member_call(&mut self, span: mago_span::Span, member: &Member, values: &[&Expression<'_>]) {
        let mut slots: Vec<(usize, String)> = Vec::new();
        for (argument, value) in member.call.arguments.iter().zip(values) {
            if let PhpCallArgument::Slot(slot) = argument {
                self.expression(value, None);
                let rendered = self.render(value);
                let receiver = *slot == 0 && member.shape != Shape::Static;
                slots.push((*slot, if receiver && !is_postfix(value) { format!("({rendered})") } else { rendered }));
            }
        }
        slots.sort_by_key(|(slot, _)| *slot);
        let texts: Vec<&str> = slots.iter().map(|(_, text)| text.as_str()).collect();
        let text = match member.shape {
            Shape::Property => format!("{}.{}", texts[0], member.name),
            Shape::Method => format!("{}.{}({})", texts[0], member.name, texts[1..].join(", ")),
            Shape::Static => match self.imports.name_of(&member.class) {
                Ok(class) => format!("{class}.{}({})", member.name, texts.join(", ")),
                Err(reason) => return self.refuse(span, reason),
            },
        };
        self.edits.replace(span, text);
    }

    fn binary(&mut self, expression: &Expression<'_>, binary: &Binary<'_>) {
        match &binary.operator {
            BinaryOperator::StringConcat(_) => return self.concatenation(expression),
            BinaryOperator::LowAnd(_) | BinaryOperator::LowOr(_) | BinaryOperator::LowXor(_) => {
                return self.refuse(expression.span(), "`and`, `or` and `xor` are removed (spec section 21)");
            }
            BinaryOperator::Identical(span) | BinaryOperator::NotIdentical(span) => {
                match identity(self.type_of(binary.lhs), self.type_of(binary.rhs)) {
                    Ok(true) => {
                        let equal = matches!(binary.operator, BinaryOperator::Identical(_));
                        self.edits.replace(*span, if equal { "==" } else { "!=" });
                    }
                    Ok(false) => {}
                    Err(reason) => return self.refuse(expression.span(), reason),
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

        let expected = self.declared_type(assignment.lhs);
        self.expression(assignment.lhs, None);
        self.expression(assignment.rhs, expected.as_ref());
    }

    /// The declared type of the place `place` names, when it is a property of `this`: the type a value written there
    /// takes, which the analysis's type of the place, narrowed by the code before it, may not be.
    fn declared_type(&self, place: &Expression<'_>) -> Option<TUnion> {
        let Expression::Access(Access::Property(access)) = place else {
            return None;
        };
        let (Expression::Variable(Variable::Direct(object)), ClassLikeMemberSelector::Identifier(name)) =
            (access.object, &access.property)
        else {
            return None;
        };
        if object.name != b"$this" {
            return None;
        }
        let property = [b"$".as_slice(), name.value].concat();
        let metadata = self.class.as_ref()?.metadata.properties.get(&mago_word::word(&property))?;

        Some(metadata.type_metadata.as_ref()?.type_union.clone())
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

/// Whether reading `expression` runs no code: a variable, a literal, a constant, or a property read of one.
fn is_plain_read(expression: &Expression<'_>) -> bool {
    match expression {
        Expression::Variable(Variable::Direct(_)) | Expression::Literal(_) | Expression::ConstantAccess(_) => true,
        Expression::Parenthesized(parenthesized) => is_plain_read(parenthesized.expression),
        Expression::Access(Access::Property(access)) => is_plain_read(access.object),
        Expression::Access(Access::NullSafeProperty(access)) => is_plain_read(access.object),
        _ => false,
    }
}

/// What a value of `union` is to `===`.
#[derive(Debug, Clone, Copy, PartialEq, Eq)]
enum Kind {
    Null,
    Int,
    Float,
    String,
    Bool,
    Enum,
    /// It may hold an object, as a class, `object` or `mixed` does.
    Object,
    Collection,
    Other,
}

fn kinds(union: &TUnion) -> Vec<Kind> {
    let mut kinds: Vec<Kind> = union
        .types
        .iter()
        .map(|atomic| {
            if atomic.is_null() {
                return Kind::Null;
            }
            if atomic.is_mixed() {
                return Kind::Object;
            }
            match get_sharp_type(atomic) {
                Some(SharpType::Int) => Kind::Int,
                Some(SharpType::Float) => Kind::Float,
                Some(SharpType::String) => Kind::String,
                Some(SharpType::Bool) => Kind::Bool,
                Some(SharpType::Enum(_)) => Kind::Enum,
                Some(SharpType::Class(..) | SharpType::Object | SharpType::Parameter(_)) => Kind::Object,
                Some(SharpType::List(_) | SharpType::Map(..) | SharpType::Set(_) | SharpType::Iterable(_)) => {
                    Kind::Collection
                }
                _ => Kind::Other,
            }
        })
        .collect();
    kinds.sort_by_key(|kind| *kind as u8);
    kinds.dedup();

    kinds
}

/// Whether PHP's `===` between values of `lhs` and `rhs` is PHP#'s `==`, `Ok(true)`, stays `===`, `Ok(false)`, or has
/// no form. `==` gives `===`'s answer when both sides have one scalar type, both are enums, or one side is `null`.
/// `===` stays where a side may hold an object, since PHP#'s `===` asks for the same object (spec section 19).
fn identity(lhs: Option<&TUnion>, rhs: Option<&TUnion>) -> Result<bool, String> {
    let (Some(lhs), Some(rhs)) = (lhs, rhs) else {
        return Err("the analysis gives a side of this `===` no type".to_owned());
    };
    let (left, right) = (kinds(lhs), kinds(rhs));
    if left.contains(&Kind::Object) || right.contains(&Kind::Object) {
        return Ok(false);
    }
    if left.contains(&Kind::Collection) || right.contains(&Kind::Collection) {
        return Err(
            "PHP's `===` on arrays compares keys, order and types, and PHP#'s `==` on collections compares their elements (spec section 19)"
                .to_owned(),
        );
    }
    if left == [Kind::Null] || right == [Kind::Null] {
        return Ok(true);
    }
    let bare = |kinds: &[Kind]| kinds.iter().copied().filter(|kind| *kind != Kind::Null).collect::<Vec<_>>();
    match (bare(&left).as_slice(), bare(&right).as_slice()) {
        ([Kind::Int], [Kind::Float]) | ([Kind::Float], [Kind::Int]) => {
            Err("PHP's `1 === 1.0` is false, and PHP# compares an `int` with a `float` by value (spec section 12)"
                .to_owned())
        }
        ([a], [b]) if a == b && *a != Kind::Other => Ok(true),
        _ => Err(format!("`===` between `{}` and `{}` is not converted yet", lhs.get_id(), rhs.get_id())),
    }
}
