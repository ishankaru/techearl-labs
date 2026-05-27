<?php
require __DIR__ . '/shared/layout.php';

layout_open('rce-basic');
?>
<h1>rce-basic: vulnerable endpoints</h1>
<p><small class="note">Deliberately vulnerable lab. See README. Bind to 127.0.0.1 only.</small></p>

<div class="card">
    <h2><a href="/ping.php">/ping.php</a></h2>
    <p>Classic OS command injection. <code>shell_exec('ping -c 1 ' . $_GET['host'])</code>. Shell metacharacters pass straight through.</p>
</div>

<div class="card">
    <h2><a href="/lookup.php">/lookup.php</a></h2>
    <p>Argument injection. The user input is wrapped with <code>escapeshellarg()</code> so shell metacharacters are quoted, but the value is still passed as a positional argument to <code>dig</code>, which accepts flags that read arbitrary files.</p>
</div>

<div class="card">
    <h2><a href="/template.php">/template.php</a></h2>
    <p>Server-side template injection via a hand-rolled mini engine. <code>{{ expr }}</code> placeholders are rendered with <code>eval('return ' . $expr . ';')</code>. Same family as Twig / Jinja2 SSTI in other ecosystems.</p>
</div>

<div class="card">
    <h2><a href="/calc.php">/calc.php</a></h2>
    <p>Direct <code>eval()</code> of a POST body. A "scientific calculator" that evaluates whatever the client submits. Textbook RCE sink.</p>
</div>

<?php layout_close();
