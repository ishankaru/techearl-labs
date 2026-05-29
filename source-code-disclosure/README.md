# source-code-disclosure

Companion lab for the techearl.com article:
**[Exposed .git Directory: How Attackers Reconstruct Your Source Code](https://techearl.com/exposed-git-directory-attack)**

This directory contains reference implementations of the two scripts that
matter when a web server is accidentally serving its `.git/` folder:

1. **`scripts/detect.sh`** — a 30-line bash one-shot that confirms whether
   `<target>/.git/HEAD` and `<target>/.git/config` are reachable.
2. **`scripts/git-dump.{py,mjs,php}`** — three equivalent implementations
   (Python, Node.js, PHP) of a minimal git-dumper. Each one walks the
   well-known `.git/` index files, extracts every SHA-1 reference, fetches
   loose objects under `.git/objects/aa/bbbb...`, parses tree objects to
   discover their blob SHAs, and writes everything to a local directory you
   can then `cd` into and inspect with a real `git` binary.

All four are deliberately under 200 lines and dependency-free (Python uses
only the standard library, Node uses the built-in `fetch`, PHP needs only
the curl + zlib extensions that ship with every default install). The point
is to make the attack legible end-to-end, not to replace
[`git-dumper`](https://github.com/arthaud/git-dumper) or
[`GitTools/Dumper`](https://github.com/internetwache/GitTools) in a real
engagement — those have brute-forcing, concurrency, and edge-case handling
this code does not.

## Quickstart

```bash
# 1. Detect
./scripts/detect.sh https://target.example.com

# 2. If exposed, dump (pick whichever runtime you have)
python3 scripts/git-dump.py  https://target.example.com ./dumped
node     scripts/git-dump.mjs https://target.example.com ./dumped
php      scripts/git-dump.php https://target.example.com ./dumped

# 3. Inspect
cd dumped
git fsck --full
git log --all --oneline
git log --all -p
git log --all -p | grep -iE 'password|secret|token|api_key'
```

## Running the lab locally

The article uses a deliberately-vulnerable WordPress install (via
[`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env)) with a
fake repo dropped into `wp-content/uploads/.git`. The repo has four commits,
one of which adds a `.env` with fake AWS / Stripe credentials, the next of
which "removes" it (but, as the article explains, the file lives forever in
object history).

Reproduce the lab inside any docker-running WordPress container:

```bash
docker exec <wp-container> bash -c '
  cd /var/www/html/wp-content/uploads
  mkdir -p tmp-repo && cd tmp-repo
  git init -q -b main
  git config user.email "dev@example.test"
  git config user.name  "Demo Dev"
  echo "App skeleton" > README.md
  git add . && git commit -q -m "Initial"
  echo "DB_PASS=Sup3rS3cret-2019" > .env
  git add .env && git commit -q -m "Add .env for local dev"
  git rm -q .env
  echo ".env" > .gitignore
  git add .gitignore && git commit -q -m "Remove .env, gitignore"
  mv .git ../.git
  cd .. && rm -rf tmp-repo
'

# Then from the host:
./scripts/detect.sh http://localhost:8888/wp-content/uploads
python3 scripts/git-dump.py http://localhost:8888/wp-content/uploads ./dumped
cd dumped && git log --all -p -- .env
```

You should see the credential recovered from history even though it was
"deleted" two commits later.

## Why this works

A git repository's working tree is what you see in `ls`; the `.git/`
directory is the entire history database. When a deploy step copies a
project root to a webroot without excluding `.git/`, the database goes with
it. Git's storage format is content-addressable: every commit, tree, and
blob is identified by the SHA-1 of its contents, and the path on disk is
`.git/objects/<first-two-hex-chars>/<remaining-38>`. Given the head SHA
(from `HEAD` → `refs/heads/main`), a remote attacker walks the graph by
fetching one object at a time. The same code paths that a `git clone` would
use locally work fine over HTTP — git was originally designed to be served
from any static webserver, and the on-disk format has not changed.

The only thing standing between this and source-code disclosure is whether
the webserver returns `.git/HEAD` to the public internet. Apache's default
configurations frequently do; nginx's default does not. WordPress in
particular is a recurring offender because hosts wire `wp-content/uploads`
to be world-readable for media serving, and developers occasionally treat
it as a dumping ground for unrelated tooling.

## Defences

Documented in detail in the article. In short:

- **Apache:** add `RedirectMatch 404 /\.git` (or `RewriteRule "(^|/)\.git" - [F]`) in `.htaccess`.
- **Nginx:** add `location ~ /\.git { deny all; return 404; }` in the server block.
- **At deploy time:** never deploy from a checkout. Build artifacts (zip, tar,
  rsync `--exclude=.git`) and deploy those. CI/CD platforms get this right
  by default; manual `git pull` on the server does not.
- **In CI:** scan deploy artifacts for `.git/` directories before they ship.

## Files

```
source-code-disclosure/
├── README.md            # this file
└── scripts/
    ├── detect.sh        # check if .git/ is exposed (HEAD + config)
    ├── git-dump.py      # Python 3 reference implementation
    ├── git-dump.mjs     # Node 18+ port
    └── git-dump.php     # PHP 7.4+ port
```

## Legal

These scripts are for use against systems you own or have written permission
to test. Running them against third-party systems without authorisation may
constitute unauthorised access under your jurisdiction's computer-misuse
laws.

The four scripts set descriptive `User-Agent` headers
(`git-exposure-checker/1.0` and `git-dumper-edu/1.0`, each pointing at
the companion article). This makes log triage easy for defenders running
the scripts against their own portfolios and is the right default for
educational tools. It does mean a scan with the default UA will show up
plainly in any target WAF / access log: a useful audit trail when you
have authorisation, a clear tell when you don't. If you are running a
bug-bounty engagement that requires a specific scoping identifier,
overwrite the UA in the script before you run it.

## License

MIT, same as the rest of the techearl-labs repo. See the top-level
[`LICENCE`](https://github.com/ishankaru/techearl-labs/blob/main/LICENCE).
