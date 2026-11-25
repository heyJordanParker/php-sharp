import { defineRule } from '@cmodjs/rulebook'

const PLUMBING = new Set(['commit-tree', 'update-ref'])
const GIT_DATABASE = /(^|\/)git\/(commits|trees|blobs|refs|tags)(\/|$)/
const BODY_FLAGS = new Set(['-f', '-F', '--field', '--raw-field', '--input'])

const method = (args: string[]) => {
  const at = args.findIndex((arg) => arg === '-X' || arg === '--method')
  if (at >= 0) return args[at + 1]?.toUpperCase()
  const joined = args.find((arg) => arg.startsWith('--method=') || /^-X[A-Za-z]/.test(arg))
  if (joined) return joined.replace(/^--method=|^-X/, '').toUpperCase()
  return args.some((arg) => BODY_FLAGS.has(arg) || [...BODY_FLAGS].some((flag) => arg.startsWith(`${flag}=`))) ? 'POST' : 'GET'
}

export default defineRule({
  description: "Don't write commits or refs with git plumbing.",
  message:
    'A commit is made with `git commit` and moves the branch it is on, so every commit has a reflog entry and lands through its pull request, and it reaches GitHub only through `git push`. `git commit-tree`, `git update-ref` and a write to GitHub\'s git database API (`repos/<owner>/<repo>/git/commits`, `trees`, `blobs`, `refs` or `tags`) make a commit or a ref that no local branch history records. A refused push is a stale parent: fetch and merge, then push again.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        if (command.program === 'gh' && command.args[0] === 'api') {
          if (command.args.some((arg) => GIT_DATABASE.test(arg)) && method(command.args) !== 'GET') context.report({ node: command })
          return
        }
        if (command.program !== 'git') return
        const verb = command.args.find((arg) => !arg.startsWith('-') && !command.args[command.args.indexOf(arg) - 1]?.match(/^-[Cc]$/))
        if (verb && PLUMBING.has(verb)) context.report({ node: command })
      },
    }
  },
})
