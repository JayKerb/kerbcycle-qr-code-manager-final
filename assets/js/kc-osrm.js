(function (window) {
    if (!window.KC_OSRM) {
        window.KC_OSRM = {};
    }

    window.KC_OSRM.ready = function (callback) {
        if (typeof callback === 'function') {
            callback(window.KC_OSRM);
        }
    };
})(window);
