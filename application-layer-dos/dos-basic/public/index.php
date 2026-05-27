<?php
require __DIR__ . '/shared/layout.php';

layout_open('dos-basic');
?>
<h1>dos-basic: application-layer DoS lab</h1>
<p><small class="note">Deliberately resource-fragile lab. Localhost only. Read the README before firing any payload.</small></p>

<div class="alert">
    This lab demonstrates three application-layer DoS classes against the LOCAL
    container at <code>127.0.0.1:8092</code>. Aiming any of these payloads at any host
    other than this lab is a federal-felony-level offence in most jurisdictions.
    The lab exists so you can test your OWN system's resilience and understand the
    attack mechanics. Never point it elsewhere.
</div>

<h2>Endpoints</h2>

<table>
    <thead>
    <tr><th>Vulnerable</th><th>Defended sibling</th><th>Class</th></tr>
    </thead>
    <tbody>
    <tr>
        <td><a href="/search.php">/search.php</a></td>
        <td><a href="/defended/search.php">/defended/search.php</a></td>
        <td>ReDoS: catastrophic backtracking via <code>(a+)+$</code></td>
    </tr>
    <tr>
        <td><a href="/upload.php">/upload.php</a></td>
        <td><a href="/defended/upload.php">/defended/upload.php</a></td>
        <td>Decompression bomb: unbounded <code>gzdecode</code></td>
    </tr>
    <tr>
        <td><a href="/slow.php">/slow.php</a></td>
        <td><a href="/defended/slow.php">/defended/slow.php</a></td>
        <td>Slow-body: server holds a worker for the full upload</td>
    </tr>
    <tr>
        <td><a href="/billion-laughs.php">/billion-laughs.php</a></td>
        <td colspan="2">XML entity expansion: libxml's <code>entity_substitution=false</code> default already mitigates. Cross-link to <a href="https://techearl.com/billion-laughs-attack">billion laughs</a>.</td>
    </tr>
    </tbody>
</table>

<p>Every defended sibling demonstrates one specific mitigation pattern. The point of the lab is the contrast: same payload, vulnerable endpoint chews CPU or RAM, defended endpoint refuses cleanly.</p>

<?php layout_close();
