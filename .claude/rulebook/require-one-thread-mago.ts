import { defineRule } from '@cmodjs/rulebook'

// On 11-24 the machine's load average reached 164 to 196 on 12 cores while lanes ran several full-thread compiles at
// once, each starting a law checker thread per core, and a law's heartbeats differed by a thousand between two runs of
// one build because its threads interleaved differently. A mago an agent runs measures or reproduces, and one worker
// thread gives counts no interleaving changes while holding one core.
export default defineRule({
  description: 'Name the worker threads every mago run uses.',
  message: 'Pass `--threads 1` to mago: a measurement counts instructions and heartbeats, which one thread keeps exact, and the shared machine keeps its other cores. Pass a larger count only to measure a cost that exists only across threads.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        const path = command.argv[0] ?? command.program
        if (!/(^|\/)mago$/.test(path)) return
        const threads = command.args.findIndex((arg) => arg === '--threads' || arg.startsWith('--threads='))
        const asks = command.args.some((arg) => ['--version', '--help', '-h', '-V'].includes(arg))
        if (threads < 0 && !asks) context.report({ node: command })
      },
    }
  },
})
