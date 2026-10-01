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
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!validCsrfToken($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif (!authenticateUser($username, $password)) {
        $error = 'Incorrect username or password.';
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
    <title>Sign in | Lab Chat</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-panel" aria-labelledby="login-title">
            <div class="brand-mark" aria-hidden="true">LC</div>
            <p class="eyebrow">Private lab room</p>
            <h1 id="login-title">Welcome back</h1>
            <p class="auth-copy">Sign in to continue to the shared chat.</p>
            <?php if ($error !== ''): ?>
                <p class="login-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
            <?php endif; ?>
            <form class="login-form" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" autocomplete="username" required autofocus>
                <label for="password">Password</label>
                <div class="password-field">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" class="password-toggle" data-target="password" aria-label="Show password" title="Show password">Show</button>
                </div>
                <button type="submit">Sign in <span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">New to the room? <a href="register.php">Create an account</a></p>
        </section>
    </main>
    <script src="theme.js?v=3"></script>
    <script src="auth.js"></script>
</body>
</html>