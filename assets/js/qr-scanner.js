function makeSearchableSelect(select) {
  if (!select || select._kcEnhanced) return;

  // Wrapper
  const wrapper = document.createElement("div");
  wrapper.className = "kc-combobox";

  // Hidden original select stays for value + form submit
  select.style.display = "none";
  select.parentNode.insertBefore(wrapper, select);
  wrapper.appendChild(select);

  // Visible input + caret button
  const input = document.createElement("input");
  input.type = "text";
  input.className = "kc-combobox-input";
  input.placeholder = select.getAttribute("data-placeholder") || "Select…";
  input.autocomplete = "off";
  input.inputMode = "search";
  input.setAttribute("aria-autocomplete", "list");
  input.setAttribute("aria-expanded", "false");
  input.setAttribute("role", "combobox");

  const btn = document.createElement("button");
  btn.type = "button";
  btn.className = "kc-combobox-toggle";
  btn.setAttribute("aria-label", "Open options");
  btn.innerHTML = "▾";

  const list = document.createElement("ul");
  list.className = "kc-combobox-list";
  list.setAttribute("role", "listbox");
  list.hidden = true;

  wrapper.appendChild(input);
  wrapper.appendChild(btn);
  wrapper.appendChild(list);

  // Build items from <select> options
  function buildList() {
    list.innerHTML = "";
    const q = input.value.trim().toLowerCase();
    const opts = Array.from(select.querySelectorAll("option")).filter(
      (opt) => opt.value && opt.value !== "-1",
    );
    let any = false;
    opts.forEach((opt) => {
      const label = (opt.textContent || "").trim();
      if (!q || label.toLowerCase().includes(q)) {
        const li = document.createElement("li");
        li.className = "kc-combobox-item";
        li.textContent = label;
        li.setAttribute("role", "option");
        li.dataset.value = opt.value;
        li.tabIndex = -1;
        list.appendChild(li);
        any = true;
      }
    });
    if (!any) {
      const li = document.createElement("li");
      li.className = "kc-combobox-empty";
      li.textContent = "No matches";
      li.setAttribute("aria-disabled", "true");
      list.appendChild(li);
    }
  }

  function openList() {
    buildList();
    list.hidden = false;
    input.setAttribute("aria-expanded", "true");
  }

  function closeList() {
    list.hidden = true;
    input.setAttribute("aria-expanded", "false");
  }

  function commitValue(label) {
    // Find the option by label text
    const found = Array.from(select.options).find(
      (o) => (o.textContent || "").trim() === label.trim(),
    );
    if (found) {
      select.value = found.value;
      input.value = found.textContent;
      select.dispatchEvent(new Event("change", { bubbles: true }));
    } else {
      // Clear if not an exact match
      select.value = "";
    }
    closeList();
  }

  // Initialize input with currently selected option
  if (select.value && select.value !== "-1") {
    const cur = select.options[select.selectedIndex];
    if (cur) input.value = cur.textContent || "";
  }

  // Events — keyboard, click, touch
  input.addEventListener("focus", openList);
  input.addEventListener("input", openList);

  // Enter/Arrow nav
  input.addEventListener("keydown", (e) => {
    if (list.hidden) return;
    const items = Array.from(list.querySelectorAll(".kc-combobox-item"));
    const active = document.activeElement;
    const idx = items.indexOf(active);

    if (e.key === "ArrowDown") {
      e.preventDefault();
      const next =
        items[Math.max(0, Math.min(items.length - 1, idx + 1))] || items[0];
      next?.focus();
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      const prev =
        items[Math.max(0, Math.min(items.length - 1, idx - 1))] ||
        items[items.length - 1];
      prev?.focus();
    } else if (e.key === "Enter") {
      e.preventDefault();
      if (active && active.classList.contains("kc-combobox-item")) {
        commitValue(active.textContent || "");
      } else if (items.length) {
        commitValue(items[0].textContent || "");
      }
    } else if (e.key === "Escape") {
      closeList();
    }
  });

  // Click/tap on caret button toggles list
  ["click", "pointerdown", "touchstart"].forEach((ev) => {
    btn.addEventListener(ev, (e) => {
      e.preventDefault();
      if (list.hidden) openList();
      else closeList();
    });
  });

  // Click/tap on options
  ["click", "pointerdown", "touchstart"].forEach((ev) => {
    list.addEventListener(ev, (e) => {
      const li = e.target.closest(".kc-combobox-item");
      if (!li) return;
      e.preventDefault();
      commitValue(li.textContent || "");
    });
  });

  // Close when clicking outside
  document.addEventListener("click", (e) => {
    if (!wrapper.contains(e.target)) closeList();
  });

  // Mark enhanced to avoid double init
  select._kcEnhanced = {
    input,
    btn,
    list,
    openList,
    closeList,
    refresh: buildList,
  };
}

const kcCompactMedia =
  typeof window.matchMedia === "function"
    ? window.matchMedia("(max-width: 480px)")
    : null;

function shortenQrDates() {
  const isCompact = kcCompactMedia ? kcCompactMedia.matches : false;

  document
    .querySelectorAll(".kerbcycle-qr-scanner-container tbody tr")
    .forEach((tr) => {
      const td =
        tr.querySelector("td.kc-date") || tr.querySelector("td:nth-child(6)");
      if (!td) return;

      if (!td.dataset.kcOriginal) {
        td.dataset.kcOriginal = (td.textContent || "").trim();
      }

      const attrFull = td.getAttribute("data-full");
      const original = td.dataset.kcOriginal || "";
      const full = attrFull && attrFull.trim() ? attrFull.trim() : original;

      if (!isCompact) {
        td.textContent = full || original || "—";
        return;
      }

      if (!full || full === "—") {
        td.textContent = "—";
        return;
      }

      const m = full.match(/^(\d{4})-(\d{2})-(\d{2})\s(\d{2}):(\d{2})/);
      td.textContent = m ? `${m[2]}/${m[3]} ${m[4]}:${m[5]}` : full;
    });
}

function initKerbcycleScanner() {
  document
    .querySelectorAll("select.kc-searchable")
    .forEach(makeSearchableSelect);
  const scannerAllowed = kerbcycle_ajax.scanner_enabled;
  const scanResult = document.getElementById("scan-result");
  const assignBtn = document.getElementById("assign-qr-btn");
  const customerIdField = document.getElementById("customer-id");
  let scannedCode = "";

  if (
    scannerAllowed &&
    typeof Html5Qrcode !== "undefined" &&
    document.getElementById("reader")
  ) {
    const scanner = new Html5Qrcode("reader", true);

    function onScanSuccess(decodedText) {
      scanner.pause();
      scannedCode = decodedText;
      scanResult.style.display = "block";
      scanResult.classList.add("updated");
      scanResult.innerHTML = `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${decodedText}</code>`;
    }

    scanner
      .start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        onScanSuccess,
      )
      .catch((err) => {
        console.error(`Unable to start scanning, error: ${err}`);
        scanResult.style.display = "block";
        scanResult.classList.add("error");
        scanResult.innerHTML =
          "<strong>❌ Unable to start scanner.</strong> Please ensure you have a camera and have granted permission.";
      });
  }

  if (assignBtn) {
    assignBtn.addEventListener("click", function () {
      const userId = customerIdField ? customerIdField.value : "";

      if (!userId || !scannedCode) {
        alert("Please select a customer and scan a QR code.");
        return;
      }

      fetch(kerbcycle_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: new URLSearchParams({
          action: "assign_qr_code",
          qr_code: scannedCode,
          customer_id: userId,
          security: kerbcycle_ajax.nonce,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            alert("QR code assigned successfully.");
            location.reload();
          } else {
            const err =
              data.data && data.data.message
                ? data.data.message
                : "Failed to assign QR code.";
            alert(err);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          alert("An error occurred while assigning the QR code.");
        });
    });
  }

  const table = document.querySelector(".kerbcycle-qr-table");
  const pagination = document.querySelector(".kerbcycle-qr-pagination");
  if (table && pagination) {
    const rowsPerPage = parseInt(pagination.dataset.rows || "10", 10);
    paginateQrTable(table, pagination, rowsPerPage);
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initKerbcycleScanner);
} else {
  initKerbcycleScanner();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", shortenQrDates);
} else {
  shortenQrDates();
}

const kcContainer = document.querySelector(".kerbcycle-qr-scanner-container");
if (kcContainer) {
  const mo = new MutationObserver(shortenQrDates);
  mo.observe(kcContainer, { childList: true, subtree: true });
}

if (kcCompactMedia) {
  const mediaRefresh = () => shortenQrDates();
  if (typeof kcCompactMedia.addEventListener === "function") {
    kcCompactMedia.addEventListener("change", mediaRefresh);
  } else if (typeof kcCompactMedia.addListener === "function") {
    kcCompactMedia.addListener(mediaRefresh);
  }
}

function paginateQrTable(table, pagination, rowsPerPage) {
  const rows = Array.from(table.querySelectorAll("tbody tr"));
  const totalPages = Math.ceil(rows.length / rowsPerPage);
  let currentPage = 1;

  const renderPage = (page) => {
    currentPage = page;
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    rows.forEach((row, index) => {
      row.style.display = index >= start && index < end ? "" : "none";
    });
    pagination
      .querySelectorAll("button")
      .forEach((btn) => btn.classList.remove("active"));
    const active = pagination.querySelector(`button[data-page="${page}"]`);
    if (active) active.classList.add("active");

    shortenQrDates();
  };

  for (let i = 1; i <= totalPages; i++) {
    const btn = document.createElement("button");
    btn.textContent = i;
    btn.dataset.page = i;
    btn.addEventListener("click", () => renderPage(i));
    pagination.appendChild(btn);
  }

  if (totalPages > 0) {
    renderPage(1);
  }
}
