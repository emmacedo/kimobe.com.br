(function () {
    var match = document.cookie.match(/(^|; )appearance=([^;]*)/);
    var appearance = match ? decodeURIComponent(match[2]) : 'system';

    if (appearance === 'system') {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    }
})();
