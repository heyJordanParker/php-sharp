import { defineRule } from '@cmodjs/rulebook'

// On 11-24 the lanes' builds, tests and measurements ran under `nice`, and the machine still reached a load of 208 on
// 12 cores: the release's Mac job took 56 minutes over a 10-minute engine build, and the owner's machine was barely
// usable. `nice` lowers a priority, yet the work still runs on every performance core. Background priority, the one
// macOS gives Spotlight and Time Machine, keeps a process and the children it starts on the efficiency cores with
// throttled disk access, so the CI runner and the owner keep the performance cores. Measurements count instructions
// and heartbeats (benchmark/speed.php), which no priority changes.
const HEAVY = new Set(['cargo', 'rustc', 'mago', 'lake', 'lean', 'composer', 'docker', 'make'])
const SCRIPTS = /(^|\/)(sharp\/bin\/[\w-]+|benchmark\/speed\.php|[\w.-]+\.sh)$|^\/usr\/bin\/time$/

export default defineRule({
  description: 'Run every build, test and measurement at background priority.',
  message: 'Prefix the command with `taskpolicy -b`, so it runs on the efficiency cores and the CI runner and the owner keep the rest.',
  files: ['@command'],
  create(context) {
    const commands: { program: string; args: string[]; argv: string[]; runner?: number }[] = []
    return {
      Command(command) {
        commands.push(command)
        const path = command.argv[0] ?? command.program
        const name = command.program.split('/').pop() ?? ''
        const heavy = HEAVY.has(name) || /^mago-[\w.-]+$/.test(name) || SCRIPTS.test(path)
          || (['php', 'sh', 'bash', 'zsh'].includes(name) && command.args.some((arg) => SCRIPTS.test(arg)))
        if (!heavy) return
        let runner = command.runner
        while (runner !== undefined) {
          const outer = commands[runner]
          if (outer?.program === 'taskpolicy' && outer.args.includes('-b')) return
          runner = outer?.runner
        }
        context.report({ node: command })
      },
    }
  },
})
