<?php
require __DIR__ . '/shared/layout.php';

/*
 * VULNERABLE: classic ReDoS via PCRE backtracking on nested quantifiers.
 *
 * The pattern (a+)+$ against input like "aaa...a!" forces the engine to try
 * every partition of the leading a-run between the inner and outer groups
 * before failing on the trailing '!'. Each extra "a" roughly doubles the
 * runtime. ~22 chars is enough to push response time over a second on a
 * single CPU core.
 *
 * No length cap, no input validation. The PCRE backtrack limit is raised
 * here from PHP's default (1,000,000) to 100,000,000 to reflect the common
 * real-world mistake: a team hits the default limit on a complex but benign
 * regex, raises pcre.backtrack_limit in php.ini "to make the warning go
 * away", and ships the ReDoS surface along with it. The defended sibling
 * shows the opposite move — pin the limit low and refuse the pathological
 * input instead.
 */

// PCRE2 JIT silently rewrites the worst-case path on simple patterns. Many
// real shops disable JIT after hitting an obscure bug ("works without JIT,
// crashes with it") and never re-enable. Reproduce that here so the lab
// shows the catastrophic shape rather than the optimised one.
ini_set('pcre.jit', '0');
ini_set('pcre.backtrack_limit', '1000000000');
ini_set('pcre.recursion_limit', '1000000000');

$q = $_GET['q'] ?? '';

$matched = null;
$elapsed_ms = null;
$pcre_err = null;

if ($q !== '') {
    $t0 = microtime(true);
    // The classic pathological pattern.
    $matched = (bool) @preg_match('/^(a+)+$/', $q);
    $pcre_err = preg_last_error();
    $elapsed_ms = (microtime(true) - $t0) * 1000;
}

layout_open('search (ReDoS)');
?>
<h1>/search.php — vulnerable to ReDoS</h1>
<p><small class="note">Pattern: <code>/^(a+)+$/</code> against the <code>q</code> parameter. Nested quantifiers, no length cap.</small></p>

<form method="get" action="/search.php">
    <label for="q">q</label>
    <input id="q" name="q" type="text" value="<?= h($q) ?>" placeholder="aaaaaaaaaaaaaaaaaaaaaa!">
    <button type="submit">Search</button>
</form>

<?php if ($q !== ''): ?>
    <h2>Result</h2>
    <pre>matched: <?= $matched ? 'true' : 'false' ?>
elapsed: <?= number_format((float) $elapsed_ms, 2) ?> ms
input length: <?= strlen($q) ?> bytes</pre>
<?php endif; ?>

<h2>Try it</h2>
<pre>time curl 'http://localhost:8092/search.php?q=aaaaaaaaaaaaaaaaaaaaaa!'</pre>
<p>Add more <code>a</code> characters before the <code>!</code> and watch the response time double per character. Compare to <a href="/defended/search.php">/defended/search.php</a>.</p>

<?php layout_close();
