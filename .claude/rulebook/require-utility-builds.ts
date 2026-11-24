import { defineRule } from '@cmodjs/rulebook'
import { started } from '../rulebook-lib/taskpolicy.ts'

// On 11-24 the lanes' builds, tests and measurements ran under `nice`, and the machine reached a load of 208 on 12
// cores: the release's Mac job took 62 minutes over a 10-minute engine build, and the owner's machine was barely
// usable. Background priority then held every lane on the 4 efficiency cores: a fixed loop took 99.6 s and 111.3 s
// of wall time and 21 to 23 CPU-seconds there, against 6.3 s and 6.3 CPU-seconds at utility priority, at a load of
// 18; a lane's cargo build sat 57 minutes on one crate. Utility, the class macOS gives long work that nobody waits on
// interactively, runs on any free core and yields to the owner's apps and to the CI runner, whose class is higher.
// Measurements count instructions and heartbeats (benchmark/speed.php), which no priority changes.
const HEAVY = new Set(['cargo', 'rustc', 'mago', 'lake', 'lean', 'composer', 'docker', 'make'])
const SCRIPTS = /(^|\/)(sharp\/bin\/[\w-]+|benchmark\/speed\.php|[\w.-]+\.sh)$|^\/usr\/bin\/time$/

const heavy = ([path = '', ...args]: readonly string[]): boolean => {
  const name = path.split('/').pop() ?? ''
  return HEAVY.has(name) || /^mago-[\w.-]+$/.test(name) || SCRIPTS.test(path)
    || (['php', 'sh', 'bash', 'zsh'].includes(name) && args.some((arg) => SCRIPTS.test(arg)))
}

export default defineRule({
  description: 'Run every build, test and measurement at utility priority.',
  message: 'Prefix the command with `taskpolicy -c utility`, so it runs on any free core and yields to the owner and the CI runner.',
  files: ['@command'],
  create(context) {
    const commands: { program: string; args: string[]; argv: string[]; runner?: number }[] = []
    return {
      Command(command) {
        commands.push(command)
        const { argv, utility } = started(command.program, command.args, command.argv)
        if (utility || !heavy(argv)) return
        let runner = command.runner
        while (runner !== undefined) {
          const outer = commands[runner]
          if (outer && started(outer.program, outer.args, outer.argv).utility) return
          runner = outer?.runner
        }
        context.report({ node: command })
      },
    }
  },
})
