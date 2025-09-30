(function () {
  function parseLatLon(str) {
    var p = (str || "").split(",").map(function (n) {
      return parseFloat(n);
    });
    return [p[0], p[1]]; // [lat, lon]
  }

  function showMsg(el, msg) {
    el.innerHTML =
      '<div style="padding:.5rem;border:1px solid #e33;background:#fee;color:#900;font:14px/1.4 system-ui,Arial">' +
      msg +
      "</div>";
  }

  function getTripBase(url) {
    if (!url) return "";
    return url.replace(/\/route\/v1\/?$/, "/trip/v1");
  }

  function initOne(cfg) {
    var el = document.getElementById(cfg.id);
    if (!el) return;

    if (!window.L) {
      showMsg(el, "Leaflet not loaded");
      return;
    }
    if (!window.L.Routing) {
      showMsg(el, "Leaflet Routing Machine not loaded");
      return;
    }
    if (!window.KC_OSRM || !KC_OSRM.base) {
      showMsg(el, "KC_OSRM config missing");
      return;
    }

    var start = parseLatLon(cfg.start);
    var end = parseLatLon(cfg.end);

    var parent = el.parentNode;
    if (!parent) return;

    var wrapper = document.createElement("div");
    wrapper.className = "kc-osrm-wrapper";
    wrapper.style.display = "flex";
    wrapper.style.flexDirection = "column";

    var toolbar = document.createElement("div");
    toolbar.className = "kc-osrm-toolbar";
    toolbar.style.display = "flex";
    toolbar.style.flexWrap = "wrap";
    toolbar.style.gap = "0.5rem";
    toolbar.style.alignItems = "center";
    toolbar.style.marginBottom = "0.5rem";

    function makeButton(label, priority) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.textContent = label;
      btn.className =
        "button " + (priority === "primary" ? "button-primary" : "button-secondary");
      return btn;
    }

    function makeToggle(label, checked) {
      var wrapperEl = document.createElement("label");
      wrapperEl.style.display = "inline-flex";
      wrapperEl.style.alignItems = "center";
      wrapperEl.style.gap = "0.35rem";
      wrapperEl.style.font = "14px/1.2 system-ui, Arial, sans-serif";
      var input = document.createElement("input");
      input.type = "checkbox";
      input.checked = !!checked;
      var span = document.createElement("span");
      span.textContent = label;
      wrapperEl.appendChild(input);
      wrapperEl.appendChild(span);
      return { label: wrapperEl, input: input };
    }

    var addStopBtn = makeButton("Add stop");
    var pasteBtn = makeButton("Paste list");
    var optimizeBtn = makeButton("Optimize", "primary");
    var roundtripToggle = makeToggle("Roundtrip", true);
    var fixStartToggle = makeToggle("Fix start", true);
    var fixEndToggle = makeToggle("Fix finish", true);
    var clearBtn = makeButton("Clear");
    var exportBtn = makeButton("Export");
    // Follow / unfollow button
    var followBtn = makeButton("Follow", "primary");
    // Recenter now
    var recenterBtn = makeButton("Recenter");

    toolbar.appendChild(addStopBtn);
    toolbar.appendChild(pasteBtn);
    toolbar.appendChild(optimizeBtn);
    toolbar.appendChild(roundtripToggle.label);
    toolbar.appendChild(fixStartToggle.label);
    toolbar.appendChild(fixEndToggle.label);
    toolbar.appendChild(clearBtn);
    toolbar.appendChild(exportBtn);
    toolbar.appendChild(followBtn);
    toolbar.appendChild(recenterBtn);

    var statusEl = document.createElement("div");
    statusEl.className = "kc-osrm-status";
    statusEl.style.flex = "1";
    statusEl.style.minWidth = "200px";
    statusEl.style.font = "13px/1.3 system-ui, Arial, sans-serif";
    statusEl.style.color = "#555";

    toolbar.appendChild(statusEl);

    var mapHolder = document.createElement("div");
    mapHolder.className = "kc-osrm-map";
    mapHolder.style.position = "relative";
    mapHolder.style.flex = "1";

    parent.insertBefore(wrapper, el);
    wrapper.appendChild(toolbar);
    wrapper.appendChild(mapHolder);
    mapHolder.appendChild(el);

    var addStopMode = false;
    var map;
    var routingControl;
    var geocoderControl;
    var tripBase = getTripBase(KC_OSRM.base);
    var stepQueue = [];
    var stepIndex = 0;
    var following = true;
    var posMarker = null;
    var geoWatchId = null;
    var navRunning = false;

    function speakWeb(text) {
      if (!text) {
        return;
      }
      try {
        if (typeof window.SpeechSynthesisUtterance !== "function") {
          return;
        }
        var utterance = new window.SpeechSynthesisUtterance(text);
        utterance.lang = "en-US";
        utterance.rate = 1.0;
        if (window.speechSynthesis && typeof window.speechSynthesis.cancel === "function") {
          window.speechSynthesis.cancel();
          window.speechSynthesis.speak(utterance);
        }
      } catch (error) {
        // ignore
      }
    }

    function nativeAvailable() {
      var ua = typeof navigator !== "undefined" && navigator.userAgent ? navigator.userAgent : "";
      return !!window.Capacitor || /Android.*(wv|Version\/)/i.test(ua);
    }

    function sendNative(msg) {
      try {
        window.postMessage(msg, "*");
      } catch (error) {
        // ignore
      }
    }

    function setFollowMode(active) {
      following = !!active;
      if (followBtn) {
        followBtn.classList.toggle("button-primary", following);
        followBtn.classList.toggle("button-secondary", !following);
        followBtn.setAttribute("aria-pressed", following ? "true" : "false");
      }
    }

    function setStatus(message, type) {
      statusEl.textContent = message || "";
      statusEl.style.color =
        type === "error" ? "#c00" : type === "success" ? "#256029" : "#555";
    }

    // 1) Build a flat list of OSRM steps from the current route
    function buildSteps(route) {
      stepQueue = [];
      stepIndex = 0;
      try {
        if (!route || !route.legs) {
          return;
        }
        route.legs.forEach(function (leg) {
          if (!leg || !leg.steps) {
            return;
          }
          leg.steps.forEach(function (step) {
            if (!step || !step.maneuver || !step.maneuver.location) {
              return;
            }
            var loc = step.maneuver.location; // [lon, lat]
            var name = step.name || "";
            var type = step.maneuver.type || "";
            var modifier = (step.maneuver.modifier || "").toLowerCase();
            var text;
            if (type === "depart") {
              text = "Head out";
            } else if (type === "arrive") {
              text = "Arrive at destination";
            } else {
              text = (modifier ? "Turn " + modifier : "Continue") + (name ? " onto " + name : "");
            }
            stepQueue.push({
              lat: loc[1],
              lon: loc[0],
              text: text,
              distance: step.distance || 0,
            });
          });
        });
      } catch (error) {
        console.warn("No steps available", error);
      }
    }

    // 2) Phone GPS: show a live position marker and optionally keep map centered
    function updatePosition(lat, lon) {
      var ll = L.latLng(lat, lon);
      if (!posMarker) {
        posMarker = L.marker(ll, { title: "You" }).addTo(map);
      } else {
        posMarker.setLatLng(ll);
      }
      if (following && map && typeof map.panTo === "function") {
        map.panTo(ll, { animate: true });
      }
    }

    // 3) When you get close to the next step, speak it and advance
    function haversineMeters(a, b) {
      var R = 6371000;
      var toRad = function (x) {
        return (x * Math.PI) / 180;
      };
      var dLat = toRad(b.lat - a.lat);
      var dLon = toRad(b.lon - a.lon);
      var sLat1 = toRad(a.lat);
      var sLat2 = toRad(b.lat);
      var h =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(sLat1) * Math.cos(sLat2) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
      return 2 * R * Math.asin(Math.sqrt(h));
    }

    function onPosition(lat, lon) {
      updatePosition(lat, lon);
      if (!stepQueue.length || stepIndex >= stepQueue.length) {
        return;
      }

      var here = { lat: lat, lon: lon };
      var next = stepQueue[stepIndex];
      var distance = haversineMeters(here, next);
      var threshold = 60;
      if (distance <= threshold) {
        if (typeof window.kcSay === "function") {
          window.kcSay(next.text);
        } else {
          speakWeb(next.text);
        }
        stepIndex += 1;
      }
    }

    // 4) Floating Start/Stop controls and native messaging helpers
    var fab = mapHolder && mapHolder.querySelector(".kc-fab");
    if (!fab) {
      fab = document.createElement("div");
      fab.className = "kc-fab";
      fab.innerHTML =
        '<button class="kc-start" type="button">Start</button>' +
        '<button class="kc-stop" type="button" style="display:none">Stop</button>';
      (mapHolder || wrapper || document.body).appendChild(fab);
    }

    var btnStart = fab.querySelector(".kc-start");
    var btnStop = fab.querySelector(".kc-stop");

    function renderFabState() {
      if (!btnStart || !btnStop) {
        return;
      }
      btnStart.style.display = navRunning ? "none" : "inline-block";
      btnStop.style.display = navRunning ? "inline-block" : "none";
    }

    window.kcSay = function (text) {
      if (!text) {
        return;
      }
      if (nativeAvailable()) {
        sendNative({ type: "kc:tts", text: text });
      } else {
        speakWeb(text);
      }
    };

    window.kcNavStart = function () {
      if (navRunning) {
        return;
      }
      navRunning = true;
      renderFabState();
      if (nativeAvailable()) {
        sendNative({ type: "kc:navigation:start" });
      } else if (
        navigator.geolocation &&
        typeof navigator.geolocation.watchPosition === "function"
      ) {
        geoWatchId = navigator.geolocation.watchPosition(
          function (pos) {
            if (pos && pos.coords) {
              onPosition(pos.coords.latitude, pos.coords.longitude);
            }
          },
          function (err) {
            console.warn("Geo error", err);
          },
          { enableHighAccuracy: true, maximumAge: 2000, timeout: 10000 }
        );
        speakWeb("Navigation started");
      }
    };

    window.kcNavStop = function () {
      if (!navRunning) {
        return;
      }
      navRunning = false;
      renderFabState();
      if (nativeAvailable()) {
        sendNative({ type: "kc:navigation:stop" });
      }
      if (
        geoWatchId != null &&
        navigator.geolocation &&
        typeof navigator.geolocation.clearWatch === "function"
      ) {
        navigator.geolocation.clearWatch(geoWatchId);
        geoWatchId = null;
      }
    };

    if (btnStart && !btnStart.dataset.kcFabBound) {
      btnStart.addEventListener("click", function () {
        window.kcNavStart();
      });
      btnStart.dataset.kcFabBound = "1";
    }

    if (btnStop && !btnStop.dataset.kcFabBound) {
      btnStop.addEventListener("click", function () {
        window.kcNavStop();
      });
      btnStop.dataset.kcFabBound = "1";
    }

    window.kcHandlePosition = onPosition;
    if (!window._kcPositionListenerBound) {
      window.addEventListener("message", function (e) {
        if (e && e.data && e.data.type === "kc:position" && e.data.detail) {
          var c = e.data.detail;
          if (
            typeof window.kcHandlePosition === "function" &&
            typeof c.latitude === "number" &&
            typeof c.longitude === "number"
          ) {
            window.kcHandlePosition(c.latitude, c.longitude);
          }
        }
      });
      window._kcPositionListenerBound = true;
    }

    renderFabState();

    function setLoading(isLoading) {
      optimizeBtn.disabled = isLoading;
      optimizeBtn.textContent = isLoading ? "Optimizing…" : "Optimize";
      optimizeBtn.style.opacity = isLoading ? "0.6" : "1";
      if (isLoading) {
        setStatus("Optimizing route…", "");
      }
    }

    function getWaypoints() {
      if (!routingControl) return [];
      return routingControl
        .getWaypoints()
        .filter(function (wp) {
          return wp && wp.latLng;
        })
        .map(function (wp) {
          return {
            lat: wp.latLng.lat,
            lng: wp.latLng.lng,
            name: wp.name || "",
          };
        });
    }

    function setWaypoints(list) {
      var wps = (list || [])
        .filter(function (wp) {
          return wp && typeof wp.lat === "number" && typeof wp.lng === "number";
        })
        .map(function (wp) {
          return L.Routing.waypoint(L.latLng(wp.lat, wp.lng), wp.name || "");
        });
      routingControl.setWaypoints(wps);
    }

    function addStopAt(latLng, index) {
      if (!routingControl || !latLng) return;
      var wps = routingControl
        .getWaypoints()
        .filter(function (wp) {
          return wp && wp.latLng;
        });
      var waypoint = L.Routing.waypoint(latLng);
      if (typeof index === "number" && index >= 0 && index <= wps.length) {
        wps.splice(index, 0, waypoint);
      } else {
        wps.push(waypoint);
      }
      routingControl.setWaypoints(wps);
    }

    function clearAll() {
      if (!routingControl) return;
      routingControl.setWaypoints([]);
      setStatus("Cleared all stops.", "");
      stepQueue = [];
      stepIndex = 0;
    }

    function setAddStopMode(active) {
      addStopMode = !!active;
      addStopBtn.setAttribute("aria-pressed", addStopMode ? "true" : "false");
      addStopBtn.classList.toggle("button-primary", addStopMode);
      addStopBtn.classList.toggle("button-secondary", !addStopMode);
      addStopBtn.style.background = "";
      addStopBtn.style.color = "";
      if (map) {
        map.getContainer().style.cursor = addStopMode ? "copy" : "";
      }
    }

    function optimizeOrder() {
      if (!routingControl) return;
      var pts = getWaypoints();
      if (pts.length < 2) {
        setStatus("Add at least two stops to optimize.", "error");
        return;
      }

      var coords = pts
        .map(function (wp) {
          return wp.lng + "," + wp.lat;
        })
        .join(";");

      var profile = KC_OSRM.profile || "driving";
      var url =
        tripBase.replace(/\/?$/, "/") +
        encodeURIComponent(profile) +
        "/" +
        coords;

      var params = [];
      params.push("roundtrip=" + (roundtripToggle.input.checked ? "true" : "false"));
      params.push("source=" + (fixStartToggle.input.checked ? "first" : "any"));
      params.push("destination=" + (fixEndToggle.input.checked ? "last" : "any"));
      url += "?" + params.join("&");

      setLoading(true);

      fetch(url)
        .then(function (response) {
          if (!response.ok) {
            throw new Error("HTTP " + response.status);
          }
          return response.json();
        })
        .then(function (data) {
          if (!data || data.code !== "Ok" || !data.waypoints) {
            throw new Error(data && data.message ? data.message : "Unexpected response");
          }

          var ordered = new Array(data.waypoints.length);
          data.waypoints.forEach(function (wp, idx) {
            if (typeof wp.waypoint_index !== "number") return;
            ordered[wp.waypoint_index] = {
              lat: wp.location[1],
              lng: wp.location[0],
              name: pts[idx] ? pts[idx].name : "",
            };
          });

          var cleaned = ordered.filter(function (item) {
            return item && typeof item.lat === "number" && typeof item.lng === "number";
          });

          if (!cleaned.length) {
            throw new Error("No optimized route returned");
          }

          setWaypoints(cleaned);
          setStatus("Route optimized.", "success");
        })
        .catch(function (err) {
          setStatus("Optimize failed: " + err.message, "error");
        })
        .finally(function () {
          setLoading(false);
        });
    }

    function exportWaypoints() {
      var pts = getWaypoints();
      if (!pts.length) {
        setStatus("Nothing to export.", "error");
        return;
      }

      var payload = {
        roundtrip: !!roundtripToggle.input.checked,
        fixStart: !!fixStartToggle.input.checked,
        fixEnd: !!fixEndToggle.input.checked,
        profile: KC_OSRM.profile || "driving",
        waypoints: pts,
      };

      var blob = new Blob([JSON.stringify(payload, null, 2)], {
        type: "application/json",
      });
      var url = URL.createObjectURL(blob);
      var a = document.createElement("a");
      a.href = url;
      a.download =
        "kerbcycle-route-" + new Date().toISOString().replace(/[:.]/g, "-") + ".json";
      document.body.appendChild(a);
      a.click();
      setTimeout(function () {
        URL.revokeObjectURL(url);
        document.body.removeChild(a);
      }, 0);
      setStatus("Exported current route.", "success");
    }

    function registerEvents() {
      addStopBtn.addEventListener("click", function () {
        setAddStopMode(!addStopMode);
      });

      map.on("click", function (e) {
        if (!addStopMode) return;
        addStopAt(e.latlng);
        setAddStopMode(false);
      });

      pasteBtn.addEventListener("click", function () {
        var existing = wrapper.querySelector('[data-kc="paste-box"]');
        if (existing) {
          existing.remove();
        }

        var box = document.createElement("div");
        box.setAttribute("data-kc", "paste-box");
        box.style.position = "absolute";
        box.style.zIndex = "1001";
        box.style.top = "56px";
        box.style.left = "8px";
        box.style.background = "#fff";
        box.style.border = "1px solid #ddd";
        box.style.borderRadius = "6px";
        box.style.padding = "8px";
        box.style.boxShadow = "0 1px 3px rgba(0,0,0,.12)";
        box.style.maxWidth = "340px";
        box.innerHTML =
          '' +
          '<div style="font-weight:600;margin-bottom:6px">Paste addresses (one per line)</div>' +
          '<textarea data-kc="paste-input" style="width:320px;height:120px"></textarea>' +
          '<div style="margin-top:6px;display:flex;gap:6px">' +
          '<button class="button button-primary" data-kc="go">Geocode</button>' +
          '<button class="button" data-kc="close">Close</button>' +
          "</div>" +
          '<div data-kc="status" style="margin-top:6px;font:12px/1.4 system-ui,Arial;color:#555"></div>';

        wrapper.appendChild(box);

        var closeBtn = box.querySelector('[data-kc="close"]');
        if (closeBtn) {
          closeBtn.addEventListener("click", function () {
            box.remove();
          });
        }

        var goBtn = box.querySelector('[data-kc="go"]');
        if (goBtn) {
          goBtn.addEventListener("click", async function () {
            var ta = box.querySelector('[data-kc="paste-input"]');
            var status = box.querySelector('[data-kc="status"]');
            if (!ta || !status) {
              return;
            }

            var lines = ta.value
              .split("\n")
              .map(function (s) {
                return s.trim();
              })
              .filter(Boolean);

            if (!lines.length) {
              status.textContent = "Nothing to geocode.";
              return;
            }

            status.textContent = "Geocoding " + lines.length + "…";
            var added = 0;

            for (var i = 0; i < lines.length; i++) {
              var query = lines[i];
              try {
                var response = await fetch(
                  "https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=" +
                    encodeURIComponent(query),
                  {
                    headers: { Accept: "application/json" },
                  }
                );
                var arr = await response.json();
                if (arr && arr[0]) {
                  var lat = parseFloat(arr[0].lat);
                  var lon = parseFloat(arr[0].lon);
                  if (!Number.isNaN(lat) && !Number.isNaN(lon)) {
                    addStopAt(L.latLng(lat, lon));
                    added++;
                  }
                } else {
                  console.warn("No result for:", query);
                }
              } catch (error) {
                console.warn("Geocode failed for", query, error);
              }

              if (i < lines.length - 1) {
                await new Promise(function (resolve) {
                  setTimeout(resolve, 1100);
                });
              }
            }

            status.textContent =
              "Added " + added + " of " + lines.length + " lines.";
          });
        }
      });

      optimizeBtn.addEventListener("click", optimizeOrder);
      clearBtn.addEventListener("click", function () {
        clearAll();
        setAddStopMode(false);
      });
      exportBtn.addEventListener("click", exportWaypoints);
      followBtn.addEventListener("click", function () {
        setFollowMode(!following);
      });
      recenterBtn.addEventListener("click", function () {
        if (posMarker && map && typeof map.panTo === "function") {
          map.panTo(posMarker.getLatLng(), { animate: true });
        }
      });

      routingControl.on("routingerror", function (e) {
        var message = "Routing failed";
        if (e && e.error && e.error.message) {
          message += ": " + e.error.message;
        }
        setStatus(message + ". Check endpoint/profile/CORS.", "error");
      });

      if (map) {
        map.on("dragstart", function () {
          setFollowMode(false);
        });
      }
    }

    try {
      map = L.map(el).setView(start, cfg.zoom || 12);
      window._kcMap = map;
      L.tileLayer(KC_OSRM.tileUrl, { attribution: KC_OSRM.tileAttrib }).addTo(map);

      if (L.Control && L.Control.Geocoder && typeof L.Control.geocoder === "function") {
        geocoderControl = L.Control.geocoder({
          defaultMarkGeocode: false,
          geocoder: L.Control.Geocoder.nominatim({
            serviceUrl: "https://nominatim.openstreetmap.org/",
          }),
        })
          .on("markgeocode", function (e) {
            if (!e || !e.geocode || !e.geocode.center) {
              return;
            }
            addStopAt(e.geocode.center);
            if (map && typeof map.panTo === "function") {
              map.panTo(e.geocode.center);
            }
          })
          .addTo(map);
      } else {
        console.warn("Leaflet Control Geocoder is unavailable.");
      }

      routingControl = L.Routing.control({
        waypoints: [
          L.latLng(start[0], start[1]),
          L.latLng(end[0], end[1]),
        ],
        router: L.Routing.osrmv1({
          serviceUrl: KC_OSRM.base,
          profile: KC_OSRM.profile,
        }),
        addWaypoints: true,
        draggableWaypoints: true,
        routeWhileDragging: true,
        showAlternatives: false,
        collapsible: true,
      })
        .on("routingstart", function () {
          setStatus("Routing…", "");
        })
        .on("routesfound", function (e) {
          setStatus("", "");
          if (e && e.routes && e.routes[0]) {
            buildSteps(e.routes[0]);
            if (posMarker && typeof posMarker.getLatLng === "function") {
              var current = posMarker.getLatLng();
              onPosition(current.lat, current.lng);
            }
          }
        })
        .addTo(map);

      setTimeout(function () {
        if (routingControl && routingControl._container) {
          routingControl._container.classList.add("leaflet-routing-collapsed");
        }
      }, 0);

      registerEvents();

      setAddStopMode(false);
      setStatus("", "");
      setFollowMode(true);

      // Hidden-tab resilience
      setTimeout(function () {
        map.invalidateSize();
      }, 50);
      document.addEventListener("visibilitychange", function () {
        setTimeout(function () {
          map.invalidateSize();
        }, 50);
      });
      window.addEventListener("um_tab_shown", function () {
        setTimeout(function () {
          map.invalidateSize();
        }, 50);
      });

    } catch (err) {
      showMsg(el, "Map init error: " + err.message);
      return;
    }

    el._kcOsrm = {
      map: map,
      control: routingControl,
      getWaypoints: getWaypoints,
      setWaypoints: setWaypoints,
      addStopAt: addStopAt,
      clearAll: clearAll,
      optimizeOrder: optimizeOrder,
      export: exportWaypoints,
    };
  }

  function drain() {
    var q = window.KC_OSRM_QUEUE || [];
    for (var i = 0; i < q.length; i++) initOne(q[i]);
    window.KC_OSRM_QUEUE = []; // clear
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", drain);
  } else {
    drain();
  }
})();
