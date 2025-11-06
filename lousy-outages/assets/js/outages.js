(function () {
    if ('undefined' === typeof window) {
        return;
    }
    window.setInterval(function () {
        window.location.reload();
    }, 300000);
})();
