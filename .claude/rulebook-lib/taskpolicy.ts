// The shell parser knows `nice` and `sudo` as runners and visits the program each one starts, but sees `taskpolicy`
// as the program itself, with what it starts among its arguments, `/usr/bin/time` and `nice` included. These read the
// program at the end of that chain back out, and whether `taskpolicy -c utility` started it.
const VALUED: Record<string, ReadonlySet<string>> = {
  taskpolicy: new Set(['-c', '-d', '-g', '-t', '-l', '-p', '-m']),
  time: new Set(['-o']),
  nice: new Set(['-n']),
}

export interface Started {
  argv: string[]
  utility: boolean
}

export function started(program: string, args: readonly string[], argv: readonly string[]): Started {
  let rest = [argv[0] ?? program, ...args]
  let utility = false
  for (let name = rest[0]?.split('/').pop() ?? ''; name in VALUED; name = rest[0]?.split('/').pop() ?? '') {
    let at = 1
    while (at < rest.length && rest[at]!.startsWith('-')) {
      if (name === 'taskpolicy' && rest[at] === '-c' && rest[at + 1] === 'utility') utility = true
      at += VALUED[name]!.has(rest[at]!) ? 2 : 1
    }
    rest = rest.slice(at)
  }
  return { argv: rest, utility }
}
