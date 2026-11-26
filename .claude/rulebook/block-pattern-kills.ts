import { defineRule } from '@cmodjs/rulebook'

export default defineRule({
  description: "Don't stop a process found by a name pattern.",
  message:
    'Stop only a process you started, by the PID you recorded when you started it: `kill <pid>`. A pattern also matches other lanes\' builds and runs, which share this machine.',
  files: ['@command'],
  create(context) {
    const text = context.sourceCode.text
    const fedByPattern = /\b(pgrep|grep|pidof)\b/.test(text)
    return {
      Command(command) {
        if (command.program === 'pkill' || command.program === 'killall') context.report({ node: command })
        const kills = command.program === 'kill' || (command.program === 'xargs' && command.args.includes('kill'))
        if (kills && fedByPattern) context.report({ node: command })
      },
    }
  },
})
