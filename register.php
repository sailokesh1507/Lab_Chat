<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

startChatSession();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = strtolower(trim((string) ($_POST['username'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!validCsrfToken($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!preg_match('/^[a-z0-9._-]{3,24}$/', $username)) {
        $error = 'Use 3-24 lowercase letters, numbers, dots, dashes, or underscores.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must be at least 8 characters.';
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } elseif (usernameIsTaken($username)) {
        $error = 'That username is already registered.';
    } elseif (!registerUser($username, $password)) {
        $error = 'Registration could not be completed. Please try again.';
    } else {
        session_regenerate_id(true);
        $_SESSION['authenticated_user'] = $username;
        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | Lab Chat</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-panel" aria-labelledby="register-title">
            <div class="brand-mark" aria-hidden="true">LC</div>
            <p class="eyebrow">Private lab room</p>
            <h1 id="register-title">Join the room</h1>
            <p class="auth-copy">Create your own account for the shared chat.</p>
            <?php if ($error !== ''): ?>
                <p class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endif; ?>
            <form class="login-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="<?= htmlspecialchars($username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" maxlength="24" autocomplete="username" required autofocus>
                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password" title="Show password">Show</button>
                </div>
                <label for="password_confirmation">Confirm password</label>
                <div class="password-field">
                    <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-target="password_confirmation" aria-label="Show password confirmation" title="Show password confirmation">Show</button>
                </div>
                <button type="submit">Create account <span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">Already registered? <a href="login.php">Sign in</a></p>
        </section>
    </main>
    <script src="theme.js?v=3"></script>
    <script src="auth.js"></script>
</body>
</html>