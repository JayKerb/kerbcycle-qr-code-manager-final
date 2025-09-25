(function (window) {
    if (!window.KC_OSRM) {
        window.KC_OSRM = {};
    }

    var callbacks = [];
    var isReady = false;
    var pollTimer = null;

    function flushCallbacks() {
        var callback;

        while ((callback = callbacks.shift())) {
            try {
                callback(window.KC_OSRM);
            } catch (error) {
                if (window.console && typeof window.console.error === 'function') {
                    window.console.error('KC_OSRM.ready callback failed', error);
                }
            }
        }
    }

    function checkReady() {
        if (isReady) {
            return;
        }

        if (window.L && window.L.Routing) {
            isReady = true;

            if (pollTimer) {
                window.clearInterval(pollTimer);
                pollTimer = null;
            }

            flushCallbacks();
        }
    }

    function ensurePolling() {
        if (isReady || pollTimer) {
            checkReady();
            return;
        }

        pollTimer = window.setInterval(checkReady, 50);
        checkReady();
    }

    window.KC_OSRM.ready = function (callback) {
        if (typeof callback !== 'function') {
            return;
        }

        callbacks.push(callback);

        if (isReady) {
            flushCallbacks();
        } else {
            ensurePolling();
        }
    };

    if (document.readyState === 'complete') {
        ensurePolling();
    } else {
        window.addEventListener('load', ensurePolling);
    }
})(window);
