import { defineRule } from '@cmodjs/rulebook'

// The intake gate runs on Edit and Write, and checks one entry in progress and every box before a brief. A script that
// writes intake.md skips it: on 11-24 the maintainer added entries with python and sed, and a second entry in progress
// went in unchecked.
export default defineRule({
  description: "Don't write the intake file from a shell command.",
  message: 'Change ~/.claude/orchestrate/php-sharp/intake.md with Edit or Write, so the intake gate checks the change.',
  files: ['@command'],
  create(context) {
    const intake = (text: string) => text.includes('orchestrate/php-sharp/intake.md')
    const shell = context.sourceCode.ast as { writes?: string[] }
    if ((shell.writes ?? []).some(intake)) context.report({ node: context.sourceCode.ast })
    return {
      Command(command) {
        if (!intake(context.sourceCode.text)) return
        const scripted = ['python', 'python3', 'perl'].includes(command.program)
        const inPlace = command.program === 'sed' && command.args.some((arg) => arg.startsWith('-i'))
        if (scripted || inPlace || command.program === 'tee') context.report({ node: command })
      },
    }
  },
})
