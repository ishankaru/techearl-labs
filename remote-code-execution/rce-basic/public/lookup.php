<?php
require __DIR__ . '/shared/layout.php';

/*
 * Argument injection. escapeshellcmd() escapes shell metacharacters
 * (; & | > < ` $ etc.) but does NOT quote whitespace or arguments starting
 * with a dash. The textbook command injection
 *
 *   ?domain=foo;id
 *
 * gets escaped to foo\;id and dig just reports the lookup of that literal
 * string failing. So far so good.
 *
 * The bug is that escapeshellcmd leaves dashes alone, and dig interprets
 * any positional argument starting with a dash as a flag. dig has a flag
 *   -f <file>
 * that reads batch queries from a file. When the file contains lines that
 * are not valid DNS names, dig prints their contents as parse errors to
 * stderr alongside DiG's banner.
 *
 *   ?domain=-f /etc/passwd
 *
 * resolves to:  dig -f /etc/passwd 2>&1   (two args to dig, the -f flag
 * and its argument /etc/passwd) and the contents of /etc/passwd come back
 * in the response.
 *
 * Fix shape: validate the input against a strict domain-name regex BEFORE
 * shelling out, and / or pass `--` before the user argument so dig stops
 * parsing flags. escapeshellcmd alone is not enough when the program
 * itself accepts dangerous arguments.
 */

$domain = $_GET['domain'] ?? '';
$output = null;

if ($domain !== '') {
    // escapeshellcmd escapes shell metacharacters but does not quote the
    // value, so dashes and spaces pass through unchanged. No -- separator
    // before the user input. No allow-list. This is the realistic shape.
    $cmd = 'dig ' . escapeshellcmd($domain) . ' 2>&1';
    $output = shell_exec($cmd);
}

layout_open('DNS lookup');
?>
<h1>DNS lookup</h1>
<p>Resolves a domain name with <code>dig</code>.</p>

<form method="get">
    <label for="domain">Domain</label>
    <input type="text" id="domain" name="domain" value="<?= h($domain) ?>" placeholder="example.com" autofocus>
    <button type="submit">Look up</button>
</form>

<?php if ($output !== null): ?>
    <h2>Output</h2>
    <pre><?= h((string)$output) ?></pre>
<?php endif; ?>

<?php layout_close();
