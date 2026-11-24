import { defineRule } from '@cmodjs/rulebook'

// On 11-24 the lanes' builds, tests and measurements held about 7 of the machine's 12 cores at normal priority, and the
// language owner's machine was barely usable. `nice` lets every build use each core nobody else wants, and gives a core
// back the moment the owner's own work needs it. Measurements count instructions and heartbeats (benchmark/speed.php),
// which priority doesn't change.
const HEAVY = new Set(['cargo', 'rustc', 'mago', 'lake', 'lean', 'composer', 'docker'])
const SCRIPTS = /(^|\/)(sharp\/bin\/(test|check|spec|bench)|benchmark\/speed\.php)$|^\/usr\/bin\/time$/

export default defineRule({
  description: 'Run every build, test and measurement under nice.',
  message: 'Prefix the command with `nice -n 10`, so the owner keeps a responsive machine while it runs.',
  files: ['@command'],
  create(context) {
    const commands: { program: string; args: string[]; argv: string[]; runner?: number }[] = []
    return {
      Command(command) {
        commands.push(command)
        const path = command.argv[0] ?? command.program
        const heavy = HEAVY.has(command.program.split('/').pop() ?? '') || SCRIPTS.test(path)
          || (command.program === 'php' && command.args.some((arg) => SCRIPTS.test(arg)))
        if (!heavy) return
        let runner = command.runner
        while (runner !== undefined) {
          if (commands[runner]?.program === 'nice') return
          runner = commands[runner]?.runner
        }
        context.report({ node: command })
      },
    }
  },
})
