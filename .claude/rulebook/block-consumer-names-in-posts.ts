import { readFileSync } from 'node:fs'
import { isAbsolute, join } from 'node:path'
import { defineRule } from '@cmodjs/rulebook'

const NAMES = [String.raw`\b${'De'}${'nt'}\b`, ['creator', 'income', 'blueprint'].join('-'), String.raw`\b${'dn'}t (start|stop|test)\b`]
const CONSUMER = new RegExp(NAMES.join('|'), 'i')

export default defineRule({
  description: "Don't post the consumer project's name to the tracker.",
  message:
    'The tracker is public: an issue, a comment or a pull request never names the consumer project or its files. Rename the shape in the body file and keep the problem.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        if (command.program !== 'gh') return
        command.args.forEach((arg, at) => {
          const inline = arg === '--body' || arg === '-b' || arg === '--title' || arg === '-t' ? command.args[at + 1] : arg.match(/^(--body=|--title=|body=)(?!@)/) ? arg.replace(/^[^=]*=/, '') : undefined
          if (inline !== undefined && CONSUMER.test(inline)) context.report({ node: command })
          const file = arg === '--body-file' || arg === '-F' ? command.args[at + 1] : arg.startsWith('--body-file=') ? arg.slice(12) : undefined
          const path = file?.startsWith('body=@') ? file.slice(6) : file
          if (!path || path === '-') return
          let body = ''
          try {
            body = readFileSync(isAbsolute(path) ? path : join(command.folder ?? context.call?.cwd ?? '.', path), 'utf8')
          } catch {
            return
          }
          if (CONSUMER.test(body)) context.report({ node: command })
        })
      },
    }
  },
})
