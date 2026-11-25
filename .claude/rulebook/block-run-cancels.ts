import { defineRule } from '@cmodjs/rulebook'

const CANCEL_ENDPOINT = /actions\/runs\/\d+\/(force-)?cancel(\/|$)/

export default defineRule({
  description: "Don't cancel a CI run by hand.",
  message:
    'A run is superseded by the concurrency groups `sharp.yml` defines and stopped by its own `cancel-failed-run` step when a check fails. A run cancelled by hand throws away the Mac minutes it already ran: 9 hand cancels cost 135 of the one Mac runner\'s minutes on 11-23 and 11-24, and the commit still needs a run. Push the next commit and let the group replace the waiting run.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        if (command.program !== 'gh') return
        if (command.args[0] === 'run' && command.args[1] === 'cancel') context.report({ node: command })
        if (command.args[0] === 'api' && command.args.some((arg) => CANCEL_ENDPOINT.test(arg))) context.report({ node: command })
      },
    }
  },
})
