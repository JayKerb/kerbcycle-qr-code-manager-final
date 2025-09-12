function makeSearchableSelect(select) {
  if (!select) return;
  const listId = select.id + "-list";
  const dataList = document.createElement("datalist");
  dataList.id = listId;
  const input = document.createElement("input");
  input.setAttribute("list", listId);
  input.className = "kc-search-input";
  input.style.minWidth = "200px";
  const updateOptions = () => {
    dataList.innerHTML = "";
    Array.from(select.options).forEach((opt) => {
      const option = document.createElement("option");
      option.value = opt.textContent;
      option.dataset.value = opt.value;
      dataList.appendChild(option);
    });
  };
  updateOptions();
  input.addEventListener("input", () => {
    const found = dataList.querySelector(
      `option[value="${CSS.escape(input.value)}"]`,
    );
    select.value = found ? found.dataset.value : "";
    select.dispatchEvent(new Event("change"));
  });
  select.parentNode.insertBefore(input, select);
  select.parentNode.insertBefore(dataList, select);
  select.style.display = "none";
  select._searchable = { input, updateOptions };
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
