import { defineRule } from '@cmodjs/rulebook'

// On 11-24 the machine's load average reached 164 to 208 on 12 cores while lanes ran several full-thread compiles at
// once, each starting a law checker of 24 threads, and the release's Mac job took 62 minutes over a 10-minute engine
// build. A lane runs a mago copy named for its build, such as mago-v055, as often as one named mago. One worker thread
// gives counts no interleaving changes and holds one core.
const MAGO = /(^|\/)mago(-[\w.-]+)?$/
const ASKS = new Set(['--version', '--help', '-h', '-V'])

export default defineRule({
  description: 'Name the worker threads every mago run uses.',
  message: 'Pass `--threads 1` to mago: a measurement counts instructions and heartbeats, which one thread keeps exact, and the shared machine keeps its other cores. Pass a larger count only to measure a cost that exists only across threads.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        if (!MAGO.test(command.argv[0] ?? command.program)) return
        const names = command.args.some((arg) => arg === '--threads' || arg.startsWith('--threads=') || ASKS.has(arg))
        if (!names) context.report({ node: command })
      },
    }
  },
})
