import { defineConfig } from '@cmodjs/rulebook'

export default defineConfig({
  plugins: { sharp: './.claude/rulebook' },
  rules: {
    'sharp/require-rustfmt': 'error',
    'sharp/block-ci-avoidance': 'error',
    'sharp/block-consumer-names': 'error',
    'sharp/block-consumer-names-in-posts': 'error',
    'sharp/block-reading-the-consumer': 'error',
    'sharp/block-relative-newermt': 'error',
    'sharp/block-check-reruns': 'error',
    'sharp/block-php-reasons': 'error',
    'sharp/block-plumbing-commits': 'error',
    'sharp/block-auto-merge-off': 'error',
    'sharp/block-intake-shell-writes': 'error',
    'sharp/require-utility-builds': 'error',
    'sharp/require-one-thread-mago': 'error',
    'sharp/block-local-suites': 'error',
    'sharp/block-pattern-kills': 'error',
  },
})
