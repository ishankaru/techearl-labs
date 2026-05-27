<?php
require __DIR__ . '/../shared/layout.php';

/*
 * DEFENDED: same intent as /search.php, three guards.
 *
 *   1. Hard length cap on the user input. 64 bytes is plenty for a search box.
 *   2. A non-pathological regex shape: drop the nested quantifier; one + is enough.
 *   3. PCRE backtrack limit pinned low via ini_set. PHP's default is 1e6, which
 *      is generous; 100000 still allows real searches and trips long before the
 *      worst-case input can burn a second of CPU.
 *
 * For really hot paths against attacker input the right move is to switch to
 * a non-backtracking engine (RE2 via the `chregex` extension or out-of-process
 * to a Go/Rust helper). The article explains that; this endpoint shows the
 * minimum viable defence in pure PHP.
 */

ini_set('pcre.backtrack_limit', '100000');

const MAX_LEN = 64;

$q = $_GET['q'] ?? '';
$too_long = strlen($q) > MAX_LEN;
$matched = null;
$elapsed_ms = null;
$pcre_err = null;

if ($q !== '' && !$too_long) {
    $t0 = microtime(true);
    // Note the single + on the inner group. Linear-time on the engine.
    $matched = (bool) @preg_match('/^a+$/', $q);
    $elapsed_ms = (microtime(true) - $t0) * 1000;
    if (preg_last_error() !== PREG_NO_ERROR) {
        $pcre_err = preg_last_error_msg();
    }
}

layout_open('search (defended)');
?>
<h1>/defended/search.php — defended ReDoS sibling</h1>
<p><small class="note">Length cap <?= MAX_LEN ?> bytes, non-pathological regex, PCRE backtrack limit pinned low.</small></p>

<form method="get" action="/defended/search.php">
    <label for="q">q</label>
    <input id="q" name="q" type="text" value="<?= h($q) ?>" placeholder="aaaaaaaaaaaaaaaaaaaaaa!">
    <button type="submit">Search</button>
</form>

<?php if ($too_long): ?>
    <div class="alert">Refused: input is <?= strlen($q) ?> bytes, max <?= MAX_LEN ?>.</div>
<?php elseif ($q !== ''): ?>
    <h2>Result</h2>
    <pre>matched: <?= $matched ? 'true' : 'false' ?>
elapsed: <?= number_format((float) $elapsed_ms, 2) ?> ms
input length: <?= strlen($q) ?> bytes
<?= $pcre_err ? "pcre error: " . h($pcre_err) : '' ?></pre>
    <?php if ($pcre_err): ?>
        <div class="ok">Backtrack limit tripped before the request could burn CPU. This is the desired behaviour.</div>
    <?php endif; ?>
<?php endif; ?>

<h2>Compare to the vulnerable sibling</h2>
<pre>time curl 'http://localhost:8092/defended/search.php?q=aaaaaaaaaaaaaaaaaaaaaa!'</pre>
<p>Same payload, same regex intent. The length cap alone would refuse the request; even without it the simpler pattern runs in microseconds.</p>

<?php layout_close();
