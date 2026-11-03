#!/usr/bin/env bash
set -uo pipefail

# The converter spike, end to end: copies the fixture project into the empty folder $1, installs it, commits it to a
# fresh git repository, runs run.php on the engine, converts the files the spike names, compiles, analyzes and runs
# run.php again. Needs the engine and mago built by sharp/bin/build and sharp/bin/build-mago, and a php on PATH for the
# refinement worker.
root="$(cd "$(dirname "$0")/../../../../.." && pwd)"
build="$root/sharp/build/$(uname -s | tr '[:upper:]' '[:lower:]')-$(uname -m)"
engine="$build/sapi/cli/php"
mago="$build/mago/test-run/mago"
project="$1"

step() { printf '\n$ %s\n' "$*"; "$@"; printf '[exit %s]\n' "$?"; }

cp -R "$(dirname "$0")/project/." "$project"
cd "$project" || exit 1
sed -i '' "s|\"../../../../../composer\"|\"$root/sharp/composer\"|" composer.json
sed -i '' "s|\"../../../../composer/|\"$root/sharp/mago/composer/|" mago.toml
composer install --quiet --no-interaction || exit 1
git init --quiet
git add --all
git -c user.name=spike -c user.email=spike@localhost commit --quiet -m "plain PHP"

step "$engine" -n run.php
"$engine" -n run.php > "$project.before"
step "$mago" --no-version-check --colors never analyze --reporting-format emacs
step "$mago" --no-version-check --colors never convert --dry-run src/Shop/Order.php
step "$mago" --no-version-check --colors never convert src/Spec.php src/Box.php src/Factory.php src/Shop
step git status --short
step git diff --cached -M --stat
step git diff --stat
step "$mago" --no-version-check --colors never compile
step git status --short --ignored src
step git -c user.name=spike -c user.email=spike@localhost commit --quiet -m "rename to PHP#"
step git -c user.name=spike -c user.email=spike@localhost commit --quiet --all -m "convert to PHP#"
step git log --follow --name-status --format=%s -- src/Shop/Shelf.sharp
step "$mago" --no-version-check --colors never analyze --reporting-format emacs
step "$engine" -n run.php
"$engine" -n run.php > "$project.after"
step diff "$project.before" "$project.after"
