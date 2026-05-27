<?php
require __DIR__ . '/shared/layout.php';

/*
 * Argument injection. escapeshellarg() does its job here: it single-quotes
 * the user input so shell metacharacters like ; | ` $() cannot break out of
 * the argument and execute a second command. A payload like
 *
 *   ?domain=foo;id
 *
 * gets quoted to 'foo;id' and dig just reports the lookup of that literal
 * string failing. So far so good.
 *
 * The bug is that the quoted string is still passed as the first positional
 * argument to dig, and dig interprets any positional argument starting with
 * a dash as a flag. dig has a flag -f <file> that reads "batch mode" queries
 * from a file. When the file does not contain valid query lines, dig prints
 * its contents as parse errors to stderr.
 *
 *   ?domain=-f /etc/passwd
 *
 * resolves to:  dig '-f /etc/passwd'   (one positional, two dig flags)
 * and the contents of /etc/passwd come back in the response.
 *
 * Fix shape: validate the input against a strict domain-name regex BEFORE
 * shelling out, and / or pass `--` before the user argument so dig stops
 * parsing flags. escapeshellarg() alone is not enough when the program
 * itself accepts dangerous arguments.
 */

$domain = $_GET['domain'] ?? '';
$output = null;

if ($domain !== '') {
    // escapeshellarg quotes the value but does not prevent it being read as
    // a flag. No -- separator. No allow-list. This is the realistic shape.
    $cmd = 'dig ' . escapeshellarg($domain) . ' 2>&1';
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
