<?php
require __DIR__ . '/shared/layout.php';

/*
 * Classic OS command injection. The user-supplied host is concatenated
 * directly into the argument string handed to shell_exec(), which spawns
 * /bin/sh -c '<string>'. Any shell metacharacter the attacker sends gets
 * interpreted by that shell, not by ping:
 *
 *   ;   command separator      -> ?host=localhost;id
 *   |   pipe                   -> ?host=localhost|id
 *   `   command substitution   -> ?host=`id`
 *   $() command substitution   -> ?host=$(id)
 *   &&  conditional chain      -> ?host=localhost%26%26id
 *
 * The fix is not "filter dangerous characters" (always incomplete). It is
 * "do not call the shell": use the array form of proc_open / pcntl_exec
 * so the binary is invoked directly with argv, no shell parsing.
 */

$host = $_GET['host'] ?? '';
$output = null;

if ($host !== '') {
    // -c 1 sends a single echo request; -W 1 caps wait at one second so a
    // bogus host does not hang the request. Both flags are on the ping
    // side; they do nothing to constrain what the shell will execute.
    $cmd = 'ping -c 1 -W 1 ' . $host . ' 2>&1';
    $output = shell_exec($cmd);
}

layout_open('Ping');
?>
<h1>Ping</h1>
<p>Sends a single ICMP echo to the host you supply.</p>

<form method="get">
    <label for="host">Host</label>
    <input type="text" id="host" name="host" value="<?= h($host) ?>" placeholder="example.com" autofocus>
    <button type="submit">Ping</button>
</form>

<?php if ($output !== null): ?>
    <h2>Output</h2>
    <pre><?= h((string)$output) ?></pre>
<?php endif; ?>

<?php layout_close();
