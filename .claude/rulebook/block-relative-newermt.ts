import { defineRule } from '@cmodjs/rulebook'

export default defineRule({
  description: "Don't give macOS find a relative time.",
  message:
    "macOS find doesn't parse a relative -newermt such as '-2 hours', so every file reads as old. Use -mmin -120 for files changed in the last two hours.",
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        if (command.program !== 'find') return
        const at = command.args.indexOf('-newermt')
        if (at !== -1 && /^-|ago|hour|minute|day/.test(command.args[at + 1] ?? '')) context.report({ node: command })
      },
    }
  },
})
