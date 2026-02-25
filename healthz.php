<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$version = '2.2.0';
$dataDir = __DIR__ . '/data';
$dataDirWritable = is_dir($dataDir) && is_writable($dataDir);

http_response_code($dataDirWritable ? 200 : 503);
echo json_encode([
    'status' => $dataDirWritable ? 'ok' : 'degraded',
    'service' => 'scrubber',
    'version' => $version,
    'time_utc' => gmdate('c'),
    'checks' => [
        'data_dir_writable' => $dataDirWritable
    ]
], JSON_UNESCAPED_SLASHES);
