(function(){
  function parseLatLon(str){
    var p = (str||'').split(',').map(function(n){ return parseFloat(n); });
    return [p[0], p[1]]; // [lat, lon]
  }
  function showMsg(el, msg){
    el.innerHTML = '<div style="padding:.5rem;border:1px solid #e33;background:#fee;color:#900;font:14px/1.4 system-ui,Arial">'+msg+'</div>';
  }
  function initOne(cfg){
    var el = document.getElementById(cfg.id);
    if (!el) return;
    if (!window.L){ showMsg(el, 'Leaflet not loaded'); return; }
    if (!window.L.Routing){ showMsg(el, 'Leaflet Routing Machine not loaded'); return; }
    if (!window.KC_OSRM || !KC_OSRM.base){ showMsg(el, 'KC_OSRM config missing'); return; }

    var start = parseLatLon(cfg.start), end = parseLatLon(cfg.end);
    try {
      var map = L.map(el).setView(start, cfg.zoom || 12);
      window._kcMap = map;
      L.tileLayer(KC_OSRM.tileUrl, { attribution: KC_OSRM.tileAttrib }).addTo(map);
      L.Routing.control({
        waypoints: [ L.latLng(start[0], start[1]), L.latLng(end[0], end[1]) ],
        router: L.Routing.osrmv1({ serviceUrl: KC_OSRM.base }) // ends with /route/v1
      }).on('routingerror', function(e){
        var msg = 'Routing failed';
        try { if (e && e.error && e.error.message) msg += ': ' + e.error.message; } catch(_){}
        showMsg(el, msg + '. Check endpoint/profile/CORS.');
      }).addTo(map);

      // Hidden-tab resilience
      setTimeout(function(){ map.invalidateSize(); }, 50);
      document.addEventListener('visibilitychange', function(){ setTimeout(function(){ map.invalidateSize(); }, 50); });
      window.addEventListener('um_tab_shown', function(){ setTimeout(function(){ map.invalidateSize(); }, 50); });
    } catch(e){
      showMsg(el, 'Map init error: ' + e.message);
    }
  }

  function drain(){
    var q = window.KC_OSRM_QUEUE || [];
    for (var i=0;i<q.length;i++) initOne(q[i]);
    window.KC_OSRM_QUEUE = []; // clear
  }

  // Run after DOM ready
  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', drain);
  } else {
    drain();
  }
})();