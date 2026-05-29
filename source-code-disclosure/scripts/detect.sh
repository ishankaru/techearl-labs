#!/usr/bin/env bash
# detect.sh — Quickly check whether a target URL is exposing its .git/ directory.
#
# Companion script to:
#   https://techearl.com/exposed-git-directory-attack
#
# Usage:
#   ./detect.sh https://example.com
#
# Exit codes:
#   0  - .git/ is exposed (vulnerable)
#   1  - .git/ is not exposed
#   2  - usage error / target unreachable
#
# What it checks:
#   1. /.git/HEAD returns 200 with a body matching "ref: refs/heads/<branch>"
#   2. /.git/config returns 200 with a body containing "[core]"
#
# Either check passing on its own is enough to investigate further.

set -u

if [[ $# -ne 1 ]]; then
  echo "usage: $0 <target-url>" >&2
  exit 2
fi

TARGET="${1%/}"
UA="git-exposure-checker/1.0 (+https://techearl.com/exposed-git-directory-attack)"

check_url() {
  local path="$1"
  local pattern="$2"
  local url="${TARGET}${path}"

  local body status
  body=$(curl -sS -A "$UA" --max-time 10 -o /tmp/_check.$$ -w "%{http_code}" "$url") || {
    echo "  $path — request failed"
    return 1
  }
  status="$body"
  body=$(cat /tmp/_check.$$ 2>/dev/null || true)
  rm -f /tmp/_check.$$

  if [[ "$status" == "200" ]] && [[ "$body" =~ $pattern ]]; then
    echo "  $path — HTTP 200, matches /$pattern/  [EXPOSED]"
    return 0
  else
    echo "  $path — HTTP $status, no match"
    return 1
  fi
}

echo "Checking: $TARGET"
hit=0
check_url "/.git/HEAD" "ref: refs/heads/" && hit=$((hit + 1))
check_url "/.git/config" "\[core\]"        && hit=$((hit + 1))

if [[ $hit -gt 0 ]]; then
  echo ""
  echo ".git/ directory appears EXPOSED on $TARGET"
  echo "Next step: run git-dump.py against the same URL to reconstruct the repo."
  exit 0
else
  echo ""
  echo "No .git/ exposure detected."
  exit 1
fi
