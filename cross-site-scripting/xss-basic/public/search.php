<?php
/*
 * VULNERABLE: reflected XSS.
 *
 * The query parameter `q` is echoed straight into the result heading without
 * htmlspecialchars(). The textbook payload:
 *
 *   /search.php?q=<script>alert(1)</script>
 *
 * fires immediately because the <script> tag is parsed by the browser as
 * part of the document. The form's value attribute is still escaped via h()
 * because that sink (attribute context) needs entity encoding to avoid
 * breaking out of the quote, and the article wants the unsafe sink to be
 * the heading, not the input value.
 *
 * Note: there is no actual search backend wired up. The vulnerability is
 * the reflection, not the search results, so the page just echoes the term
 * back. That matches a surprisingly large class of real-world reflected
 * XSS bugs: "no results for X" pages, 404 handlers, error messages.
 */

require_once __DIR__ . '/shared/db.php';

$q = $_GET['q'] ?? '';

layout_open('Search');
echo '<h1>Search</h1>';

echo '<form method="get" action="/search.php">';
echo '<label>Search</label>';
echo '<input type="text" name="q" value="' . h($q) . '" autofocus>';
echo '<button type="submit">Search</button>';
echo '</form>';

if ($q !== '') {
    // VULNERABLE: $q is interpolated straight into HTML. Reflected XSS sink.
    echo '<h2>Results for: ' . $q . '</h2>';
    echo '<p>No matches found.</p>';
}

layout_close();
