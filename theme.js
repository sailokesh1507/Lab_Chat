const themeToggle = document.querySelector('#themeToggle');
const themeIcon = document.querySelector('#themeIcon');
const themeLabel = document.querySelector('#themeLabel');
const savedTheme = localStorage.getItem('lab-chat-theme') || 'light';

function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    if (!themeToggle) return;

    themeToggle.setAttribute('aria-pressed', String(isDark));
    themeToggle.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    themeIcon.textContent = isDark ? '\u2600' : '\u263e';
    themeLabel.textContent = isDark ? 'Switch to light mode' : 'Switch to dark mode';
}

applyTheme(savedTheme);

themeToggle?.addEventListener('click', () => {
    const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('lab-chat-theme', nextTheme);
    applyTheme(nextTheme);
});