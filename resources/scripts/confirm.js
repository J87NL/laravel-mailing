(function () {
    var form = document.querySelector('form[data-mailing-auto-submit]');

    if (!form) {
        return;
    }

    var submit = function () {
        var button = form.querySelector('button[type="submit"]');

        if (button) {
            button.disabled = true;
        }

        form.submit();
    };

    if (document.visibilityState === 'visible') {
        submit();

        return;
    }

    document.addEventListener('visibilitychange', function onVisible() {
        if (document.visibilityState !== 'visible') {
            return;
        }

        document.removeEventListener('visibilitychange', onVisible);
        submit();
    });
})();
