<?php
require __DIR__ . '/shared/layout.php';

/*
 * Server-side template injection. The "template engine" here is twelve lines
 * of regex + eval. Every {{ expr }} placeholder in the user-supplied template
 * is handed to eval('return ' . $expr . ';'), which means anything PHP can
 * express runs server-side with the privileges of www-data.
 *
 * Demo, intended:
 *   {{ 2 + 2 }}                       -> 4
 *   Hello {{ strtoupper("world") }}   -> Hello WORLD
 *
 * Exploit:
 *   {{ system("id") }}                -> uid=33(www-data) ...
 *   {{ file_get_contents("/etc/passwd") }}
 *   {{ `id` }}                        -> PHP backtick operator, same as shell_exec
 *
 * This is the same shape as Twig / Jinja2 / Smarty SSTI in other ecosystems:
 * a template language that exposes the host language's evaluator to whoever
 * controls the template string. The realistic anti-pattern is rendering
 * templates that came from user input (a CMS field, a webhook body, an email
 * subject line) through an engine that allows arbitrary expressions.
 *
 * Fix shape: never eval. Use a sandboxed template engine that exposes only
 * a fixed set of safe operations, and only render templates authored by
 * trusted users.
 */

function render_template(string $tpl): string {
    return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', function ($m) {
        $expr = $m[1];
        // The whole point of the vulnerability: user-controlled $expr gets
        // wrapped in a return and executed. @ silences parse warnings so a
        // typo in the demo template still renders the rest of the page.
        $result = @eval('return ' . $expr . ';');
        return (string)$result;
    }, $tpl) ?? '';
}

$default = "Hello {{ strtoupper(\"world\") }}. 2 + 2 = {{ 2 + 2 }}";
$tpl = $_POST['tpl'] ?? $default;
$rendered = render_template($tpl);

layout_open('Template');
?>
<h1>Template renderer</h1>
<p>Naive <code>{{ expr }}</code> renderer. Innocuous demo:
<code>{{ 2 + 2 }}</code> evaluates to <code>4</code>.</p>

<form method="post">
    <label for="tpl">Template</label>
    <textarea id="tpl" name="tpl"><?= h($tpl) ?></textarea>
    <button type="submit">Render</button>
</form>

<h2>Output</h2>
<pre><?= h($rendered) ?></pre>

<?php layout_close();
