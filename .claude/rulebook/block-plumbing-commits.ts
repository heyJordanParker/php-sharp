import { defineRule } from '@cmodjs/rulebook'

const PLUMBING = new Set(['commit-tree', 'update-ref'])

export default defineRule({
  description: "Don't write commits or refs with git plumbing.",
  message:
    'A commit is made with `git commit` and moves the branch it is on, so every commit has a reflog entry and lands through its pull request. `git commit-tree` and `git update-ref` make a commit or a ref that no branch history records.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        if (command.program !== 'git') return
        const verb = command.args.find((arg) => !arg.startsWith('-') && !command.args[command.args.indexOf(arg) - 1]?.match(/^-[Cc]$/))
        if (verb && PLUMBING.has(verb)) context.report({ node: command })
      },
    }
  },
})
