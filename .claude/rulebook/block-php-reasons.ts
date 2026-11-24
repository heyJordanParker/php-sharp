import { defineRule } from '@cmodjs/rulebook'

const PHP_REASON = /"[^"]*\bPHP\b(?!#)[^"]*"/

export default defineRule({
  description: "Don't give PHP as the reason in a compiler message.",
  message:
    'PHP# owns its engine and compiler, so PHP is never the reason a program is refused. State the PHP# rule the program breaks, or lower around the PHP name so the rule goes away.',
  files: ['sharp/mago/crates/*/src/**/*.rs'],
  create(context) {
    context.sourceCode.lines.forEach((line, at) => {
      if (PHP_REASON.test(line)) context.report({ loc: { start: { line: at + 1, column: 0 } } })
    })
    return {}
  },
})
