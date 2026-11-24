import { readFileSync } from 'node:fs'
import { defineRule } from '@cmodjs/rulebook'

const WORKSPACE = `${import.meta.dir}/../../sharp/mago`

export default defineRule({
  description: 'Write Rust that rustfmt leaves unchanged.',
  message: 'rustfmt changes this line. Run rustfmt on the file from sharp/mago, which reads its .rustfmt.toml.',
  files: ['sharp/mago/**/*.rs'],
  create(context) {
    const version = /^rust-version = "([^"]+)"/m.exec(readFileSync(`${WORKSPACE}/Cargo.toml`, 'utf8'))?.[1]
    if (version === undefined) return {}
    const text = context.sourceCode.text
    const run = Bun.spawnSync(['rustup', 'run', version, 'rustfmt', '--emit', 'stdout', '--quiet'], {
      cwd: WORKSPACE,
      env: process.env,
      stdin: new TextEncoder().encode(text),
    })
    const formatted = run.stdout.toString()
    if (run.exitCode !== 0 || formatted === text) return {}
    const written = text.split('\n')
    const wanted = formatted.split('\n')
    const line = written.findIndex((each, at) => each !== wanted[at])
    context.report({ loc: { start: { line: (line === -1 ? written.length - 1 : line) + 1, column: 0 } } })
    return {}
  },
})
