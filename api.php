<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'crypto.php';
requireApiLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$dataDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$messageFile = $dataDirectory . DIRECTORY_SEPARATOR . 'messages.json';
$stateFile = $dataDirectory . DIRECTORY_SEPARATOR . 'chat_state.json';
$typingFile = $dataDirectory . DIRECTORY_SEPARATOR . 'typing.json';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function deletedForUsers(array $message): array
{
    $deletedFor = $message['deleted_for'] ?? [];
    if (is_string($deletedFor)) {
        $deletedFor = json_decode(decryptChatValue($deletedFor), true);
    }
    return is_array($deletedFor) ? $deletedFor : [];
}

function presentMessage(array $message, string $currentUsername): array
{
    $message['name'] = decryptChatValue((string) ($message['name'] ?? ''));
    $message['message'] = decryptChatValue((string) ($message['message'] ?? ''));
    $message['deleted'] = (bool) ($message['deleted'] ?? false);
    if ($message['deleted']) {
        $message['message'] = 'This message was deleted';
    }
    $safeUsername = htmlspecialchars($currentUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $isOwner = $message['name'] === $safeUsername;
    $message['is_owner'] = $isOwner;
    $message['is_host'] = isHostUser($currentUsername);
    $message['can_modify'] = $isOwner || $message['is_host'];
    if (isset($message['attachment']) && is_array($message['attachment'])) {
        $message['attachment']['name'] = decryptChatValue((string) ($message['attachment']['name'] ?? 'file'));
    }
    return $message;
}

function readMessages(string $messageFile, string $currentUsername): array
{
    if (!is_file($messageFile)) {
        return [];
    }

    $contents = file_get_contents($messageFile);
    $messages = json_decode($contents ?: '[]', true);
    if (!is_array($messages)) {
        return [];
    }

    $visibleMessages = [];
    foreach ($messages as $message) {
        if (in_array($currentUsername, deletedForUsers($message), true)) {
            continue;
        }
        $visibleMessages[] = presentMessage($message, $currentUsername);
    }

    $messageLookup = [];
    foreach ($visibleMessages as $message) {
        $messageLookup[(int) $message['id']] = $message;
    }
    foreach ($visibleMessages as &$message) {
        $replyId = (int) ($message['reply_to'] ?? 0);
        if ($replyId > 0 && isset($messageLookup[$replyId])) {
            $message['reply_to_name'] = $messageLookup[$replyId]['name'];
            $message['reply_to_message'] = $messageLookup[$replyId]['message'];
        }
    }
    unset($message);
    return $visibleMessages;
}

function writeMessages($handle, array $messages): void
{
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($handle);
}

function nextMessageId(string $stateFile, int $fallback): int
{
    $handle = fopen($stateFile, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        return $fallback + 1;
    }
    rewind($handle);
    $state = json_decode(stream_get_contents($handle) ?: '{}', true);
    $nextId = max((int) ($state['next_id'] ?? 1), $fallback + 1);
    $state['next_id'] = $nextId + 1;
    writeMessages($handle, $state);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $nextId;
}

function updateTypingStatus(string $typingFile, string $username, bool $isTyping): bool
{
    $handle = fopen($typingFile, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        return false;
    }

    rewind($handle);
    $typingUsers = json_decode(stream_get_contents($handle) ?: '{}', true);
    $typingUsers = is_array($typingUsers) ? $typingUsers : [];
    $now = microtime(true);
    foreach ($typingUsers as $user => $lastSeen) {
        if ((float) $lastSeen < $now - 3) {
            unset($typingUsers[$user]);
        }
    }

    if ($isTyping) {
        $typingUsers[$username] = $now;
    } else {
        unset($typingUsers[$username]);
    }

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($typingUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

function getTypingUsers(string $typingFile, string $currentUsername): array
{
    if (!is_file($typingFile)) {
        return [];
    }

    $typingUsers = json_decode(file_get_contents($typingFile) ?: '{}', true);
    if (!is_array($typingUsers)) {
        return [];
    }

    $now = microtime(true);
    $activeUsers = [];
    foreach ($typingUsers as $username => $lastSeen) {
        if ($username !== $currentUsername && (float) $lastSeen >= $now - 3) {
            $activeUsers[] = $username;
        }
    }
    return $activeUsers;
}

if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0775, true) && !is_dir($dataDirectory)) {
    respond(['error' => 'The chat data directory could not be created.'], 500);
}

if (!is_file($messageFile)) {
    file_put_contents($messageFile, "[]", LOCK_EX);
}
if (!is_file($stateFile)) {
    file_put_contents($stateFile, '{"next_id":1}', LOCK_EX);
}
if (!is_file($typingFile)) {
    file_put_contents($typingFile, "{}", LOCK_EX);
}

if ($method === 'GET') {
    if (isset($_GET['typing'])) {
        respond(['users' => getTypingUsers($typingFile, (string) $_SESSION['authenticated_user'])]);
    }

    $after = max(0, (int) ($_GET['after'] ?? 0));
    $messages = readMessages($messageFile, (string) $_SESSION['authenticated_user']);
    $newMessages = array_values(array_filter($messages, static fn (array $message): bool => (int) ($message['id'] ?? 0) > $after));
    respond(['messages' => $newMessages]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    $name = (string) $_SESSION['authenticated_user'];

    $action = (string) ($input['action'] ?? 'send');

    if ($action === 'typing') {
        $isTyping = filter_var($input['isTyping'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!updateTypingStatus($typingFile, $name, $isTyping)) {
            respond(['error' => 'Typing status is temporarily unavailable.'], 503);
        }
        respond(['ok' => true]);
    }

    $handle = fopen($messageFile, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        respond(['error' => 'The chat is temporarily unavailable.'], 503);
    }
    rewind($handle);
    $contents = stream_get_contents($handle);
    $messages = json_decode($contents ?: '[]', true);
    $messages = is_array($messages) ? $messages : [];

    if ($action === 'clear_chat') {
        if (!isHostUser($name)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            respond(['error' => 'Only the host can clear the chat.'], 403);
        }
        writeMessages($handle, []);
        flock($handle, LOCK_UN);
        fclose($handle);
        respond(['ok' => true]);
    }

    if ($action === 'delete_message' || $action === 'highlight_message') {
        $messageId = (int) ($input['id'] ?? 0);
        $messageIndex = null;
        foreach ($messages as $index => $message) {
            if ((int) ($message['id'] ?? 0) === $messageId) {
                $messageIndex = $index;
                break;
            }
        }
        if ($messageIndex === null) {
            flock($handle, LOCK_UN);
            fclose($handle);
            respond(['error' => 'Message not found.'], 404);
        }

        $messageOwner = decryptChatValue((string) ($messages[$messageIndex]['name'] ?? ''));
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $isOwner = $messageOwner === $safeName;
        $isHost = isHostUser($name);

        if ($action === 'highlight_message') {
            if (!$isOwner && !$isHost) {
                flock($handle, LOCK_UN);
                fclose($handle);
                respond(['error' => 'You can only highlight your own messages.'], 403);
            }
            $messages[$messageIndex]['highlighted'] = isset($input['highlighted'])
                ? (bool) $input['highlighted']
                : !((bool) ($messages[$messageIndex]['highlighted'] ?? false));
        } else {
            $mode = (string) ($input['mode'] ?? 'for_me');
            if ($mode === 'for_everyone') {
                if (!$isOwner && !$isHost) {
                    flock($handle, LOCK_UN);
                    fclose($handle);
                    respond(['error' => 'You can only delete your own messages for everyone.'], 403);
                }
                $messages[$messageIndex]['deleted'] = true;
                $messages[$messageIndex]['message'] = encryptChatValue('');
            } elseif ($mode === 'for_me') {
                if (!$isOwner && !$isHost) {
                    flock($handle, LOCK_UN);
                    fclose($handle);
                    respond(['error' => 'You can only delete your own messages.'], 403);
                }
                $deletedFor = deletedForUsers($messages[$messageIndex]);
                if (!in_array($name, $deletedFor, true)) {
                    $deletedFor[] = $name;
                }
                $messages[$messageIndex]['deleted_for'] = encryptChatValue(json_encode($deletedFor));
            } else {
                flock($handle, LOCK_UN);
                fclose($handle);
                respond(['error' => 'Invalid delete mode.'], 422);
            }
        }

        writeMessages($handle, $messages);
        flock($handle, LOCK_UN);
        fclose($handle);
        respond(['ok' => true]);
    }

    $text = trim((string) ($input['message'] ?? ''));
    $attachment = $input['attachment'] ?? null;

    if ($text === '' && !is_array($attachment)) {
        respond(['error' => 'Message is required.'], 422);
    }
    if (mb_strlen($name) > 32 || mb_strlen($text) > 1000) {
        respond(['error' => 'The name or message is too long.'], 422);
    }

    $lastMessage = end($messages);
    $replyTo = max(0, (int) ($input['reply_to'] ?? 0));
    if ($replyTo > 0) {
        $replyExists = false;
        foreach ($messages as $existingMessage) {
            if ((int) ($existingMessage['id'] ?? 0) === $replyTo) {
                $replyExists = true;
                break;
            }
        }
        if (!$replyExists) {
            flock($handle, LOCK_UN);
            fclose($handle);
            respond(['error' => 'The message being replied to no longer exists.'], 422);
        }
    }
    $storedMessage = [
        'id' => nextMessageId($stateFile, (int) ($lastMessage['id'] ?? 0)),
        'name' => encryptChatValue(htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        'message' => encryptChatValue(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
    if (is_array($attachment) && preg_match('/^[a-f0-9]{32}$/', (string) ($attachment['id'] ?? ''))) {
        $storedMessage['attachment'] = [
            'id' => (string) $attachment['id'],
            'name' => encryptChatValue((string) ($attachment['name'] ?? 'file')),
            'mime' => (string) ($attachment['mime'] ?? 'application/octet-stream'),
            'size' => (int) ($attachment['size'] ?? 0),
        ];
    }
    if ($replyTo > 0) {
        $storedMessage['reply_to'] = $replyTo;
    }
    $messages[] = $storedMessage;
    $messages = array_slice($messages, -200);

    writeMessages($handle, $messages);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    respond(['message' => [
        'id' => $storedMessage['id'],
        'name' => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'message' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        'timestamp' => $storedMessage['timestamp'],
    ]], 201);
}

respond(['error' => 'Method not allowed.'], 405);