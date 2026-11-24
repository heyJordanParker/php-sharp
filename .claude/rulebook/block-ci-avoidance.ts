import { defineRule } from '@cmodjs/rulebook'

const AVOIDANCE = /actions\/cache|cache\/(restore|save)|\bccache\b|\bsccache\b|passed-tree|allowed-skips|mago-cache|cache-from|cache-to/

export default defineRule({
  description: "Don't make CI faster by skipping, reusing or caching work.",
  message:
    'CI runs every suite on every run and builds from source: a cache, a reused result or a skip hides the cost the speed work has to cut. Make the work itself faster.',
  files: ['.github/**', 'sharp/bin/land', 'sharp/docker/**'],
  create(context) {
    context.sourceCode.lines.forEach((line, at) => {
      if (AVOIDANCE.test(line)) context.report({ loc: { start: { line: at + 1, column: 0 } } })
    })
    return {}
  },
})
