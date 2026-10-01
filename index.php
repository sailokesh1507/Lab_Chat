<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
requirePageLogin();
$currentUsername = (string) $_SESSION['authenticated_user'];
$isHost = isHostUser($currentUsername);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lab Chat</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
    <main class="app-shell">
        <section class="chat-panel" aria-label="Lab chat">
            <header class="chat-header">
                <div class="brand-mark" aria-hidden="true">LC</div>
                <div class="brand-copy">
                    <p class="eyebrow">Shared room</p>
                    <h1>Lab Chat</h1>
                </div>
                <div class="connection-status" id="connectionStatus">
                    <span class="status-dot"></span>
                    <span id="statusText">Connecting</span>
                </div>
                <button type="button" class="theme-toggle" id="themeToggle" aria-pressed="false" title="Switch to dark mode">
                    <span id="themeIcon" aria-hidden="true">&#9790;</span>
                    <span class="sr-only" id="themeLabel">Switch to dark mode</span>
                </button>
                <button type="button" class="notification-toggle" id="notificationToggle" aria-pressed="false" title="Enable notifications">
                    <span id="notificationIcon" aria-hidden="true">&#128276;</span>
                    <span class="sr-only" id="notificationLabel">Enable notifications</span>
                </button>
                <?php if ($isHost): ?>
                    <button type="button" class="clear-chat-button" id="clearChatButton">Clear chat</button>
                <?php endif; ?>
                <a class="logout-link" href="logout.php">Log out</a>
            </header>

            <div class="identity-bar">
                <label for="nameInput">You are</label>
                <input id="nameInput" type="text" value="<?= htmlspecialchars($currentUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-username="<?= htmlspecialchars($currentUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" maxlength="32" autocomplete="username" readonly>
                <span class="member-count" id="memberCount">Ready to join</span>
            </div>

            <div class="messages" id="messages" aria-live="polite" aria-label="Messages">
                <div class="empty-state" id="emptyState">
                    <div class="empty-icon" aria-hidden="true">+</div>
                    <h2>Start the conversation</h2>
                    <p>Messages from everyone connected to this room will appear here.</p>
                </div>
            </div>

            <div class="typing-indicator" id="typingIndicator" aria-live="polite" hidden>
                <span class="typing-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                <span id="typingText"></span>
            </div>

            <div class="reply-bar" id="replyBar" hidden>
                <div>
                    <strong>Replying to <span id="replyAuthor"></span></strong>
                    <p id="replyPreview"></p>
                </div>
                <button type="button" id="cancelReply" aria-label="Cancel reply">×</button>
            </div>

            <div class="context-menu" id="messageContextMenu" role="menu" hidden>
                <button type="button" data-context-action="reply" role="menuitem">Reply</button>
                <button type="button" data-context-action="highlight" role="menuitem">Highlight</button>
                <button type="button" data-context-action="delete-me" role="menuitem">Delete for me</button>
                <button type="button" data-context-action="delete-everyone" role="menuitem">Delete for everyone</button>
            </div>

            <form class="composer" id="messageForm">
                <label class="sr-only" for="messageInput">Message</label>
                <label class="file-button" title="Attach a file">
                    <span aria-hidden="true">+</span>
                    <input id="fileInput" type="file" hidden>
                </label>
                <span class="file-status" id="fileStatus" aria-live="polite"></span>
                <textarea id="messageInput" rows="1" maxlength="1000" placeholder="Write a message..." autocomplete="off"></textarea>
                <button type="submit" id="sendButton" aria-label="Send message" title="Send message">
                    <span>Send</span>
                    <span aria-hidden="true">↗</span>
                </button>
            </form>
            <p class="composer-hint">Press Enter to send · Shift + Enter for a new line</p>
        </section>
    </main>
    <script src="theme.js?v=3"></script>
    <script src="app.js"></script>
</body>
</html>