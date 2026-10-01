<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

function usersFile(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'users.json';
}

function registeredUsers(): array
{
    $file = usersFile();
    if (!is_file($file)) {
        return [];
    }

    $users = json_decode(file_get_contents($file) ?: '[]', true);
    return is_array($users) ? $users : [];
}

function usernameIsTaken(string $username): bool
{
    if (hash_equals(CHAT_USERNAME, $username)) {
        return true;
    }

    foreach (registeredUsers() as $user) {
        if (is_array($user) && ($user['username'] ?? '') === $username) {
            return true;
        }
    }
    return false;
}

function authenticateUser(string $username, string $password): bool
{
    if (hash_equals(CHAT_USERNAME, $username) && password_verify($password, CHAT_PASSWORD_HASH)) {
        return true;
    }

    foreach (registeredUsers() as $user) {
        if (($user['username'] ?? '') === $username && password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return true;
        }
    }
    return false;
}

function isHostUser(string $username): bool
{
    return hash_equals(CHAT_USERNAME, $username);
}

function registerUser(string $username, string $password): bool
{
    $file = usersFile();
    $handle = fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        return false;
    }

    rewind($handle);
    $users = json_decode(stream_get_contents($handle) ?: '[]', true);
    $users = is_array($users) ? $users : [];
    foreach ($users as $user) {
        if (is_array($user) && ($user['username'] ?? '') === $username) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }
    }

    $users[] = [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($users, JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return true;
}

function startChatSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function isLoggedIn(): bool
{
    startChatSession();
    return isset($_SESSION['authenticated_user']);
}

function csrfToken(): string
{
    startChatSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validCsrfToken(string $token): bool
{
    startChatSession();
    return $token !== '' && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requirePageLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireApiLogin(): void
{
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Login required.']);
        exit;
    }
}