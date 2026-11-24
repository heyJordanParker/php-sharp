import { defineRule } from '@cmodjs/rulebook'

export default defineRule({
  description: "Don't rerun a CI run.",
  message:
    'A red check is read and its cause written on the issue: a defect is fixed in a new commit with a test that fails first, and a runner or network fault is a problem for the maintainer to triage. A rerun on the same commit hides both.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        if (command.program !== 'gh') return
        const [group, verb] = command.args
        const rerun = group === 'run' && verb === 'rerun'
        const api = group === 'api' && command.args.some((arg) => /\/actions\/(runs|jobs)\/[^/]+\/rerun/.test(arg))
        if (rerun || api) context.report({ node: command })
      },
    }
  },
})
