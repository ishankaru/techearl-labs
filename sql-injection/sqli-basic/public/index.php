<?php
require __DIR__ . '/shared/db.php';

// Route the bare-path requests for /product and /track without needing
// .htaccess. Apache will hit index.php for /, and these checks let us route
// /product?id=1, /track?ref=..., /track?page=... from the same handler.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rtrim($path, '/') ?: '/';

if ($path === '/product') {
    require __DIR__ . '/product.php';
    exit;
}
if ($path === '/track') {
    require __DIR__ . '/track.php';
    exit;
}

// Default: render the product catalogue.
$conn = db();
$result = $conn->query("SELECT id, name, description, price FROM products ORDER BY id");

layout_open('Catalogue');
echo '<h1>sqli-basic — product catalogue</h1>';
echo '<p><small class="note">Deliberately vulnerable lab. See README.</small></p>';
while ($row = $result->fetch_assoc()) {
    echo '<div class="product">';
    echo '<h2><a href="/product?id=' . (int)$row['id'] . '">' . h($row['name']) . '</a></h2>';
    echo '<div>' . h($row['description']) . '</div>';
    echo '<div class="price">$' . number_format((float)$row['price'], 2) . '</div>';
    echo '</div>';
}
layout_close();
