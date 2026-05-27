<?php
/**
 * PHAR-deserialization demo.
 *
 * The historical bug (Sam Thomas, BlackHat USA 2018): any filesystem
 * function (`file_exists`, `is_file`, `file_get_contents`, `fopen`,
 * `filesize`...) called with a `phar://` stream-wrapper URL caused PHP
 * to parse the archive's manifest and unserialize its metadata block.
 * That gave attackers a deserialize sink in code that never called
 * unserialize() at all — the developer just thought they were checking
 * whether an uploaded file exists.
 *
 * PHP 8.0 hardened this surface significantly. From the 8.0 changelog:
 * "Phar metadata is no longer unserialized by default. Phar::getMetadata
 * now requires explicit allowed_classes option to deserialize objects."
 * On the lab's PHP 8.2 runtime, `file_exists("phar://...")` no longer
 * fires the gadget. Calling `Phar::getMetadata()` without arguments
 * returns a `__PHP_Incomplete_Class` (inert — no magic methods).
 *
 * To still demonstrate the gadget firing, this endpoint accepts an
 * `?unsafe=1` query parameter that opts back into the dangerous
 * behaviour by calling `getMetadata(['allowed_classes' => true])`.
 * That is exactly the API surface an application opts into when it
 * "needs to read PHAR metadata", which is the realistic shape of the
 * bug today: it has moved from "any file_exists is a sink" to "any
 * code that calls getMetadata without an allow-list is a sink".
 */

require_once '/var/www/src/Logger.php';

header('Content-Type: text/plain');

$file   = $_GET['file']   ?? '';
$unsafe = isset($_GET['unsafe']) && $_GET['unsafe'] === '1';

if ($file === '') {
    echo "usage: /phar_demo.php?file=<path>[&unsafe=1]\n";
    echo "\n";
    echo "the handler runs file_exists() on whatever you pass.\n";
    echo "if unsafe=1, it also opens the archive with Phar and calls\n";
    echo "getMetadata(['allowed_classes' => true]), which unserializes\n";
    echo "the manifest metadata into live objects (gadget fires).\n";
    echo "\n";
    echo "try: /phar_demo.php?file=phar:///var/www/html/uploads/avatar.phar\n";
    echo "try: /phar_demo.php?file=/var/www/html/uploads/avatar.phar&unsafe=1\n";
    exit;
}

// Historical sink. On PHP 7.x this alone fired the gadget. On PHP 8.0+
// it does not, because Phar metadata no longer auto-unserializes through
// the stream wrapper.
if (file_exists($file)) {
    echo "file_exists(" . $file . ") = true\n";
} else {
    echo "file_exists(" . $file . ") = false\n";
}

// Modern sink. Any code that opens a PHAR and asks for its metadata
// with allowed_classes=true (or unset on older PHP) re-introduces the
// classic primitive. This is the shape the bug has on PHP 8.x.
if ($unsafe) {
    try {
        $phar = new Phar($file);
        $meta = $phar->getMetadata(['allowed_classes' => true]);
        echo "metadata type: " . (is_object($meta) ? get_class($meta) : gettype($meta)) . "\n";
    } catch (Throwable $e) {
        echo "phar open failed: " . $e->getMessage() . "\n";
    }
}
