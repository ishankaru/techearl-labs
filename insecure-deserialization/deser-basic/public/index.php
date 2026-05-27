<?php
// Landing page — lists the vulnerable endpoints. Pure HTML, no behaviour.
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>deser-basic — insecure deserialization lab</title>
<style>
  body { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; max-width: 720px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
  h1 { font-size: 1.2rem; }
  table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
  th, td { border: 1px solid #ccc; padding: 0.4rem 0.6rem; text-align: left; vertical-align: top; }
  code { background: #f3f3f3; padding: 0.05rem 0.3rem; border-radius: 3px; }
  .warn { background: #fff8c4; padding: 0.5rem 0.8rem; border-left: 4px solid #d9c200; margin: 1rem 0; }
</style>
</head>
<body>

<h1>deser-basic — insecure deserialization lab</h1>

<p>A small PHP 8.2 app with three flavours of <code>unserialize()</code> abuse. Companion lab for the
<a href="https://techearl.com/insecure-deserialization">TechEarl insecure deserialization article</a>.</p>

<p>The gadget class is <code>Logger</code> (see <code>src/Logger.php</code>). Its <code>__destruct</code> appends
attacker-controlled bytes to an attacker-controlled file path, so any request that successfully
deserializes a <code>Logger</code> object leaves a file-write footprint on disk.</p>

<table>
<tr><th>Endpoint</th><th>Source</th><th>Sink</th></tr>
<tr><td><code>GET /cookie.php</code></td><td><code>state</code> cookie (base64 of serialized PHP)</td><td><code>unserialize($cookie)</code></td></tr>
<tr><td><code>POST /post.php</code></td><td><code>data</code> POST field (raw serialized PHP)</td><td><code>unserialize($_POST['data'])</code></td></tr>
<tr><td><code>GET /phar_demo.php?file=...</code></td><td>attacker-supplied path</td><td><code>file_exists($file)</code> with a <code>phar://</code> URL</td></tr>
</table>

<div class="warn">
Every successful exploit writes to <code>/tmp/pwned</code> inside the container. To verify, run
<code>docker compose exec deser-basic cat /tmp/pwned</code> from the host. The article walks the
payloads.
</div>

</body>
</html>
