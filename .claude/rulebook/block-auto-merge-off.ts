import { defineRule } from '@cmodjs/rulebook'

export default defineRule({
  description: "Don't turn off a pull request's auto-merge.",
  message:
    'Every stack pull request keeps auto-merge, so the stack lands the moment its top run passes. A commit that fails a check or needs a measurement is reverted on top of the stack by its lane (SHIP.md).',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        if (command.program !== 'gh') return
        const [group, verb] = command.args
        const disable = group === 'pr' && verb === 'merge' && command.args.includes('--disable-auto')
        const api = group === 'api' && command.args.some((arg) => /disablePullRequestAutoMerge/.test(arg))
        if (disable || api) context.report({ node: command })
      },
    }
  },
})
