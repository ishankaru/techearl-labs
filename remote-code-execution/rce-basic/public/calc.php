<?php
require __DIR__ . '/shared/layout.php';

/*
 * Direct eval() of a POST body. Never eval user input. This is the
 * textbook RCE sink. There is no clever framework here, no template
 * layer, no shell. The application takes a string from the request and
 * hands it straight to the PHP interpreter:
 *
 *   eval("return " . $_POST['expr'] . ";");
 *
 * Intended demo:
 *   expr=2 * pi()              -> 6.2831853071796
 *   expr=sqrt(2)               -> 1.4142135623731
 *
 * Exploit:
 *   expr=phpinfo()
 *   expr=system("id")
 *   expr=file_get_contents("/etc/passwd")
 *   expr=`id`                  (PHP backtick = shell_exec)
 *
 * Real-world variants of this bug hide behind "let admins write small
 * expressions in the rules engine", "let plugins evaluate dynamic
 * formulas", etc. The fix is the same as every other eval RCE: do not
 * eval untrusted input. If a real expression language is needed, use a
 * dedicated parser (Symfony ExpressionLanguage, mathjs, etc.) with a
 * locked-down function allow-list.
 */

$expr = $_POST['expr'] ?? '';
$result = null;
$error = null;

if ($expr !== '') {
    // Suppress parse warnings so a bad expression returns a friendly error
    // instead of leaking the PHP error to the page. The eval itself is the
    // vulnerability; the error handling is cosmetic.
    set_error_handler(function ($_n, $msg) use (&$error) { $error = $msg; });
    try {
        $result = @eval('return ' . $expr . ';');
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
    restore_error_handler();
}

layout_open('Calculator');
?>
<h1>Scientific calculator</h1>
<p>Evaluates a PHP expression. Try <code>2 * pi()</code> or <code>sqrt(2)</code>.</p>

<form method="post">
    <label for="expr">Expression</label>
    <input type="text" id="expr" name="expr" value="<?= h($expr) ?>" placeholder="2 * pi()" autofocus>
    <button type="submit">Evaluate</button>
</form>

<?php if ($expr !== ''): ?>
    <h2>Result</h2>
    <?php if ($error !== null): ?>
        <div class="alert"><?= h($error) ?></div>
    <?php else: ?>
        <pre><?= h(var_export($result, true)) ?></pre>
    <?php endif; ?>
<?php endif; ?>

<?php layout_close();
