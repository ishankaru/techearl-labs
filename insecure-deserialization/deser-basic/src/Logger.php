<?php
/**
 * Logger — the gadget class.
 *
 * Real-world Laravel/Symfony/WordPress gadget chains are bigger than this:
 * they string together half a dozen classes via magic methods to reach a
 * sink like `Runtime.exec` / `file_put_contents` / `eval`. The shape of
 * this class is the same; it just collapses the chain into one node so
 * the lab stays readable.
 *
 * The sink is `__destruct`: it runs at garbage-collection time, which
 * is at the end of the PHP request for any object instantiated by
 * `unserialize`. The fields are attacker-controlled because `unserialize`
 * restores them from the byte stream verbatim.
 */
class Logger
{
    /** @var string Path the destructor writes into. */
    public $logFile = '/tmp/legitimate-log.txt';

    /** @var string Line the destructor appends. */
    public $payload = "boot\n";

    public function __destruct()
    {
        // Classic file-write sink. The attacker controls both the path
        // and the content, so this single magic method is enough to
        // demonstrate the primitive.
        file_put_contents($this->logFile, $this->payload, FILE_APPEND);
    }
}
