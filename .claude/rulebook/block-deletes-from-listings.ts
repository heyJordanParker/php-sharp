import { defineRule } from '@cmodjs/rulebook'

export default defineRule({
  description: "Don't delete the paths a listing prints.",
  message:
    'Delete by literal path or shell glob, as `rm -r <path>`. `ls` prints columns and decorations, so its words reach `rm` as paths, and a name with a space splits in two.',
  files: ['@command'],
  create(context) {
    const listed = /(^|[|$(`]\s*)ls\b/.test(context.sourceCode.text)
    return {
      Command(command) {
        const deletes = command.program === 'rm' || (command.program === 'xargs' && command.args.includes('rm'))
        if (deletes && listed) context.report({ node: command })
      },
    }
  },
})
