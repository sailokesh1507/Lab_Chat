<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'crypto.php';
requireApiLogin();

$id = (string) ($_GET['id'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
    http_response_code(404);
    exit;
}
$metadataFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'files.json';
$files = json_decode(is_file($metadataFile) ? file_get_contents($metadataFile) : '[]', true);
$record = null;
foreach (is_array($files) ? $files : [] as $file) {
    if (($file['id'] ?? '') === $id) {
        $record = $file;
        break;
    }
}
$path = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $id . '.bin';
if (!is_array($record) || !is_file($path)) {
    http_response_code(404);
    exit;
}
$name = decryptChatValue((string) ($record['name'] ?? 'download.bin'));
header('Content-Type: ' . ($record['mime'] ?? 'application/octet-stream'));
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . addcslashes($name, "\\\"") . '"');
readfile($path);