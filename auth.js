document.querySelectorAll('[data-toggle-password]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const passwordInput = document.getElementById(toggle.dataset.target);
        const isVisible = passwordInput.type === 'text';
        passwordInput.type = isVisible ? 'password' : 'text';
        toggle.textContent = isVisible ? 'Show' : 'Hide';
        toggle.setAttribute('aria-label', `${isVisible ? 'Show' : 'Hide'} password`);
        toggle.setAttribute('title', `${isVisible ? 'Show' : 'Hide'} password`);
    });
});