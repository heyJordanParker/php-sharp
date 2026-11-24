import { defineRule } from '@cmodjs/rulebook'

// Lanes held speed measurements for a quiet machine that never came, because the lanes' own builds keep it loaded.
// A measurement is instructions retired and CPU time from `/usr/bin/time -l`, which load doesn't change, so no lane
// reads the load average to decide when to measure.
export default defineRule({
  description: "Don't read the machine's load to decide when to measure.",
  message:
    "Measure the moment both binaries are built, by `/usr/bin/time -l`'s instructions retired and CPU time, which load doesn't change. Wall time is logged and never compared.",
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        const loadavg = command.program === 'sysctl' && command.args.some((arg) => arg.includes('vm.loadavg'))
        if (command.program === 'uptime' || loadavg) context.report({ node: command })
      },
    }
  },
})
