<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'crypto.php';
requireApiLogin();
header('Content-Type: application/json; charset=utf-8');

$maxSize = 25 * 1024 * 1024;
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip'];
$file = $_FILES['file'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['error' => 'Choose a file to upload.']);
    exit;
}

$originalName = basename((string) $file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$size = (int) $file['size'];
if ($size <= 0 || $size > $maxSize || !in_array($extension, $allowedExtensions, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'File type or size is not allowed. Maximum size is 25 MB.']);
    exit;
}

$directory = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'files';
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    http_response_code(500);
    echo json_encode(['error' => 'File storage is unavailable.']);
    exit;
}

$id = bin2hex(random_bytes(16));
$storedPath = $directory . DIRECTORY_SEPARATOR . $id . '.bin';
if (!move_uploaded_file((string) $file['tmp_name'], $storedPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'The file could not be stored.']);
    exit;
}

$metadataFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'files.json';
$handle = fopen($metadataFile, 'c+');
if ($handle === false || !flock($handle, LOCK_EX)) {
    @unlink($storedPath);
    http_response_code(503);
    echo json_encode(['error' => 'File storage is temporarily unavailable.']);
    exit;
}
rewind($handle);
$files = json_decode(stream_get_contents($handle) ?: '[]', true);
$files = is_array($files) ? $files : [];
$mime = (string) ($file['type'] ?? 'application/octet-stream');
$files[] = [
    'id' => $id,
    'owner' => (string) $_SESSION['authenticated_user'],
    'name' => encryptChatValue($originalName),
    'mime' => $mime,
    'size' => $size,
    'path' => $id . '.bin',
];
ftruncate($handle, 0);
rewind($handle);
fwrite($handle, json_encode($files, JSON_UNESCAPED_SLASHES));
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

echo json_encode(['attachment' => ['id' => $id, 'name' => $originalName, 'mime' => $mime, 'size' => $size]]);