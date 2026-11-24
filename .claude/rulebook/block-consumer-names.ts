import { defineRule } from '@cmodjs/rulebook'

const NAMES = [String.raw`\b${'De'}${'nt'}\b`, ['creator', 'income', 'blueprint'].join('-'), String.raw`\b${'dn'}t (start|stop|test)\b`]
const CONSUMER = new RegExp(NAMES.join('|'), 'i')

export default defineRule({
  description: "Don't name the consumer project in the repository.",
  message:
    'The repository and its tracker are public: a file, a test, a benchmark, a commit or an issue comment never names the consumer project or its files. Rename the shape and keep the problem.',
  files: ['**'],
  create(context) {
    context.sourceCode.lines.forEach((line, at) => {
      if (CONSUMER.test(line)) context.report({ loc: { start: { line: at + 1, column: 0 } } })
    })
    return {}
  },
})
