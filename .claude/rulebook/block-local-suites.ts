import { defineRule } from '@cmodjs/rulebook'
import { started } from '../rulebook-lib/taskpolicy.ts'

// CI runs every suite on the tree that lands, so a lane runs only the tests its brief names, as /implement's step 3
// says. On 11-25 a lane ran the whole Zend/tests/sharp folder twice in the shared checkout at a load of 162, its slow
// tests timed out, and it spent the next hour rerunning them.
export default defineRule({
  description: "Don't run a whole test folder locally.",
  message: 'Name the .phpt files or the test your brief names. CI runs every suite on the tree that lands.',
  files: ['@command'],
  create(context) {
    return {
      Command(command) {
        const [path = '', ...args] = started(command.program, command.args, command.argv).argv
        const folder = (arg: string) => !arg.startsWith('-') && !arg.endsWith('.phpt') && /(^|\/)(tests?|Zend\/tests)(\/|$)/.test(arg)
        if (/(^|\/)sharp\/bin\/test$/.test(path) && args.some(folder)) context.report({ node: command })
        if (/(^|\/)run-tests\.php$/.test(args[0] ?? '') && args.slice(1).some(folder)) context.report({ node: command })
      },
    }
  },
})
