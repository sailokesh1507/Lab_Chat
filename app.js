const messagesElement = document.querySelector('#messages');
const emptyState = document.querySelector('#emptyState');
const nameInput = document.querySelector('#nameInput');
const messageInput = document.querySelector('#messageInput');
const messageForm = document.querySelector('#messageForm');
const sendButton = document.querySelector('#sendButton');
const fileInput = document.querySelector('#fileInput');
const fileStatus = document.querySelector('#fileStatus');
const statusText = document.querySelector('#statusText');
const connectionStatus = document.querySelector('#connectionStatus');
const memberCount = document.querySelector('#memberCount');
const typingIndicator = document.querySelector('#typingIndicator');
const typingText = document.querySelector('#typingText');
const replyBar = document.querySelector('#replyBar');
const replyAuthor = document.querySelector('#replyAuthor');
const replyPreview = document.querySelector('#replyPreview');
const cancelReplyButton = document.querySelector('#cancelReply');
const clearChatButton = document.querySelector('#clearChatButton');
const notificationToggle = document.querySelector('#notificationToggle');
const notificationIcon = document.querySelector('#notificationIcon');
const notificationLabel = document.querySelector('#notificationLabel');
const messageContextMenu = document.querySelector('#messageContextMenu');
const contextActionButtons = messageContextMenu.querySelectorAll('[data-context-action]');
document.body.appendChild(messageContextMenu);

let latestMessageId = 0;
let isSending = false;
let typingTimeout;
let replyTarget = null;
let contextMessageId = 0;
let hasLoadedMessages = false;
let pendingAttachment = null;
const messageCache = new Map();
const currentUsername = nameInput.dataset.username;

function setStatus(online) {
    statusText.textContent = online ? 'Live' : 'Offline';
    connectionStatus.classList.toggle('offline', !online);
    memberCount.textContent = online ? 'Shared with your lab' : 'Trying to reconnect...';
}

function formatMessageTime(message) {
    if (!message.timestamp) return message.time || '';
    return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(new Date(message.timestamp));
}

function notificationUnavailableReason() {
    if (!('Notification' in window)) return 'Notifications are not supported by this browser';
    if (!window.isSecureContext && !['localhost', '127.0.0.1'].includes(window.location.hostname)) {
        return 'Notifications require HTTPS on a LAN IP';
    }
    return '';
}

function updateNotificationControl() {
    if (!notificationToggle) return;
    const unavailableReason = notificationUnavailableReason();
    if (unavailableReason) {
        notificationToggle.disabled = true;
        notificationToggle.title = unavailableReason;
        notificationLabel.textContent = unavailableReason;
        return;
    }

    const enabled = Notification.permission === 'granted';
    const denied = Notification.permission === 'denied';
    notificationToggle.disabled = false;
    notificationToggle.setAttribute('aria-pressed', String(enabled));
    notificationToggle.title = denied ? 'Notifications are blocked in Chrome' : enabled ? 'Notifications enabled' : 'Enable notifications';
    notificationLabel.textContent = notificationToggle.title;
    notificationIcon.textContent = enabled ? '\uD83D\uDD14' : '\uD83D\uDD15';
}

function notificationText(value) {
    const element = document.createElement('textarea');
    element.innerHTML = value;
    return element.value;
}

function notifyNewMessage(message) {
    if (!hasLoadedMessages || message.name === currentUsername || !document.hidden || !('Notification' in window) || Notification.permission !== 'granted') return;
    const notification = new Notification(`${message.name} in Lab Chat`, {
        body: notificationText(message.message),
        tag: `lab-chat-${message.id}`,
        renotify: true,
    });
    notification.onclick = () => {
        window.focus();
        notification.close();
    };
}

function addMessage(message) {
    messageCache.set(Number(message.id), message);
    emptyState.hidden = true;
    const item = document.createElement('article');
    item.className = 'message';
    item.dataset.messageId = message.id;
    const replyMarkup = message.reply_to_message
        ? `<div class="reply-quote"><strong>${message.reply_to_name}</strong><span>${message.reply_to_message}</span></div>`
        : '';
    const attachmentMarkup = message.attachment
        ? `<a class="attachment-link" href="download.php?id=${encodeURIComponent(message.attachment.id)}"><span aria-hidden="true">&#128206;</span><span>${message.attachment.name}</span><small>${formatFileSize(message.attachment.size)}</small></a>`
        : '';
    item.innerHTML = `
        <div class="message-avatar">${message.name.charAt(0).toUpperCase()}</div>
        <div class="message-body">
            <div class="message-meta"><strong>${message.name}</strong><time>${formatMessageTime(message)}</time></div>
            ${replyMarkup}
            <p>${message.message}</p>
            ${attachmentMarkup}
        </div>`;
    if (message.highlighted) item.classList.add('highlighted');
    messagesElement.appendChild(item);
    messagesElement.scrollTop = messagesElement.scrollHeight;
}

function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

fileInput.addEventListener('change', async () => {
    const file = fileInput.files[0];
    if (!file) return;
    if (file.size > 25 * 1024 * 1024) {
        fileStatus.textContent = 'Maximum file size is 25 MB';
        fileInput.value = '';
        return;
    }
    fileStatus.textContent = 'Uploading...';
    const formData = new FormData();
    formData.append('file', file);
    try {
        const response = await fetch('upload.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Upload failed');
        pendingAttachment = data.attachment;
        fileStatus.textContent = `${pendingAttachment.name} attached`;
    } catch (error) {
        fileStatus.textContent = error.message;
        fileInput.value = '';
    }
});

async function loadMessages(reset = false) {
    try {
        if (reset) {
            latestMessageId = 0;
            messageCache.clear();
            messagesElement.replaceChildren(emptyState);
            emptyState.hidden = false;
        }
        const response = await fetch(`api.php?after=${latestMessageId}`, { cache: 'no-store' });
        if (!response.ok) throw new Error('Request failed');
        const data = await response.json();
        const shouldNotify = hasLoadedMessages && !reset;
        data.messages.forEach((message) => {
            latestMessageId = Math.max(latestMessageId, Number(message.id));
            addMessage(message);
            if (shouldNotify) notifyNewMessage(message);
        });
        hasLoadedMessages = true;
        setStatus(true);
    } catch (error) {
        setStatus(false);
    }
}

notificationToggle?.addEventListener('click', async () => {
    if (notificationUnavailableReason()) return;
    if (Notification.permission === 'default') await Notification.requestPermission();
    updateNotificationControl();
});
updateNotificationControl();

function setReplyTarget(message) {
    replyTarget = message;
    replyAuthor.textContent = message.name;
    replyPreview.textContent = message.deleted ? 'This message was deleted' : message.message;
    replyBar.hidden = false;
    messageInput.focus();
}

function clearReplyTarget() {
    replyTarget = null;
    replyBar.hidden = true;
    replyAuthor.textContent = '';
    replyPreview.textContent = '';
}

async function messageAction(action, messageId, extra = {}) {
    const response = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, id: messageId, ...extra }),
    });
    if (!response.ok) throw new Error('Message action failed');
    await loadMessages(true);
}

function closeContextMenu() {
    messageContextMenu.hidden = true;
    contextMessageId = 0;
}

function openContextMenu(event, message) {
    event.preventDefault();
    contextMessageId = Number(message.id);
    contextActionButtons.forEach((button) => {
        const action = button.dataset.contextAction;
        const visible = action === 'reply'
            ? !message.deleted
            : !message.deleted && message.can_modify;
        button.hidden = !visible;
        if (action === 'highlight') {
            button.textContent = message.highlighted ? 'Unhighlight' : 'Highlight';
        }
    });
    messageContextMenu.hidden = false;
    const left = Math.min(event.clientX + 8, window.innerWidth - messageContextMenu.offsetWidth - 10);
    const top = Math.min(event.clientY + 8, window.innerHeight - messageContextMenu.offsetHeight - 10);
    messageContextMenu.style.left = `${Math.max(10, left)}px`;
    messageContextMenu.style.top = `${Math.max(10, top)}px`;
}

async function handleMessageAction(action, messageId) {
    const message = messageCache.get(messageId);
    if (!message) return;

    if (action === 'reply') {
        setReplyTarget(message);
    } else if (action === 'highlight') {
        await messageAction('highlight_message', messageId, { highlighted: !message.highlighted });
    } else if (action === 'delete-me' && window.confirm('Delete this message for you?')) {
        await messageAction('delete_message', messageId, { mode: 'for_me' });
    } else if (action === 'delete-everyone' && window.confirm('Delete this message for everyone?')) {
        await messageAction('delete_message', messageId, { mode: 'for_everyone' });
    }
}

messagesElement.addEventListener('contextmenu', (event) => {
    const messageElement = event.target.closest('[data-message-id]');
    if (!messageElement) return;
    const message = messageCache.get(Number(messageElement.dataset.messageId));
    if (message) openContextMenu(event, message);
});

messageContextMenu.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-context-action]');
    if (!button || !contextMessageId) return;
    const action = button.dataset.contextAction;
    const messageId = contextMessageId;
    closeContextMenu();
    try {
        await handleMessageAction(action, messageId);
    } catch (error) {
        setStatus(false);
    }
});

document.addEventListener('click', (event) => {
    if (!messageContextMenu.contains(event.target)) closeContextMenu();
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeContextMenu();
});
window.addEventListener('resize', closeContextMenu);

cancelReplyButton.addEventListener('click', clearReplyTarget);

clearChatButton?.addEventListener('click', async () => {
    if (!window.confirm('Clear the entire chat for everyone?')) return;
    try {
        await messageAction('clear_chat', 0);
        clearReplyTarget();
    } catch (error) {
        setStatus(false);
    }
});

function updateTypingIndicator(users) {
    if (users.length === 0) {
        typingIndicator.hidden = true;
        return;
    }

    typingText.textContent = users.length === 1
        ? `${users[0]} is typing...`
        : `${users.join(', ')} are typing...`;
    typingIndicator.hidden = false;
}

async function loadTypingStatus() {
    try {
        const response = await fetch('api.php?typing=1', { cache: 'no-store' });
        if (!response.ok) throw new Error('Typing status failed');
        const data = await response.json();
        updateTypingIndicator(data.users || []);
    } catch (error) {
        typingIndicator.hidden = true;
    }
}

function notifyTyping(isTyping) {
    fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'typing', isTyping }),
    }).catch(() => {});
}

messageForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = messageInput.value.trim();
    if (!currentUsername || (!message && !pendingAttachment) || isSending) return;

    isSending = true;
    sendButton.disabled = true;
    clearTimeout(typingTimeout);
    notifyTyping(false);
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message, attachment: pendingAttachment, reply_to: replyTarget?.id || 0 }),
        });
        if (!response.ok) throw new Error('Message could not be sent');
        messageInput.value = '';
        messageInput.style.height = 'auto';
        clearReplyTarget();
        pendingAttachment = null;
        fileInput.value = '';
        fileStatus.textContent = '';
        await loadMessages();
    } catch (error) {
        setStatus(false);
    } finally {
        isSending = false;
        sendButton.disabled = false;
        messageInput.focus();
    }
});

messageInput.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        messageForm.requestSubmit();
    }
});

messageInput.addEventListener('input', () => {
    messageInput.style.height = 'auto';
    messageInput.style.height = `${Math.min(messageInput.scrollHeight, 120)}px`;
    clearTimeout(typingTimeout);
    notifyTyping(messageInput.value.trim() !== '');
    typingTimeout = setTimeout(() => notifyTyping(false), 2500);
});

messageInput.addEventListener('blur', () => {
    clearTimeout(typingTimeout);
    notifyTyping(false);
});

loadMessages();
loadTypingStatus();
setInterval(loadMessages, 1500);
setInterval(loadTypingStatus, 1000);