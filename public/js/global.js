document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('table tbody tr');
    rows.forEach((row, index) => {
        row.style.animation = `fadeInUp .25s ease ${index * 0.02}s both`;
    });
});
