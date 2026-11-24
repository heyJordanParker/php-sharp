import { homedir } from 'node:os'
import { defineRule } from '@cmodjs/rulebook'

const CONSUMER = `${homedir()}/Developer/${['creator', 'income', 'blueprint'].join('-')}`
const FROZEN = '/private/tmp/sharp-bench/'

export default defineRule({
  description: "Don't read the consumer project's files.",
  message:
    "The consumer's code is never read: its feedback comes from its agent. The one command that reaches it is the maintainer's rsync of a frozen copy into /private/tmp/sharp-bench/.",
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        const words = [command.program, ...command.args].join(' ')
        if (!words.includes(CONSUMER)) return
        const freezing = command.program === 'rsync' && command.args.some((arg) => arg.startsWith(FROZEN))
        if (!freezing) context.report({ node: command })
      },
    }
  },
})
