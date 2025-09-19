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

function escapeHtml(value) {
  if (value === null || value === undefined) {
    return "";
  }
  return String(value).replace(/[&<>"']/g, (char) => {
    switch (char) {
      case "&":
        return "&amp;";
      case "<":
        return "&lt;";
      case ">":
        return "&gt;";
      case '"':
        return "&quot;";
      case "'":
        return "&#039;";
      default:
        return char;
    }
  });
}

function cssEscape(value) {
  const stringValue = value === null || value === undefined ? "" : String(value);
  if (window.CSS && typeof window.CSS.escape === "function") {
    return window.CSS.escape(stringValue);
  }
  return stringValue.replace(/(["'\\])/g, "\\$1");
}

function formatStatus(status) {
  if (!status) return "";
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function buildQrTableRow(row, record) {
  if (!row || !record) return;

  const dash = "—";
  const cells = [];
  row.innerHTML = "";
  row.dataset.qrCode = record.qr_code ? String(record.qr_code) : "";

  const idCell = document.createElement("td");
  idCell.textContent = record.id ? String(record.id) : dash;
  cells.push(idCell);

  const codeCell = document.createElement("td");
  const codeValue = record.qr_code ? String(record.qr_code) : "";
  codeCell.textContent = codeValue || dash;
  if (codeValue) {
    codeCell.title = codeValue;
  } else {
    codeCell.removeAttribute("title");
  }
  cells.push(codeCell);

  const userCell = document.createElement("td");
  if (record.user_id) {
    userCell.textContent = String(record.user_id);
  } else {
    userCell.textContent = dash;
  }
  cells.push(userCell);

  const displayCell = document.createElement("td");
  const displayName = record.display_name ? String(record.display_name) : "";
  displayCell.textContent = displayName || dash;
  if (displayName) {
    displayCell.title = displayName;
  } else {
    displayCell.title = dash;
  }
  cells.push(displayCell);

  const statusCell = document.createElement("td");
  statusCell.textContent = formatStatus(record.status);
  cells.push(statusCell);

  const assignedCell = document.createElement("td");
  assignedCell.className = "kc-date";
  const assignedValue = record.assigned_at ? String(record.assigned_at) : "";
  if (assignedValue) {
    assignedCell.textContent = assignedValue;
    assignedCell.dataset.full = assignedValue;
    assignedCell.title = assignedValue;
  } else {
    assignedCell.textContent = dash;
    assignedCell.dataset.full = "";
    assignedCell.title = dash;
  }
  cells.push(assignedCell);

  cells.forEach((cell) => row.appendChild(cell));
}

function updateFrontendQrTable(record) {
  if (!record) {
    return { updated: false, existed: false };
  }

  const table = document.querySelector(".kerbcycle-qr-table");
  if (!table) {
    return { updated: false, existed: false };
  }
  const tbody = table.querySelector("tbody");
  if (!tbody) {
    return { updated: false, existed: false };
  }

  const code = record.qr_code ? String(record.qr_code) : "";
  const selector = code
    ? `tr[data-qr-code="${cssEscape(code)}"]`
    : null;
  let row = selector ? tbody.querySelector(selector) : null;
  const existed = !!row;

  if (!row) {
    const emptyRow = tbody.querySelector("td.description")?.parentElement;
    if (emptyRow) {
      emptyRow.remove();
    }
    row = document.createElement("tr");
    buildQrTableRow(row, record);

    const recordId = record.id ? Number(record.id) : NaN;
    const rows = Array.from(tbody.querySelectorAll("tr"));
    let inserted = false;
    if (!Number.isNaN(recordId)) {
      for (const existingRow of rows) {
        const idCell = existingRow.querySelector("td");
        if (!idCell || idCell.classList.contains("description")) {
          continue;
        }
        const existingId = Number(idCell.textContent || 0);
        if (Number.isNaN(existingId)) {
          continue;
        }
        if (existingId < recordId) {
          tbody.insertBefore(row, existingRow);
          inserted = true;
          break;
        }
      }
    }
    if (!inserted) {
      tbody.appendChild(row);
    }
  } else {
    buildQrTableRow(row, record);
  }

  const mm = window.matchMedia("(max-width: 480px)");
  updateQrDatesView(mm.matches);

  const pagination = document.querySelector(".kerbcycle-qr-pagination");
  if (pagination) {
    const rowsPerPage = parseInt(pagination.dataset.rows || "10", 10);
    const currentPage =
      existed && table._kcPagination
        ? table._kcPagination.currentPage || 1
        : 1;
    paginateQrTable(table, pagination, rowsPerPage, currentPage);
  }

  return { updated: true, existed };
}

function setScanResult(element, type, html) {
  if (!element) return;
  element.style.display = "block";
  element.classList.remove("error", "updated");
  if (type === "error") {
    element.classList.add("error");
  } else {
    element.classList.add("updated");
  }
  element.innerHTML = html;
}

function updateQrDatesView(isMobile) {
  document
    .querySelectorAll(".kerbcycle-qr-scanner-container tbody tr")
    .forEach((tr) => {
      const td =
        tr.querySelector("td.kc-date") || tr.querySelector("td:nth-child(6)");
      if (!td) return;

      const fullDate = td.getAttribute("data-full");
      if (!fullDate) return; // Can't do anything without the full date.

      if (isMobile) {
        const m = fullDate.match(/^(\d{4})-(\d{2})-(\d{2})\s(\d{2}):(\d{2})/);
        if (m) {
          td.textContent = `${m[2]}/${m[3]} ${m[4]}:${m[5]}`;
        }
      } else {
        // Revert to the full date
        td.textContent = fullDate;
      }
    });
}

function initResponsiveDates() {
  const mm = window.matchMedia("(max-width: 480px)");

  // Set initial state
  updateQrDatesView(mm.matches);

  // Listen for changes
  mm.addEventListener("change", (e) => {
    updateQrDatesView(e.matches);
  });
}

function initKerbcycleScanner() {
  document
    .querySelectorAll("select.kc-searchable")
    .forEach(makeSearchableSelect);
  const scannerAllowed = kerbcycle_ajax.scanner_enabled;
  const scanResult = document.getElementById("scan-result");
  const assignBtn = document.getElementById("assign-qr-btn");
  const resetBtn = document.getElementById("reset-scan-btn");
  const customerIdField = document.getElementById("customer-id");
  let scannedCode = "";
  let lastDecodedText = null;

  let scanner = null;
  const scannerCameraConfig = { facingMode: "environment" };
  const scannerStartConfig = { fps: 10, qrbox: 250 };
  let scannerStateHint = "NOT_STARTED";
  let scannerActivationPromise = null;

  function getScannerState() {
    if (scanner && typeof scanner.getState === "function") {
      try {
        return scanner.getState();
      } catch (stateError) {
        // Fall back to our internal hint when the library cannot report the state.
        return scannerStateHint;
      }
    }
    return scannerStateHint;
  }

  function getScannerStateLabel() {
    const rawState = getScannerState();

    if (typeof rawState === "string" && rawState) {
      return rawState;
    }

    if (typeof rawState === "number") {
      switch (rawState) {
        case 3:
          return "SCANNING";
        case 2:
          return "PAUSED";
        case 4:
          return "STOPPED";
        case 1:
          return "NOT_STARTED";
        default:
          return scannerStateHint;
      }
    }

    return scannerStateHint;
  }

  function updateScannerStateHint(state) {
    if (typeof state === "string" && state) {
      scannerStateHint = state;
    }
  }

  function pauseActiveScanner() {
    if (!scannerAllowed || !scanner || typeof scanner.pause !== "function") {
      return;
    }

    const currentState = getScannerStateLabel();
    if (currentState === "PAUSED") {
      updateScannerStateHint("PAUSED");
      return;
    }

    try {
      const pauseResult = scanner.pause();
      updateScannerStateHint("PAUSED");
      if (pauseResult && typeof pauseResult.catch === "function") {
        pauseResult.catch((pauseError) => {
          console.warn("Unable to pause scanner", pauseError);
          updateScannerStateHint("SCANNING");
        });
      }
    } catch (pauseError) {
      console.warn("Unable to pause scanner", pauseError);
      updateScannerStateHint("SCANNING");
    }
  }

  function displayScannerStartError(error) {
    if (!scanResult) {
      return;
    }
    const safeErr = escapeHtml(String(error));
    setScanResult(
      scanResult,
      "error",
      `<strong>❌ Unable to start scanner.</strong> Please ensure you have a camera and have granted permission.<br>${safeErr}`,
    );
  }

  function activateScanner(options = {}) {
    const { clearCode = false, showError = false } = options;

    if (clearCode) {
      scannedCode = "";
      lastDecodedText = null;
    }

    if (!scannerAllowed || !scanner) {
      return;
    }

    if (scannerActivationPromise) {
      return;
    }

    const currentState = getScannerStateLabel();
    if (currentState === "SCANNING") {
      updateScannerStateHint("SCANNING");
      return;
    }

    let activation;
    let attemptedResume = false;
    if (currentState === "PAUSED" && typeof scanner.resume === "function") {
      attemptedResume = true;
      try {
        activation = scanner.resume();
      } catch (resumeError) {
        console.warn("Unable to resume scanner", resumeError);
        if (showError) {
          displayScannerStartError(resumeError);
        }
        return;
      }
    } else {
      try {
        activation = scanner.start(
          scannerCameraConfig,
          scannerStartConfig,
          onScanSuccess,
        );
      } catch (startError) {
        console.error("Unable to start scanning", startError);
        if (showError) {
          displayScannerStartError(startError);
        }
        updateScannerStateHint("STOPPED");
        return;
      }
    }

    if (activation && typeof activation.then === "function") {
      scannerActivationPromise = activation;
      activation
        .then(() => {
          updateScannerStateHint("SCANNING");
        })
        .catch((activationError) => {
          console.error("Unable to activate scanner", activationError);
          const message = String(activationError || "");

          if (attemptedResume && typeof scanner.start === "function") {
            try {
              const restart = scanner.start(
                scannerCameraConfig,
                scannerStartConfig,
                onScanSuccess,
              );

              if (restart && typeof restart.then === "function") {
                scannerActivationPromise = restart;
                restart
                  .then(() => {
                    updateScannerStateHint("SCANNING");
                  })
                  .catch((restartError) => {
                    console.error("Unable to restart scanner", restartError);
                    updateScannerStateHint("STOPPED");
                    if (showError) {
                      displayScannerStartError(restartError);
                    }
                  })
                  .finally(() => {
                    if (scannerActivationPromise === restart) {
                      scannerActivationPromise = null;
                    }
                  });
                return restart;
              }

              updateScannerStateHint("SCANNING");
              if (scannerActivationPromise === activation) {
                scannerActivationPromise = null;
              }
              return null;
            } catch (restartError) {
              console.error("Unable to restart scanner", restartError);
              updateScannerStateHint("STOPPED");
              if (showError) {
                displayScannerStartError(restartError);
              }
              return null;
            }
          }

          if (message.includes("clear while scan is ongoing")) {
            updateScannerStateHint("SCANNING");
            return null;
          }

          updateScannerStateHint("STOPPED");
          if (showError) {
            displayScannerStartError(activationError);
          }
          return null;
        })
        .finally(() => {
          if (scannerActivationPromise === activation) {
            scannerActivationPromise = null;
          }
        });
    } else {
      updateScannerStateHint("SCANNING");
    }
  }

  if (
    scannerAllowed &&
    typeof Html5Qrcode !== "undefined" &&
    document.getElementById("reader")
  ) {
    scanner = new Html5Qrcode("reader", true);

    function onScanSuccess(decodedText) {
      const normalizedText =
        decodedText === null || decodedText === undefined
          ? ""
          : String(decodedText);

      if (normalizedText && normalizedText === lastDecodedText) {
        return;
      }

      lastDecodedText = normalizedText;

      pauseActiveScanner();
      scannedCode = normalizedText;
      const safeCode = escapeHtml(normalizedText);
      setScanResult(
        scanResult,
        "success",
        `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${safeCode}</code>`,
      );
    }
    activateScanner({ showError: true });
  }

  if (assignBtn) {
    assignBtn.addEventListener("click", () => {
      const userId = customerIdField ? customerIdField.value : "";

      if (!userId || !scannedCode) {
        setScanResult(
          scanResult,
          "error",
          "<strong>❌ Please select a customer and scan a QR code before assigning.</strong>",
        );
        return;
      }

      assignBtn.disabled = true;
      assignBtn.setAttribute("aria-busy", "true");

      const params = new URLSearchParams({
        action: "assign_qr_code",
        qr_code: scannedCode,
        customer_id: userId,
        security: kerbcycle_ajax.nonce,
      });

      fetch(kerbcycle_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: params,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            const record = data.data ? data.data.record : null;
            if (record) {
              updateFrontendQrTable(record);
            }

            const assignedCode = scannedCode;
            const selectedOption =
              customerIdField && customerIdField.selectedIndex >= 0
                ? customerIdField.options[customerIdField.selectedIndex]
                : null;
            const customerLabel = selectedOption
              ? selectedOption.textContent.trim()
              : "";

            const messageParts = [
              "<strong>✅ QR code assigned successfully.</strong>",
            ];
            if (assignedCode) {
              messageParts.push(
                `Code: <code>${escapeHtml(assignedCode)}</code>`,
              );
            }
            if (customerLabel) {
              messageParts.push(
                `Customer: ${escapeHtml(customerLabel)}`,
              );
            }
            messageParts.push("Scan another code to continue.");

            setScanResult(scanResult, "success", messageParts.join("<br>"));

            if (customerIdField) {
              const placeholderIndex = Array.from(
                customerIdField.options || [],
              ).findIndex((option) => !option.value || option.value === "-1");

              if (placeholderIndex >= 0) {
                customerIdField.selectedIndex = placeholderIndex;
              } else {
                customerIdField.selectedIndex = -1;
              }

              customerIdField.value = "";
              customerIdField.dispatchEvent(
                new Event("change", { bubbles: true }),
              );

              const enhanced = customerIdField._kcEnhanced;
              if (enhanced && enhanced.input) {
                enhanced.input.value = "";
              }
              if (enhanced && typeof enhanced.closeList === "function") {
                enhanced.closeList();
              }
            }

            scannedCode = "";
          } else {
            const err =
              data.data && data.data.message
                ? data.data.message
                : "Failed to assign QR code.";
            setScanResult(
              scanResult,
              "error",
              `<strong>❌ ${escapeHtml(err)}</strong>`,
            );
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          setScanResult(
            scanResult,
            "error",
            `<strong>❌ An error occurred while assigning the QR code.</strong><br>${escapeHtml(
              String(error),
            )}`,
          );
        })
        .finally(() => {
          assignBtn.disabled = false;
          assignBtn.removeAttribute("aria-busy");

          lastDecodedText = null;

          // Always attempt to reactivate the scanner after handling the
          // assignment request, even when the server returns an error.
          activateScanner({ showError: true });
        });
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener("click", () => {
      scannedCode = "";
      lastDecodedText = null;

      if (scanResult) {
        scanResult.style.display = "none";
        scanResult.classList.remove("error", "updated");
        scanResult.innerHTML = "";
      }

      activateScanner({ clearCode: true, showError: true });
    });
  }

  const table = document.querySelector(".kerbcycle-qr-table");
  const pagination = document.querySelector(".kerbcycle-qr-pagination");
  if (table && pagination) {
    const rowsPerPage = parseInt(pagination.dataset.rows || "10", 10);
    paginateQrTable(table, pagination, rowsPerPage);
  }
}

function setupKerbcycleFrontend() {
  initKerbcycleScanner();
  initResponsiveDates();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", setupKerbcycleFrontend);
} else {
  setupKerbcycleFrontend();
}

// Ensure new rows added via pagination also get the correct date format
const kcContainer = document.querySelector(".kerbcycle-qr-scanner-container");
if (kcContainer) {
  const mm = window.matchMedia("(max-width: 480px)");
  const mo = new MutationObserver(() => updateQrDatesView(mm.matches));
  mo.observe(kcContainer, { childList: true, subtree: true });
}

function paginateQrTable(table, pagination, rowsPerPage, targetPage) {
  const perPage =
    Number.isFinite(rowsPerPage) && rowsPerPage > 0 ? rowsPerPage : 10;
  const rows = Array.from(table.querySelectorAll("tbody tr"));

  pagination.innerHTML = "";

  if (!rows.length) {
    table._kcPagination = {
      currentPage: 1,
      rowsPerPage: perPage,
      totalPages: 0,
      pagination,
    };
    return;
  }

  const totalPages = Math.ceil(rows.length / perPage);

  if (totalPages <= 1) {
    rows.forEach((row) => {
      row.style.display = "";
    });
    table._kcPagination = {
      currentPage: 1,
      rowsPerPage: perPage,
      totalPages,
      pagination,
    };
    return;
  }

  const state = table._kcPagination || { currentPage: 1 };

  const renderPage = (page) => {
    const safePage = Math.max(1, Math.min(page, totalPages));
    state.currentPage = safePage;
    const start = (safePage - 1) * perPage;
    const end = start + perPage;
    rows.forEach((row, index) => {
      row.style.display = index >= start && index < end ? "" : "none";
    });
    pagination
      .querySelectorAll("button")
      .forEach((btn) => btn.classList.remove("active"));
    const active = pagination.querySelector(`button[data-page="${safePage}"]`);
    if (active) active.classList.add("active");
  };

  for (let i = 1; i <= totalPages; i++) {
    const btn = document.createElement("button");
    btn.textContent = i;
    btn.dataset.page = i;
    btn.addEventListener("click", () => renderPage(i));
    pagination.appendChild(btn);
  }

  state.rowsPerPage = perPage;
  state.totalPages = totalPages;
  state.pagination = pagination;
  table._kcPagination = state;

  const desiredPage =
    typeof targetPage === "number" ? targetPage : state.currentPage || 1;
  renderPage(desiredPage);
}
