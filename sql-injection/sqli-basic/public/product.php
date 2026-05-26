<?php
/*
 * VULNERABLE: numeric concat into a SELECT.
 *
 * id is taken from the query string and dropped straight into the WHERE
 * clause. Every variant works: boolean, error, time, UNION (3 columns).
 * The base query selects 3 columns from products, matching the article's
 * "ORDER BY 3 works, ORDER BY 4 errors" walkthrough.
 */

require_once __DIR__ . '/shared/db.php';
$conn = db();

$id = $_GET['id'] ?? '1';

$sql = "SELECT id, name, description FROM products WHERE id = $id";
$result = $conn->query($sql);

layout_open('Product');
echo '<h1>Product</h1>';

if ($result === false) {
    // Error-based variant: MySQL errors get echoed verbatim. Realistic for
    // misconfigured production apps that leave display_errors on.
    echo '<div class="alert">DB error: ' . h($conn->error) . '</div>';
} else {
    $rows = 0;
    while ($row = $result->fetch_assoc()) {
        echo '<div class="product">';
        echo '<h2>' . h($row['name']) . '</h2>';
        echo '<div>' . h($row['description']) . '</div>';
        echo '</div>';
        $rows++;
    }
    if ($rows === 0) {
        echo '<div class="alert">No product found for id = ' . h((string)$id) . '</div>';
    }
}

echo '<p><a href="/">Back to catalogue</a></p>';
layout_close();
