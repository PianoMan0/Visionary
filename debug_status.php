<?php
// Quick debug page: shows current session and last lines of api_debug.log
require_once __DIR__ . '/auth.php';
header('Content-Type: text/plain; charset=utf-8');
echo "SESSION:\n";
print_r($_SESSION);
echo "\nCURRENT_USER (from current_user()):\n";
print_r(current_user());
echo "\nPHP SAPI: " . php_sapi_name() . "\n";
echo "\nLast api_debug.log (if exists):\n";
$log = __DIR__ . '/data/api_debug.log';
if (file_exists($log)) {
    $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $last = array_slice($lines, -200);
    echo implode("\n", $last);
} else {
    echo "(no log file)\n";
}
