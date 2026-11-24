import { defineRule } from '@cmodjs/rulebook'

// On 11-24 the machine's load average reached 164 to 196 on 12 cores while lanes ran several full-thread compiles at
// once, each starting a law checker thread per core, and a law's heartbeats differed by a thousand between two runs of
// one build because its threads interleaved differently. A mago an agent runs measures or reproduces, and one worker
// thread gives counts no interleaving changes while holding one core.
export default defineRule({
  description: 'Run mago on one worker thread.',
  message: 'Pass `--threads 1` to mago: a measurement counts instructions and heartbeats, which one thread keeps exact, and the shared machine keeps its other cores.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        const path = command.argv[0] ?? command.program
        if (!/(^|\/)mago$/.test(path)) return
        const threads = command.args.findIndex((arg) => arg === '--threads' || arg.startsWith('--threads='))
        const one = threads >= 0 && (command.args[threads] === '--threads=1' || command.args[threads + 1] === '1')
        const asks = command.args.some((arg) => ['--version', '--help', '-h', '-V'].includes(arg))
        if (!one && !asks) context.report({ node: command })
      },
    }
  },
})
