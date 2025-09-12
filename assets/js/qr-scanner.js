function makeSearchableSelect(select) {
  if (!select) return;

  // Create a wrapper for our custom elements
  const wrapper = document.createElement("div");
  wrapper.className = "kc-searchable-wrapper";
  select.parentNode.insertBefore(wrapper, select);

  // Create the fake input field
  const input = document.createElement("input");
  input.className = "kc-search-input";
  input.placeholder = "Type to search...";
  wrapper.appendChild(input);

  // Create the dropdown panel
  const dropdown = document.createElement("div");
  dropdown.className = "kc-search-dropdown";
  wrapper.appendChild(dropdown);

  // Hide the original select element, but leave it in place
  select.style.display = "none";

  // Function to build options in the dropdown
  const updateOptions = () => {
    dropdown.innerHTML = "";
    Array.from(select.options).forEach((opt) => {
      if (!opt.value) return; // Skip placeholder/empty options

      const optionDiv = document.createElement("div");
      optionDiv.className = "kc-search-option";
      optionDiv.textContent = opt.textContent;
      optionDiv.dataset.value = opt.value;

      // Use 'mousedown' to register click before input loses focus (blur)
      optionDiv.addEventListener("mousedown", () => {
        input.value = opt.textContent;
        select.value = opt.value;
        select.dispatchEvent(new Event("change"));
        dropdown.classList.remove("is-visible"); // Hide dropdown
      });
      dropdown.appendChild(optionDiv);
    });
  };

  updateOptions();

  // Show dropdown when the input is focused
  input.addEventListener("focus", () => {
    dropdown.classList.add("is-visible");
  });

  // Hide dropdown when clicking anywhere else on the page
  document.addEventListener("mousedown", (e) => {
    if (!wrapper.contains(e.target)) {
      dropdown.classList.remove("is-visible");
    }
  });

  // Filter results as the user types
  input.addEventListener("input", () => {
    // Make sure dropdown is visible while typing
    dropdown.classList.add("is-visible");

    const filter = input.value.toLowerCase();
    const options = dropdown.querySelectorAll(".kc-search-option");

    // Loop through options and hide/show based on filter
    options.forEach((opt) => {
      const text = opt.textContent.toLowerCase();
      opt.style.display = text.includes(filter) ? "" : "none";
    });

    // Also update the underlying select in case of an exact match
    const exactMatch = Array.from(select.options).find(
      (opt) => opt.textContent.toLowerCase() === input.value.toLowerCase(),
    );
    select.value = exactMatch ? exactMatch.value : "";
    select.dispatchEvent(new Event("change"));
  });

  // Storing a reference for potential future use, though not used in this impl.
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
