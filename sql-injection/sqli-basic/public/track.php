<?php
/*
 * Two analytics paths, both intentionally vulnerable but in different ways.
 *
 * Path A: /track?ref=...
 *   Stores `ref` to the `tracking` table via a SAFE parameterised statement.
 *   The bug is in /admin/reports (NOT implemented in this lab to keep scope
 *   tight), which reads the stored ref back and concatenates it into a
 *   different query. That is the second-order injection pattern.
 *
 * Path B: /track?page=...
 *   Records a page view in the `page_views` table with `User-Agent` and
 *   `X-Tenant-Id` header values concatenated directly into the INSERT.
 *   This is the User-Agent / custom-header SQLi target referenced in the
 *   tutorial's Step 12 and in the User-Agent vector deep-dive.
 *
 * Real applications make exactly these mistakes in analytics middleware.
 */

require_once __DIR__ . '/shared/db.php';
$conn = db();

// Path A: second-order entry point. Parameterised by design.
if (isset($_GET['ref'])) {
    $stmt = $conn->prepare("INSERT INTO tracking (ref) VALUES (?)");
    $stmt->bind_param('s', $_GET['ref']);
    $stmt->execute();
    header('Content-Type: text/plain');
    echo "ok\n";
    exit;
}

// Path B: analytics with User-Agent + X-Tenant-Id concatenation.
if (isset($_GET['page'])) {
    $page = $_GET['page'];
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $tenant = $_SERVER['HTTP_X_TENANT_ID'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Vulnerable: every value concatenated rather than bound.
    // The fix is `INSERT INTO page_views (path, user_agent, tenant_id, ip) VALUES (?, ?, ?, ?)`.
    $sql = "INSERT INTO page_views (path, user_agent, tenant_id, ip)
            VALUES ('$page', '$ua', '$tenant', '$ip')";
    $ok = $conn->query($sql);

    header('Content-Type: text/plain');
    if ($ok) {
        echo "tracked\n";
    } else {
        http_response_code(500);
        echo "track failed: " . $conn->error . "\n";
    }
    exit;
}

http_response_code(400);
header('Content-Type: text/plain');
echo "usage: /track?ref=<value>   or   /track?page=<path>\n";
