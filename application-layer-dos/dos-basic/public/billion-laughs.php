<?php
require __DIR__ . '/shared/layout.php';

/*
 * XML billion-laughs demo.
 *
 * libxml since 2.9.0 (released 2012) disables entity substitution by default
 * via LIBXML_NOENT being OFF. The simplexml_load_string default does NOT
 * expand the entities, so a billion-laughs payload parses without exploding.
 *
 * The point of this page is to show that the mitigation is already there
 * for any modern PHP/libxml stack, and to cross-link to the deeper XXE/XML
 * coverage on techearl.com.
 */

$payload = $_POST['xml'] ?? '';
$result = null;
$err = null;
$enabled_entity_subst = isset($_POST['unsafe']);

if ($payload !== '') {
    $opts = 0;
    if ($enabled_entity_subst) {
        // Explicitly opt in to the unsafe behaviour, to demonstrate what
        // happens when an application turns the guard off.
        $opts |= LIBXML_NOENT;
    }
    libxml_use_internal_errors(true);
    $t0 = microtime(true);
    $doc = @simplexml_load_string($payload, 'SimpleXMLElement', $opts);
    $elapsed_ms = (microtime(true) - $t0) * 1000;

    if ($doc === false) {
        $errors = libxml_get_errors();
        $err = $errors ? trim($errors[0]->message) : 'parse failed';
        libxml_clear_errors();
    } else {
        $text = (string) $doc;
        $result = [
            'enabled_entity_substitution' => $enabled_entity_subst,
            'parsed_text_bytes' => strlen($text),
            'elapsed_ms' => round($elapsed_ms, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
        ];
    }
}

$default_payload = trim('
<?xml version="1.0"?>
<!DOCTYPE lolz [
  <!ENTITY lol "lol">
  <!ENTITY lol1 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">
  <!ENTITY lol2 "&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;">
  <!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">
  <!ENTITY lol4 "&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;">
  <!ENTITY lol5 "&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;&lol4;">
]>
<lolz>&lol5;</lolz>
');

layout_open('billion laughs');
?>
<h1>/billion-laughs.php — XML entity expansion (mitigation demo)</h1>
<p><small class="note">libxml disables entity substitution by default. simplexml_load_string with no extra flags refuses to expand &amp;lol5; even when the DTD declares it.</small></p>

<form method="post" action="/billion-laughs.php">
    <label for="xml">XML</label>
    <textarea id="xml" name="xml" rows="14"><?= h($payload !== '' ? $payload : $default_payload) ?></textarea>
    <label><input type="checkbox" name="unsafe" value="1" <?= $enabled_entity_subst ? 'checked' : '' ?>> Enable LIBXML_NOENT (unsafe — opts in to entity expansion)</label>
    <button type="submit">Parse</button>
</form>

<?php if ($err): ?>
    <div class="alert">Parser error: <?= h($err) ?></div>
<?php elseif ($result): ?>
    <h2>Result</h2>
    <pre><?= h(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>
    <?php if (!$enabled_entity_subst): ?>
        <div class="ok">Entities were NOT expanded. The parser returned the literal text without inflating to 100k characters. This is the libxml default and it is what protects the modern PHP stack.</div>
    <?php else: ?>
        <div class="alert">Entities WERE expanded. <code>parsed_text_bytes</code> is the inflated length. In a real codebase, never pass LIBXML_NOENT against untrusted XML.</div>
    <?php endif; ?>
<?php endif; ?>

<p>See the spoke article <a href="https://techearl.com/billion-laughs-attack">billion laughs attack</a> for the full taxonomy and the XXE neighbour.</p>

<?php layout_close();
