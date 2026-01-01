<?php
declare(strict_types=1);

// Generates an APP_KEY suitable for app/Support/Crypto.php (32 bytes base64).
// Usage: php scripts/generate_app_key.php

$bytes = random_bytes(32);
echo 'APP_KEY=base64:' . base64_encode($bytes) . "\n";
