<?php
/*
 * VULNERABLE: DOM-based XSS.
 *
 * The server response is a fixed, static HTML document. There is no untrusted
 * input on the server side at all. The vulnerability is purely client-side:
 * a small inline script reads location.hash, decodes it, and writes it into
 * the DOM via .innerHTML.
 *
 * Canonical payload:
 *
 *   /share.php#<img src=x onerror=alert(1)>
 *
 * The fragment never leaves the browser, so this sink is invisible to any
 * server-side WAF, IDS, or request log. That is why DOM XSS is a distinct
 * class from reflected/stored, even though the impact is identical.
 *
 * innerHTML strips inline <script> tags from injected content (per the HTML
 * spec for parsing fragments), which is why payloads typically use
 * event-handler attributes on tags that ARE rendered (<img onerror>,
 * <svg onload>, <body onload>, etc).
 */

require_once __DIR__ . '/shared/db.php';

layout_open('Share');
echo '<h1>Share this page</h1>';
echo '<p>Drop a message into the URL fragment after the <code>#</code> and it shows up below. Useful for sharing prefilled views with someone over chat.</p>';
echo '<p><small class="note">Try <code>/share.php#hello world</code>.</small></p>';
echo '<div id="out" style="border:1px dashed #cbd5e1;border-radius:.5rem;padding:1rem;min-height:2rem">(nothing shared yet)</div>';
?>
<script>
// VULNERABLE: writes the URL fragment into the DOM via innerHTML.
// The fragment is attacker-controlled and never sanitised. Classic DOM XSS.
(function () {
  var hash = location.hash.slice(1);
  if (hash.length === 0) return;
  var out = document.getElementById('out');
  out.innerHTML = decodeURIComponent(hash);
})();
</script>
<?php
layout_close();
