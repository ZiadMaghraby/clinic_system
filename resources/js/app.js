document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !window.confirm(message)) event.preventDefault();
});
document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
