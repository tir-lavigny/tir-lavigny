#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 2 ]; then
  echo "usage: $0 <base> <head>" >&2
  exit 2
fi

base=$1
head=$2

for ref in "$base" "$head"; do
  if ! git rev-parse --verify --quiet "${ref}^{commit}" >/dev/null; then
    echo "invalid ref: $ref" >&2
    exit 2
  fi
done

cd "$(git rev-parse --show-toplevel)"

# Ignore patterns must also be evaluated at base, so files deleted on head are
# still classified correctly. A temporary index keeps the working index intact.
tmp_index=$(mktemp)
rm -f "$tmp_index"
GIT_INDEX_FILE="$tmp_index" git read-tree "$base"
base_ignored=$(GIT_INDEX_FILE="$tmp_index" git ls-files -ci --exclude-from=.dockerignore)
rm -f "$tmp_index"

head_ignored=$(git ls-files -ci --exclude-from=.dockerignore)

# Dockerfile and .dockerignore are excluded from the build context but still
# affect the build, so they must never be filtered out as irrelevant.
ignored=$(printf '%s\n%s\n' "$base_ignored" "$head_ignored" \
  | sed -e '/^$/d' -e '/^\(Dockerfile\|\.dockerignore\)$/d' \
  | sort -u)

relevant=$(comm -23 \
  <(git diff --name-only "$base" "$head" | sort -u) \
  <(printf '%s\n' "$ignored" | sed '/^$/d' | sort -u))

if [ -z "$relevant" ]; then
  exit 1
fi

printf '%s\n' "$relevant"
