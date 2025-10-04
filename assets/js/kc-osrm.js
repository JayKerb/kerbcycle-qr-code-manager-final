(function () {
  function parseLatLon(str) {
    if (Array.isArray(str) && str.length >= 2) {
      var fromArrayLat = parseFloat(str[0]);
      var fromArrayLon = parseFloat(str[1]);
      if (isFinite(fromArrayLat) && isFinite(fromArrayLon)) {
        return [fromArrayLat, fromArrayLon];
      }
    }

    if (typeof str !== "string") {
      return null;
    }

    var p = str
      .split(",")
      .map(function (n) {
        return parseFloat(n);
      })
      .filter(function (value, index) {
        return index < 2;
      });

    if (!isFinite(p[0]) || !isFinite(p[1])) {
      return null;
    }

    return [p[0], p[1]]; // [lat, lon]
  }

  function stripBOM(text) {
    var str = String(text || "");
    if (!str) {
      return "";
    }
    return str.charCodeAt(0) === 0xfeff ? str.slice(1) : str;
  }

  function toLatLngMaybe(a, b) {
    var n1 = parseFloat(a);
    var n2 = parseFloat(b);
    if (!isFinite(n1) || !isFinite(n2)) {
      return null;
    }
    if (Math.abs(n1) <= 90 && Math.abs(n2) <= 180) {
      return L.latLng(n1, n2);
    }
    if (Math.abs(n2) <= 90 && Math.abs(n1) <= 180) {
      return L.latLng(n2, n1);
    }
    return null;
  }

  function normHeader(header) {
    return String(header || "").trim().toLowerCase().replace(/[^a-z]/g, "");
  }

  function sniffDelimiter(headerLine) {
    var candidates = [",", ";", "\t", "|"];
    var best = ",";
    var bestCount = -1;
    for (var i = 0; i < candidates.length; i++) {
      var delim = candidates[i];
      var count = headerLine.split(delim).length;
      if (count > bestCount) {
        best = delim;
        bestCount = count;
      }
    }
    return best;
  }

  function splitCSVLine(line, delim) {
    var out = [];
    var cur = "";
    var quoted = false;
    for (var i = 0; i < line.length; i++) {
      var ch = line[i];
      if (ch === '"') {
        quoted = !quoted;
        cur += ch;
      } else if (ch === delim && !quoted) {
        out.push(cur.trim());
        cur = "";
      } else {
        cur += ch;
      }
    }
    out.push(cur.trim());
    return out.map(function (value) {
      var val = value;
      if (val.slice(0, 1) === '"' && val.slice(-1) === '"') {
        val = val.slice(1, -1).replace(/""/g, '"');
      }
      return val;
    });
  }

  function handleImportFile(file) {
    if (!file) {
      return;
    }
    resetImportErrors();
    setStatus("Reading " + file.name + "…", "");
    var ext = (file.name.split(".").pop() || "").toLowerCase();
    var reader = new FileReader();
    reader.onerror = function () {
      setStatus("Failed to read file.", "error");
    };
    reader.onload = function () {
      var raw = stripBOM(reader.result || "");
      var text = String(raw || "");
      if (!text.trim()) {
        setStatus("File is empty.", "warn");
        updateErrorReportButton();
        return;
      }

      if (ext === "json" || ext === "geojson") {
        importFromJSON(text);
      } else if (ext === "csv" || ext === "txt") {
        if (/^\s*[\[{]/.test(text)) {
          importFromJSON(text);
        } else {
          importFromCSV(text);
        }
      } else {
        if (/^\s*[\[{]/.test(text)) {
          importFromJSON(text);
        } else {
          importFromCSV(text);
        }
      }
    };
    reader.readAsText(file);
  }

  function importFromCSV(text) {
    var lines = String(text || "")
      .split(/\r?\n/)
      .filter(function (line) {
        return line.trim().length > 0;
      });
    if (!lines.length) {
      setStatus("CSV has no rows.", "warn");
      updateErrorReportButton();
      return;
    }

    var headerLine = lines[0];
    var delimiter = sniffDelimiter(headerLine);
    var headers = splitCSVLine(headerLine, delimiter).map(normHeader);
    if (!headers.length) {
      setStatus("CSV header row is empty.", "error");
      updateErrorReportButton();
      return;
    }

    var idxAddr = headers.indexOf("address");
    var idxLat = headers.indexOf("lat");
    if (idxLat === -1) {
      idxLat = headers.indexOf("latitude");
    }
    var idxLon = -1;
    var lonHeaders = ["lon", "lng", "long", "longitude"];
    for (var i = 0; i < lonHeaders.length; i++) {
      var pos = headers.indexOf(lonHeaders[i]);
      if (pos !== -1) {
        idxLon = pos;
        break;
      }
    }

    if (idxAddr === -1 && (idxLat === -1 || idxLon === -1)) {
      setStatus(
        'CSV needs an "address" column OR both "lat" and "lon" columns.',
        "error"
      );
      updateErrorReportButton();
      return;
    }

    var queued = [];
    var added = 0;
    for (var rowIndex = 1; rowIndex < lines.length; rowIndex++) {
      var cols = splitCSVLine(lines[rowIndex], delimiter);
      if (!cols.length) {
        continue;
      }
      if (idxAddr !== -1) {
        var addrRaw = cols[idxAddr] || "";
        var addr = addrRaw.trim();
        if (addr) {
          queued.push({
            value: addr,
            label: addr + " (row " + (rowIndex + 1) + ")",
            row: rowIndex + 1,
          });
        } else {
          recordImportError({
            label: "Row " + (rowIndex + 1),
            reason: "Missing address",
          });
        }
      } else {
        var latSource = idxLat !== -1 ? cols[idxLat] : null;
        var lonSource = idxLon !== -1 ? cols[idxLon] : null;
        var ll = toLatLngMaybe(latSource, lonSource);
        if (ll) {
          addStopAt(ll);
          added += 1;
        } else {
          recordImportError({
            label: "Row " + (rowIndex + 1),
            reason: "Invalid coordinates",
          });
        }
      }
    }

    if (idxAddr !== -1) {
      if (queued.length) {
        setStatus("Geocoding " + queued.length + " address(es)…", "");
        geocodeSequential(queued);
      } else {
        setStatus("No valid addresses found in CSV.", "warn");
        updateErrorReportButton();
      }
    } else if (added > 0) {
      var summary = "Added " + added + " stop(s) from CSV.";
      if (importErrors.length) {
        summary += " Skipped " + importErrors.length + " invalid row(s).";
      }
      setStatus(summary, importErrors.length ? "warn" : "success");
      updateErrorReportButton();
    } else {
      setStatus("No valid coordinates found in CSV.", "warn");
      updateErrorReportButton();
    }
  }

  function importFromJSON(text) {
    var data;
    try {
      data = JSON.parse(String(text || ""));
    } catch (error) {
      setStatus("Invalid JSON.", "error");
      updateErrorReportButton();
      return;
    }

    var added = 0;
    var queued = [];

    function handleQueued() {
      if (queued.length) {
        setStatus("Geocoding " + queued.length + " address(es)…", "");
        geocodeSequential(queued);
      } else if (!added) {
        setStatus("No valid stops found in JSON.", "warn");
        updateErrorReportButton();
      } else {
        var message = "Added " + added + " stop(s) from JSON.";
        if (importErrors.length) {
          message += " Review errors for skipped entries.";
        }
        setStatus(message, importErrors.length ? "warn" : "success");
        updateErrorReportButton();
      }
    }

    if (data && data.type === "FeatureCollection" && Array.isArray(data.features)) {
      data.features.forEach(function (feature, index) {
        if (!feature || !feature.geometry) {
          recordImportError({
            label: "Feature " + (index + 1),
            reason: "Missing geometry",
          });
          return;
        }
        var geom = feature.geometry;
        if (geom.type === "Point") {
          var coords = geom.coordinates || [];
          var ll =
            toLatLngMaybe(coords[1], coords[0]) || toLatLngMaybe(coords[0], coords[1]);
          if (ll) {
            addStopAt(ll);
            added += 1;
          } else {
            recordImportError({
              label: "Feature " + (index + 1),
              reason: "Invalid coordinates",
            });
          }
        } else if (geom.type === "LineString" && Array.isArray(geom.coordinates)) {
          for (var i = 0; i < geom.coordinates.length; i++) {
            var pair = geom.coordinates[i] || [];
            var llLine =
              toLatLngMaybe(pair[1], pair[0]) || toLatLngMaybe(pair[0], pair[1]);
            if (llLine) {
              addStopAt(llLine);
              added += 1;
            } else {
              recordImportError({
                label: "Feature " + (index + 1) + " point " + (i + 1),
                reason: "Invalid coordinates",
              });
            }
          }
        } else {
          recordImportError({
            label: "Feature " + (index + 1),
            reason: "Unsupported geometry type",
          });
        }
      });
      if (added) {
        var summary = "Added " + added + " stop(s) from GeoJSON.";
        if (importErrors.length) {
          summary += " Review errors for skipped entries.";
        }
        setStatus(summary, importErrors.length ? "warn" : "success");
      } else {
        setStatus("No valid features found in GeoJSON.", "warn");
      }
      updateErrorReportButton();
      return;
    }

    if (data && Array.isArray(data.waypoints)) {
      data.waypoints.forEach(function (wp, index) {
        if (wp && (wp.lat != null || wp.latitude != null)) {
          var latVal = wp.lat != null ? wp.lat : wp.latitude;
          var lonVal =
            wp.lon != null
              ? wp.lon
              : wp.lng != null
              ? wp.lng
              : wp.long != null
              ? wp.long
              : wp.longitude;
          var ll = toLatLngMaybe(latVal, lonVal);
          if (ll) {
            addStopAt(ll);
            added += 1;
          } else if (wp.address) {
            queued.push({
              value: wp.address,
              label: String(wp.address) + " (item " + (index + 1) + ")",
            });
          } else {
            recordImportError({
              label: "Waypoint " + (index + 1),
              reason: "Invalid coordinates",
            });
          }
        } else if (wp && wp.address) {
          queued.push({
            value: wp.address,
            label: String(wp.address) + " (item " + (index + 1) + ")",
          });
        } else {
          recordImportError({
            label: "Waypoint " + (index + 1),
            reason: "Missing coordinates",
          });
        }
      });
      handleQueued();
      return;
    }

    if (Array.isArray(data)) {
      data.forEach(function (item, index) {
        if (Array.isArray(item) && item.length >= 2) {
          var llArray = toLatLngMaybe(item[0], item[1]);
          if (llArray) {
            addStopAt(llArray);
            added += 1;
          } else {
            recordImportError({
              label: "Item " + (index + 1),
              reason: "Invalid coordinate pair",
            });
          }
        } else if (item && (item.lat != null || item.latitude != null)) {
          var latValue = item.lat != null ? item.lat : item.latitude;
          var lonValue =
            item.lon != null
              ? item.lon
              : item.lng != null
              ? item.lng
              : item.long != null
              ? item.long
              : item.longitude;
          var llObj = toLatLngMaybe(latValue, lonValue);
          if (llObj) {
            addStopAt(llObj);
            added += 1;
          } else if (item.address) {
            queued.push({
              value: item.address,
              label: String(item.address) + " (item " + (index + 1) + ")",
            });
          } else {
            recordImportError({
              label: "Item " + (index + 1),
              reason: "Invalid coordinates",
            });
          }
        } else if (item && item.address) {
          queued.push({
            value: item.address,
            label: String(item.address) + " (item " + (index + 1) + ")",
          });
        } else {
          recordImportError({
            label: "Item " + (index + 1),
            reason: "Unrecognized entry",
          });
        }
      });
      handleQueued();
      return;
    }

    setStatus(
      "JSON format not recognized. Expect GeoJSON, {waypoints: []}, or an array.",
      "error"
    );
    updateErrorReportButton();
  }

  function readStoredDefaultStart() {
    try {
      if (typeof window !== "undefined" && window.localStorage) {
        return window.localStorage.getItem("kc_default_start") || "";
      }
    } catch (error) {
      // localStorage can throw in private browsing; ignore.
    }
    return "";
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

    var storedStart = readStoredDefaultStart();
    var start =
      parseLatLon(storedStart) ||
      parseLatLon(cfg.start) ||
      parseLatLon(KC_OSRM.defaultStart) ||
      [40.73, -73.99];
    var end = parseLatLon(cfg.end) || [40.78, -73.97];

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
    var importBtn = makeButton("Import");
    var optimizeBtn = makeButton("Optimize", "primary");
    var roundtripToggle = makeToggle("Roundtrip", false);
    var fixStartToggle = makeToggle("Fix start", true);
    var fixEndToggle = makeToggle("Fix finish", true);
    var gpsStartToggle = makeToggle("Use GPS as start", true);
    var saveDefaultBtn = makeButton("Set current as default Start");
    var resetDefaultBtn = makeButton("Reset defaults");
    var clearBtn = makeButton("Clear");
    var exportBtn = makeButton("Export");
    var errorReportBtn = makeButton("Download errors");
    errorReportBtn.disabled = true;
    var errorButtonBaseLabel = "Download errors";
    // Follow / unfollow button
    var followBtn = makeButton("Follow", "primary");
    // Recenter now
    var recenterBtn = makeButton("Recenter");

    toolbar.appendChild(addStopBtn);
    toolbar.appendChild(pasteBtn);
    toolbar.appendChild(importBtn);
    toolbar.appendChild(optimizeBtn);
    toolbar.appendChild(roundtripToggle.label);
    toolbar.appendChild(fixStartToggle.label);
    toolbar.appendChild(fixEndToggle.label);
    toolbar.appendChild(gpsStartToggle.label);
    toolbar.appendChild(saveDefaultBtn);
    toolbar.appendChild(resetDefaultBtn);
    toolbar.appendChild(clearBtn);
    toolbar.appendChild(exportBtn);
    toolbar.appendChild(errorReportBtn);
    toolbar.appendChild(followBtn);
    toolbar.appendChild(recenterBtn);

    var statusEl = document.createElement("div");
    statusEl.className = "kc-osrm-status";
    statusEl.style.flex = "1";
    statusEl.style.minWidth = "200px";
    statusEl.style.font = "13px/1.3 system-ui, Arial, sans-serif";
    statusEl.style.color = "#555";

    var fileInput = document.createElement("input");
    fileInput.type = "file";
    fileInput.accept = ".csv,.geojson,.json,.txt";
    fileInput.style.display = "none";
    toolbar.appendChild(fileInput);

    toolbar.appendChild(statusEl);

    if (gpsStartToggle && gpsStartToggle.input) {
      preferGpsStart = !!gpsStartToggle.input.checked;
    }

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
    var geoWatchReason = null;
    var navRunning = false;
    var fallbackTimerId = null;
    var receivedNativePosition = false;
    var firstFixApplied = false;
    var preferGpsStart = true;

    var preferredVoice = null;
    var importErrors = [];
    var geocodeDelayMs = 1100;

    function pickVoice() {
      if (!window.speechSynthesis || typeof window.speechSynthesis.getVoices !== "function") {
        return null;
      }
      var voices = window.speechSynthesis.getVoices() || [];
      if (!voices.length) {
        return null;
      }
      var want = ["Google US English", "Google English", "en-US"];
      for (var i = 0; i < want.length; i++) {
        var target = want[i];
        for (var j = 0; j < voices.length; j++) {
          var voice = voices[j];
          var name = voice && voice.name ? voice.name : "";
          var lang = voice && voice.lang ? voice.lang : "";
          if ((name && name.indexOf(target) !== -1) || (lang && lang === target)) {
            return voice;
          }
        }
      }
      for (var k = 0; k < voices.length; k++) {
        var fallback = voices[k];
        var fallbackLang = fallback && fallback.lang ? fallback.lang : "";
        if (fallbackLang && fallbackLang.indexOf("en-") === 0) {
          return fallback;
        }
      }
      return voices[0];
    }

    function refreshPreferredVoice() {
      preferredVoice = pickVoice();
    }

    function speakWeb(text) {
      if (!text) {
        return false;
      }
      try {
        if (typeof window.SpeechSynthesisUtterance !== "function") {
          return false;
        }
        if (!preferredVoice) {
          refreshPreferredVoice();
        }
        var utterance = new window.SpeechSynthesisUtterance(text);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        if (preferredVoice) {
          utterance.voice = preferredVoice;
          if (preferredVoice.lang) {
            utterance.lang = preferredVoice.lang;
          }
        } else {
          utterance.lang = "en-US";
        }
        if (window.speechSynthesis && typeof window.speechSynthesis.cancel === "function") {
          window.speechSynthesis.cancel();
          window.speechSynthesis.speak(utterance);
          return true;
        }
      } catch (error) {
        // ignore
      }
      return false;
    }

    function isCapacitorNative() {
      try {
        if (!window.Capacitor) {
          return false;
        }
        if (typeof window.Capacitor.isNativePlatform === "function") {
          return !!window.Capacitor.isNativePlatform();
        }
        return !!window.Capacitor.isNativePlatform;
      } catch (error) {
        return false;
      }
    }

    function getNativeTts() {
      try {
        if (window.Capacitor && window.Capacitor.Plugins) {
          if (window.Capacitor.Plugins.TextToSpeech) {
            return window.Capacitor.Plugins.TextToSpeech;
          }
        }
      } catch (error) {
        // ignore
      }
      try {
        if (window.TextToSpeech) {
          return window.TextToSpeech;
        }
      } catch (error2) {
        // ignore
      }
      return null;
    }

    function nativeSpeak(text, lang, rate, pitch) {
      if (!text) {
        return Promise.resolve(false);
      }
      if (!isCapacitorNative()) {
        return Promise.resolve(false);
      }
      var plugin = getNativeTts();
      if (!plugin || typeof plugin.speak !== "function") {
        return Promise.resolve(false);
      }
      try {
        var result = plugin.speak({
          text: text,
          lang: lang || "en-US",
          rate: typeof rate === "number" ? rate : 1.0,
          pitch: typeof pitch === "number" ? pitch : 1.0,
          volume: 1.0,
        });
        if (result && typeof result.then === "function") {
          return result
            .then(function () {
              return true;
            })
            .catch(function () {
              return false;
            });
        }
        return Promise.resolve(true);
      } catch (error) {
        return Promise.resolve(false);
      }
    }

    var ttsPrimed = false;

    function primeTTS() {
      if (ttsPrimed) {
        return false;
      }
      ttsPrimed = true;
      if (
        window.speechSynthesis &&
        typeof window.speechSynthesis.getVoices === "function" &&
        window.speechSynthesis.getVoices().length === 0
      ) {
        if (typeof window.speechSynthesis.addEventListener === "function") {
          var handleVoicesReady = function handleVoicesReady() {
            window.speechSynthesis.removeEventListener("voiceschanged", handleVoicesReady);
            refreshPreferredVoice();
          };
          window.speechSynthesis.addEventListener("voiceschanged", handleVoicesReady);
        } else {
          var originalHandler = window.speechSynthesis.onvoiceschanged;
          window.speechSynthesis.onvoiceschanged = function (event) {
            if (typeof originalHandler === "function") {
              originalHandler.call(this, event);
            }
            refreshPreferredVoice();
          };
        }
      }
      if (typeof window.kcSay === "function") {
        window.kcSay("Navigation started");
      } else {
        speakWeb("Navigation started");
      }
      return true;
    }

    window.kcPrimeTTS = primeTTS;

    if (window.speechSynthesis) {
      refreshPreferredVoice();
      if (typeof window.speechSynthesis.addEventListener === "function") {
        window.speechSynthesis.addEventListener("voiceschanged", refreshPreferredVoice);
      } else {
        var oldHandler = window.speechSynthesis.onvoiceschanged;
        window.speechSynthesis.onvoiceschanged = function (event) {
          if (typeof oldHandler === "function") {
            oldHandler.call(this, event);
          }
          refreshPreferredVoice();
        };
      }
    }

    function nativeAvailable() {
      var cap =
        (typeof window !== "undefined" && window.Capacitor) ||
        (typeof window !== "undefined" && window.capacitorExports && window.capacitorExports.Capacitor);
      if (!cap) {
        return false;
      }
      if (typeof cap.isPluginAvailable === "function") {
        try {
          if (cap.isPluginAvailable("Geolocation")) {
            return true;
          }
        } catch (error) {
          // ignore
        }
      }
      var plugins = cap.Plugins || {};
      if (
        plugins.Geolocation ||
        plugins.GeolocationPlugin ||
        plugins.GeolocationNative ||
        plugins.KerbcycleGeolocation
      ) {
        return true;
      }
      return false;
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

    function setWaypointsLatLngs(latlngs) {
      if (!routingControl || !Array.isArray(latlngs)) {
        return;
      }
      var wps = latlngs
        .map(function (entry) {
          if (!entry) {
            return null;
          }

          var ll = null;
          var name = "";

          if (
            entry.latLng &&
            typeof entry.latLng.lat === "number" &&
            typeof entry.latLng.lng === "number"
          ) {
            ll = entry.latLng;
            name =
              typeof entry.name === "string"
                ? entry.name
                : typeof entry.latLng.name === "string"
                ? entry.latLng.name
                : "";
          } else if (
            typeof entry.lat === "number" &&
            typeof entry.lng === "number"
          ) {
            ll = L.latLng(entry.lat, entry.lng);
            name = typeof entry.name === "string" ? entry.name : "";
          } else if (
            typeof entry.lat === "number" &&
            typeof entry.lon === "number"
          ) {
            ll = L.latLng(entry.lat, entry.lon);
            name = typeof entry.name === "string" ? entry.name : "";
          } else if (Array.isArray(entry) && entry.length >= 2) {
            var latFromArray = parseFloat(entry[0]);
            var lngFromArray = parseFloat(entry[1]);
            if (isFinite(latFromArray) && isFinite(lngFromArray)) {
              ll = L.latLng(latFromArray, lngFromArray);
            }
          }

          if (!ll || !isFinite(ll.lat) || !isFinite(ll.lng)) {
            return null;
          }

          return {
            latLng: L.latLng(ll.lat, ll.lng),
            name: typeof name === "string" ? name : "",
          };
        })
        .filter(function (entry) {
          return (
            entry &&
            entry.latLng &&
            typeof entry.latLng.lat === "number" &&
            typeof entry.latLng.lng === "number" &&
            isFinite(entry.latLng.lat) &&
            isFinite(entry.latLng.lng)
          );
        })
        .map(function (entry) {
          return L.Routing.waypoint(entry.latLng, entry.name || "");
        });
      routingControl.setWaypoints(wps);
    }

    function ensureCurrentIsStart(lat, lon) {
      if (!preferGpsStart || firstFixApplied || !routingControl) {
        return false;
      }

      var waypoints = [];
      if (typeof routingControl.getWaypoints === "function") {
        waypoints = routingControl
          .getWaypoints()
          .map(function (wp) {
            if (!wp || !wp.latLng) {
              return null;
            }
            var ll = wp.latLng;
            if (
              typeof ll.lat !== "number" ||
              typeof ll.lng !== "number" ||
              !isFinite(ll.lat) ||
              !isFinite(ll.lng)
            ) {
              return null;
            }
            return {
              latLng: L.latLng(ll.lat, ll.lng),
              name: typeof wp.name === "string" ? wp.name : "",
            };
          })
          .filter(Boolean);
      }

      var here = L.latLng(lat, lon);
      var applied = false;

      if (waypoints.length === 0) {
        setWaypointsLatLngs([{ latLng: here, name: "" }]);
        applied = true;
      } else if (waypoints.length === 1) {
        var existing = waypoints[0];
        setWaypointsLatLngs([
          { latLng: here, name: "" },
          existing,
        ]);
        routingControl.route();
        applied = true;
      } else {
        var currentStart = waypoints[0];
        if (currentStart && currentStart.latLng && typeof here.distanceTo === "function") {
          var dist = here.distanceTo(currentStart.latLng);
          if (isFinite(dist) && dist > 50) {
            waypoints[0] = {
              latLng: here,
              name: typeof currentStart.name === "string" ? currentStart.name : "",
            };
            setWaypointsLatLngs(waypoints);
            routingControl.route();
            applied = true;
          }
        }
      }

      firstFixApplied = true;
      return applied;
    }

    function updateErrorReportButton() {
      if (!errorReportBtn) {
        return;
      }
      var count = importErrors.length;
      errorReportBtn.textContent =
        count > 0 ? errorButtonBaseLabel + " (" + count + ")" : errorButtonBaseLabel;
      errorReportBtn.disabled = count === 0;
      errorReportBtn.title = count
        ? "Download a report of " + count + " failed item(s)."
        : "No import errors to download.";
    }

    function resetImportErrors() {
      importErrors = [];
      updateErrorReportButton();
    }

    function recordImportError(entry) {
      if (!entry) {
        return;
      }
      importErrors.push({
        label: entry.label || "Unknown item",
        reason: entry.reason || "",
      });
      updateErrorReportButton();
    }

    function downloadErrorReport() {
      if (!importErrors.length) {
        setStatus("No import errors to download.", "error");
        return;
      }
      var lines = importErrors.map(function (item, index) {
        var label = item && item.label ? item.label : "Item " + (index + 1);
        var reason = item && item.reason ? item.reason : "";
        return label + (reason ? " — " + reason : "");
      });
      var blob = new Blob([lines.join("\n")], { type: "text/plain" });
      var url = URL.createObjectURL(blob);
      var a = document.createElement("a");
      a.href = url;
      a.download =
        "kerbcycle-import-errors-" +
        new Date().toISOString().replace(/[:.]/g, "-") +
        ".txt";
      document.body.appendChild(a);
      a.click();
      setTimeout(function () {
        URL.revokeObjectURL(url);
        document.body.removeChild(a);
      }, 0);
      setStatus("Downloaded import error report.", "success");
    }

    

    async function geocodeAndAdd(item) {
      var address = item && item.value ? item.value : item;
      var displayLabel =
        (item && item.label ? item.label : null) ||
        (item && item.row ? "Row " + item.row : "Unknown address");
      if (!address) {
        recordImportError({
          label: displayLabel,
          reason: "Empty address",
        });
        return false;
      }
      try {
        var base = (KC_OSRM && KC_OSRM.geocodeUrl) || "https://photon.komoot.io/api";
        var url = base;
        if (url.indexOf("?") === -1) {
          url += "?";
        } else if (!/[?&]$/.test(url)) {
          url += "&";
        }
        url += "q=" + encodeURIComponent(address) + "&limit=1";
        var response = await fetch(url, {
          headers: { Accept: "application/json" },
        });
        if (!response.ok) {
          throw new Error("HTTP " + response.status);
        }
        var json = await response.json();
        var feat = json && json.features && json.features[0];
        if (!feat || !feat.geometry || !feat.geometry.coordinates) {
          throw new Error("No result");
        }
        var coords = feat.geometry.coordinates;
        var lon = coords[0];
        var lat = coords[1];
        if (!isFinite(lat) || !isFinite(lon)) {
          throw new Error("Invalid coordinates");
        }
        addStopAt(L.latLng(lat, lon));
        return true;
      } catch (error) {
        var message = error && error.message ? error.message : "Geocode failed";
        recordImportError({
          label: displayLabel,
          reason: message,
        });
        return false;
      }
    }

    function geocodeSequential(addresses) {
      (async function () {
        var success = 0;
        for (var i = 0; i < addresses.length; i++) {
          var ok = false;
          try {
            ok = await geocodeAndAdd(addresses[i]);
          } catch (error) {
            ok = false;
          }
          if (ok) {
            success += 1;
          }
          if (i < addresses.length - 1) {
            await new Promise(function (resolve) {
              setTimeout(resolve, geocodeDelayMs);
            });
          }
        }
        updateErrorReportButton();
        var message =
          "Imported " + success + " of " + addresses.length + " address(es).";
        if (importErrors.length) {
          message += " Download errors for details.";
        }
        var type =
          success === addresses.length
            ? "success"
            : success > 0
            ? "warn"
            : "error";
        setStatus(message, type);
      })();
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
      var gpsStartApplied = ensureCurrentIsStart(lat, lon);
      updatePosition(lat, lon);

      if (gpsStartApplied && map && typeof map.panTo === "function") {
        setFollowMode(true);
        map.panTo([lat, lon], { animate: true });
      }
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
      nativeSpeak(text).then(function (spoken) {
        if (spoken) {
          return;
        }
        if (!nativeAvailable()) {
          speakWeb(text);
          return;
        }
        try {
          sendNative({ type: "kc:tts", text: text });
        } catch (error) {
          // ignore
        }
        speakWeb(text);
      });
    };

    function clearFallbackTimer() {
      if (fallbackTimerId != null) {
        clearTimeout(fallbackTimerId);
        fallbackTimerId = null;
      }
    }

    function stopGeoWatch() {
      if (
        geoWatchId != null &&
        navigator.geolocation &&
        typeof navigator.geolocation.clearWatch === "function"
      ) {
        navigator.geolocation.clearWatch(geoWatchId);
      }
      geoWatchId = null;
      geoWatchReason = null;
    }

    function startGeoWatch(reason) {
      if (
        geoWatchId != null ||
        !navigator.geolocation ||
        typeof navigator.geolocation.watchPosition !== "function"
      ) {
        return;
      }
      geoWatchReason = reason || "web";
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
    }

    window.kcNavStart = function (primedFromHandler) {
      if (navRunning) {
        return;
      }
      var primedThisCall = primedFromHandler === true ? true : primeTTS();
      navRunning = true;
      receivedNativePosition = false;
      if (preferGpsStart) {
        firstFixApplied = false;
      }
      clearFallbackTimer();
      renderFabState();
      if (nativeAvailable()) {
        sendNative({ type: "kc:navigation:start" });
        fallbackTimerId = window.setTimeout(function () {
          if (!navRunning || receivedNativePosition) {
            return;
          }
          startGeoWatch("fallback");
        }, 5000);
      } else {
        startGeoWatch("web");
      }
      if (!primedThisCall) {
        window.kcSay("Navigation started");
      }
    };

    window.kcNavStop = function () {
      if (!navRunning) {
        return;
      }
      navRunning = false;
      receivedNativePosition = false;
      clearFallbackTimer();
      renderFabState();
      if (nativeAvailable()) {
        sendNative({ type: "kc:navigation:stop" });
      }
      stopGeoWatch();
    };

    if (btnStart && !btnStart.dataset.kcFabBound) {
      btnStart.addEventListener("click", function () {
        var primed = primeTTS();
        window.kcNavStart(primed);
      });
      btnStart.dataset.kcFabBound = "1";
    }

    var primaryStartButton = document.getElementById("kc-start");
    if (primaryStartButton && !primaryStartButton.dataset.kcPrimeBound) {
      primaryStartButton.addEventListener("click", primeTTS);
      primaryStartButton.dataset.kcPrimeBound = "1";
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
            receivedNativePosition = true;
            clearFallbackTimer();
            if (geoWatchId != null && geoWatchReason === "fallback") {
              stopGeoWatch();
            }
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

    function routeIfReady(potentialWaypoints) {
      if (
        !routingControl ||
        !routingControl.options ||
        routingControl.options.autoRoute !== false ||
        typeof routingControl.route !== "function"
      ) {
        return;
      }

      var source;
      if (Array.isArray(potentialWaypoints)) {
        source = potentialWaypoints;
      } else if (typeof routingControl.getWaypoints === "function") {
        source = routingControl.getWaypoints();
      } else {
        source = [];
      }

      var active = source.filter(function (wp) {
        return (
          wp &&
          wp.latLng &&
          typeof wp.latLng.lat === "number" &&
          typeof wp.latLng.lng === "number"
        );
      });

      if (active.length >= 2) {
        routingControl.route();
      }
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

      var lat = null;
      var lng = null;

      if (typeof latLng.lat === "number" && typeof latLng.lng === "number") {
        lat = latLng.lat;
        lng = latLng.lng;
      } else if (
        latLng.latLng &&
        typeof latLng.latLng.lat === "number" &&
        typeof latLng.latLng.lng === "number"
      ) {
        lat = latLng.latLng.lat;
        lng = latLng.latLng.lng;
      }

      if (!isFinite(lat) || !isFinite(lng)) {
        return;
      }

      var wps = getWaypoints();
      var waypoint = {
        lat: lat,
        lng: lng,
        name: latLng.name || "",
      };

      if (typeof index === "number" && index >= 0 && index <= wps.length) {
        wps.splice(index, 0, waypoint);
      } else {
        var insertAt = wps.length <= 1 ? wps.length : Math.max(1, wps.length - 1);
        wps.splice(insertAt, 0, waypoint);
      }

      setWaypoints(wps);
    }

    function clearAll() {
      if (!routingControl) return;
      setWaypoints([]);
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

      var roundtrip = roundtripToggle.input.checked;
      var fixStart = fixStartToggle.input.checked;
      var fixEnd = fixEndToggle.input.checked;
      var queryString = "";
      if (typeof URLSearchParams === "function") {
        var params = new URLSearchParams({
          roundtrip: roundtrip ? "true" : "false",
          overview: "full",
          steps: "true",
        });
        if (!roundtrip) {
          if (fixStart) {
            params.set("source", "first");
          }
          if (fixEnd) {
            params.set("destination", "last");
          }
        }
        queryString = params.toString();
      } else {
        var fallbackParams = [
          "roundtrip=" + (roundtrip ? "true" : "false"),
          "overview=full",
          "steps=true",
        ];
        if (!roundtrip) {
          if (fixStart) {
            fallbackParams.push("source=first");
          }
          if (fixEnd) {
            fallbackParams.push("destination=last");
          }
        }
        queryString = fallbackParams.join("&");
      }
      url += "?" + queryString;

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

      var stops = [];
      for (var i = 0; i < pts.length; i++) {
        if (i === 0 || i === pts.length - 1) {
          continue;
        }
        stops.push(pts[i]);
      }

      if (!stops.length) {
        setStatus("No intermediate stops to export.", "warn");
        return;
      }

      function csvCell(value) {
        var str = value == null ? "" : String(value);
        if (/[",\n]/.test(str)) {
          str = '"' + str.replace(/"/g, '""') + '"';
        }
        return str;
      }

      var rows = ["name,address,lat,lon"];
      for (var j = 0; j < stops.length; j++) {
        var stop = stops[j];
        var lat = typeof stop.lat === "number" ? stop.lat : "";
        var lon = typeof stop.lng === "number" ? stop.lng : "";
        rows.push(
          [
            csvCell(stop.name || ""),
            csvCell(""),
            csvCell(lat),
            csvCell(lon),
          ].join(",")
        );
      }

      var csv = rows.join("\n");
      var blob = new Blob([csv], { type: "text/csv" });
      var url = URL.createObjectURL(blob);
      var a = document.createElement("a");
      a.href = url;
      a.download =
        "kerbcycle-stops-" + new Date().toISOString().replace(/[:.]/g, "-") + ".csv";
      document.body.appendChild(a);
      a.click();
      setTimeout(function () {
        URL.revokeObjectURL(url);
        document.body.removeChild(a);
      }, 0);
      setStatus(
        "Exported " + stops.length + " stop(s) to CSV (Start/Finish excluded).",
        "success"
      );
    }

    function registerEvents() {
      updateErrorReportButton();

      addStopBtn.addEventListener("click", function () {
        setAddStopMode(!addStopMode);
      });

      importBtn.addEventListener("click", function () {
        fileInput.click();
      });

      fileInput.addEventListener("change", function () {
        var selected = fileInput.files && fileInput.files[0];
        if (selected) {
          handleImportFile(selected);
        }
        fileInput.value = "";
      });

      errorReportBtn.addEventListener("click", function () {
        downloadErrorReport();
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
      saveDefaultBtn.addEventListener("click", function () {
        var wps = getWaypoints();
        if (!wps.length) {
          setStatus("No start point to save.", "error");
          return;
        }
        var first = wps[0] || {};
        var lat = typeof first.lat === "number" ? first.lat : NaN;
        var lng = typeof first.lng === "number" ? first.lng : NaN;
        if (!isFinite(lat) || !isFinite(lng)) {
          setStatus("No valid start point to save.", "error");
          return;
        }
        var value = lat.toFixed(6) + "," + lng.toFixed(6);
        var saved = false;
        try {
          if (typeof window !== "undefined" && window.localStorage) {
            window.localStorage.setItem("kc_default_start", value);
            saved = true;
          }
        } catch (error) {
          console.warn("Unable to save default start", error);
        }
        if (saved) {
          setStatus("Saved default start for this browser.", "success");
        } else {
          setStatus("Unable to access browser storage.", "error");
        }
      });
      resetDefaultBtn.addEventListener("click", function () {
        var cleared = false;
        try {
          if (typeof window !== "undefined" && window.localStorage) {
            window.localStorage.removeItem("kc_default_start");
            cleared = true;
          }
        } catch (error) {
          console.warn("Unable to clear default start", error);
        }
        if (cleared) {
          setStatus(
            "Per-user default cleared. Reload to use shortcode/site default.",
            ""
          );
        } else {
          setStatus("Unable to access browser storage.", "error");
        }
      });
      clearBtn.addEventListener("click", function () {
        clearAll();
        setAddStopMode(false);
      });
      if (gpsStartToggle && gpsStartToggle.input) {
        gpsStartToggle.input.addEventListener("change", function () {
          preferGpsStart = !!gpsStartToggle.input.checked;
          if (preferGpsStart) {
            firstFixApplied = false;
          }
        });
      }
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
        waypoints: [],
        autoRoute: false,
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
        .on("waypointschanged", function () {
          routeIfReady();
        })
        .on("routingstart", function () {
          setStatus("Routing…", "");
          clearItinerary();
        })
        .on("routesfound", function (e) {
          setStatus("", "");
          clearItinerary();
          if (e && e.routes && e.routes[0]) {
            buildSteps(e.routes[0]);
            if (posMarker && typeof posMarker.getLatLng === "function") {
              var current = posMarker.getLatLng();
              onPosition(current.lat, current.lng);
            }
          }
          if (isMobile) {
            collapseItinerary();
          }
        })
        .addTo(map);

      function collapseItinerary() {
        var container = routingControl && routingControl._container;
        if (!container) {
          return;
        }
        container.classList.add("leaflet-routing-collapsed");
        container.classList.add("leaflet-routing-container-hide");
      }

      function clearItinerary() {
        var container = routingControl && routingControl._container;
        if (!container) {
          return;
        }

        var altWrap = container.querySelector(
          ".leaflet-routing-alternatives-container"
        );
        if (altWrap) {
          altWrap.innerHTML = "";
        }

        var altNodes = container.querySelectorAll(".leaflet-routing-alt");
        Array.prototype.forEach.call(altNodes, function (node) {
          node.innerHTML = "";
        });

        var summary = container.querySelector(".leaflet-routing-summary");
        if (summary) {
          summary.innerHTML = "";
        }

        var errors = container.querySelector(".leaflet-routing-error");
        if (errors) {
          errors.innerHTML = "";
        }

        var customHost = document.querySelector(
          ".kc-steps, #kc-steps, .kc-steps-list"
        );
        if (customHost) {
          customHost.innerHTML = "";
        }
      }

      var isMobile = false;
      if (typeof window !== "undefined" && window.matchMedia) {
        isMobile = window.matchMedia("(max-width: 768px)").matches;
      }

      clearItinerary();

      if (typeof requestAnimationFrame === "function") {
        requestAnimationFrame(clearItinerary);
      } else {
        setTimeout(clearItinerary, 0);
      }

      (function ensureClearedForAMoment() {
        if (typeof window === "undefined" || !window.MutationObserver) {
          return;
        }
        var container = routingControl && routingControl._container;
        if (!container) {
          return;
        }
        var observer = new MutationObserver(function () {
          clearItinerary();
        });
        observer.observe(container, { childList: true, subtree: true });
        setTimeout(function () {
          try {
            observer.disconnect();
          } catch (error) {}
        }, 1000);
      })();

      if (isMobile) {
        if (typeof requestAnimationFrame === "function") {
          requestAnimationFrame(collapseItinerary);
        } else {
          setTimeout(collapseItinerary, 0);
        }
      }

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
