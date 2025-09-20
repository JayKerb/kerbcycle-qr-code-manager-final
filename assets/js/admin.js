function showToast(message, isError = false) {
  let toast = document.getElementById("qr-toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.id = "qr-toast";
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.className = isError ? "error show" : "show";
  setTimeout(() => toast.classList.remove("show"), 3000);
}

function makeSearchableSelect(select) {
  if (!select) return;
  const listId = select.id + "-list";
  const dataList = document.createElement("datalist");
  dataList.id = listId;
  const input = document.createElement("input");
  input.setAttribute("list", listId);
  input.className = "kc-search-input";
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

function initKerbcycleAdmin() {
  const qrSelect = document.getElementById("qr-code-select");
  const assignedSelect = document.getElementById("assigned-qr-code-select");
  const userField = document.getElementById("customer-id");
  const sendEmailCheckbox = document.getElementById("send-email");
  const sendSmsCheckbox = document.getElementById("send-sms");
  const sendReminderCheckbox = document.getElementById("send-reminder");
  const assignBtn = document.getElementById("assign-qr-btn");
  const releaseBtn = document.getElementById("release-qr-btn");
  const addBtn = document.getElementById("add-qr-btn");
  const newCodeInput = document.getElementById("new-qr-code");
  const importBtn = document.getElementById("import-qr-btn");
  const importFile = document.getElementById("import-qr-file");

  function adjustCounts(availChange, assignChange) {
    document.querySelectorAll(".qr-code-counts").forEach((el) => {
      const avail = el.querySelector(".qr-available-count");
      const assign = el.querySelector(".qr-assigned-count");
      if (avail) {
        avail.textContent = parseInt(avail.textContent, 10) + availChange;
      }
      if (assign) {
        assign.textContent = parseInt(assign.textContent, 10) + assignChange;
      }
    });
  }

  function refreshDropdowns(oldCode, newCode) {
    [qrSelect, assignedSelect].forEach((select) => {
      if (!select) return;
      const opt = select.querySelector(
        `option[value="${CSS.escape(oldCode)}"]`,
      );
      if (!opt) return;
      opt.value = newCode;
      opt.textContent = newCode;
      if (select.value === oldCode) {
        select.value = newCode;
        if (select._searchable) {
          select._searchable.input.value = newCode;
        }
      }
      if (select._searchable) {
        select._searchable.updateOptions();
      }
    });
  }

  function updateSelectAllState() {
    const selectAll = document.getElementById("qr-select-all");
    if (!selectAll) {
      return;
    }
    const checkboxes = document.querySelectorAll(
      "#qr-code-list .qr-item .qr-select",
    );
    if (!checkboxes.length) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
      return;
    }
    const allChecked = Array.from(checkboxes).every((cb) => cb.checked);
    const anyChecked = Array.from(checkboxes).some((cb) => cb.checked);
    selectAll.checked = allChecked;
    selectAll.indeterminate = !allChecked && anyChecked;
  }

  function handleInlineEditBlur(event) {
    const span = event.currentTarget;
    const li = span.closest("li");
    if (!li) {
      return;
    }
    const oldCode = li.dataset.code;
    const newCode = span.textContent.trim();
    if (oldCode === newCode) {
      return;
    }
    fetch(kerbcycle_ajax.ajax_url, {
      method: "POST",
      headers: {
        "Content-Type":
          "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: `action=update_qr_code&old_code=${encodeURIComponent(
        oldCode,
      )}&new_code=${encodeURIComponent(newCode)}&security=${
        kerbcycle_ajax.nonce
      }`,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          li.dataset.code = newCode;
          const msg =
            data.data && data.data.message
              ? data.data.message
              : "QR code updated";
          showToast(msg);
          refreshDropdowns(oldCode, newCode);
        } else {
          const err =
            data.data && data.data.message
              ? data.data.message
              : "Failed to update QR code";
          showToast(err, true);
          span.textContent = oldCode;
        }
      })
      .catch((error) => {
        console.error("Error updating QR code:", error);
        showToast("An error occurred while updating the QR code.", true);
        span.textContent = oldCode;
      });
  }

  function wireQrItem(li) {
    if (!li) {
      return;
    }
    const checkbox = li.querySelector(".qr-select");
    if (checkbox && !checkbox.dataset.qrWired) {
      checkbox.addEventListener("change", updateSelectAllState);
      checkbox.dataset.qrWired = "1";
    }
    const span = li.querySelector(".qr-text");
    if (span && !span.dataset.qrWired) {
      span.addEventListener("blur", handleInlineEditBlur);
      span.dataset.qrWired = "1";
    }
  }

  document
    .querySelectorAll("select.kc-searchable")
    .forEach(makeSearchableSelect);

  if (userField && assignedSelect) {
    userField.addEventListener("change", function () {
      const userId = userField.value;
      assignedSelect.innerHTML =
        '<option value="">Select Assigned QR Code</option>';
      if (assignedSelect._searchable) {
        assignedSelect._searchable.updateOptions();
        assignedSelect._searchable.input.value = "";
      }
      if (!userId) {
        return;
      }
      fetch(kerbcycle_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: `action=get_assigned_qr_codes&customer_id=${encodeURIComponent(userId)}&security=${kerbcycle_ajax.nonce}`,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success && Array.isArray(data.data)) {
            data.data.forEach((code) => {
              const opt = document.createElement("option");
              opt.value = code;
              opt.textContent = code;
              assignedSelect.appendChild(opt);
            });
            if (assignedSelect._searchable) {
              assignedSelect._searchable.updateOptions();
            }
          }
        });
    });
    userField.dispatchEvent(new Event("change"));
  }

  const cssEscape = (value) =>
    typeof CSS !== "undefined" && typeof CSS.escape === "function"
      ? CSS.escape(value)
      : value;

  function assignQrCodeToCustomer(rawQrCode, rawUserId, options = {}) {
    const {
      sendEmail = false,
      sendSms = false,
      sendReminder = false,
      showAlertOnMissing = false,
      source = "manual",
      customerName = "",
    } = options;

    const qrCode = rawQrCode ? rawQrCode.trim() : "";
    const userId = rawUserId ? String(rawUserId).trim() : "";

    if (!userId) {
      if (showAlertOnMissing) {
        alert("Please select a user.");
      }
      const detail = { code: qrCode, userId, source, reason: "missing-user" };
      document.dispatchEvent(
        new CustomEvent("kerbcycle-qr-code-assignment-failed", { detail }),
      );
      return Promise.resolve({ success: false, reason: "missing-user" });
    }

    if (!qrCode) {
      if (showAlertOnMissing) {
        alert("Please choose a QR code.");
      }
      const detail = { code: qrCode, userId, source, reason: "missing-code" };
      document.dispatchEvent(
        new CustomEvent("kerbcycle-qr-code-assignment-failed", { detail }),
      );
      return Promise.resolve({ success: false, reason: "missing-code" });
    }

    const body = `action=assign_qr_code&qr_code=${encodeURIComponent(
      qrCode,
    )}&customer_id=${encodeURIComponent(
      userId,
    )}&send_email=${sendEmail ? 1 : 0}&send_sms=${sendSms ? 1 : 0}&send_reminder=${
      sendReminder ? 1 : 0
    }&security=${kerbcycle_ajax.nonce}`;

    return fetch(kerbcycle_ajax.ajax_url, {
      method: "POST",
      headers: {
        "Content-Type":
          "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          let msg = "QR code assigned successfully.";
          if (data.data && typeof data.data.sms_sent !== "undefined") {
            if (data.data.sms_sent) {
              msg += " SMS notification sent.";
            } else {
              msg +=
                " SMS failed: " +
                (data.data.sms_error || "Unknown error") +
                ".";
            }
          }
          showToast(msg);
          try {
            localStorage.setItem(
              "kerbcycleAssignment",
              Date.now().toString(),
            );
          } catch (e) {
            console.warn("LocalStorage unavailable", e);
          }
          const list = document.getElementById("qr-code-list");
          const escapedCode = cssEscape(qrCode);
          let li = list
            ? list.querySelector(
                `.qr-item[data-code="${escapedCode}"]`,
              )
            : null;
          const record = data.data && data.data.record ? data.data.record : null;
          if (!li && record && list) {
            li = document.createElement("li");
            li.className = "qr-item";
            li.dataset.code = record.qr_code || qrCode;
            if (record.id !== undefined && record.id !== null) {
              li.dataset.id = record.id;
            }

            const checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.className = "qr-select";
            li.appendChild(checkbox);

            const idSpan = document.createElement("span");
            idSpan.className = "qr-id";
            idSpan.textContent =
              record.id !== undefined && record.id !== null ? record.id : "—";
            li.appendChild(idSpan);

            const codeSpan = document.createElement("span");
            codeSpan.className = "qr-text";
            codeSpan.contentEditable = "true";
            codeSpan.textContent = record.qr_code || qrCode;
            li.appendChild(codeSpan);

            const userSpan = document.createElement("span");
            userSpan.className = "qr-user";
            userSpan.textContent =
              record.user_id !== undefined && record.user_id !== null
                ? record.user_id
                : "—";
            li.appendChild(userSpan);

            const nameSpan = document.createElement("span");
            nameSpan.className = "qr-name";
            nameSpan.textContent =
              record.display_name !== undefined && record.display_name !== null
                ? record.display_name
                : "—";
            li.appendChild(nameSpan);

            const statusSpan = document.createElement("span");
            statusSpan.className = "qr-status";
            statusSpan.textContent = record.status || "Assigned";
            li.appendChild(statusSpan);

            const assignedSpan = document.createElement("span");
            assignedSpan.className = "qr-assigned";
            assignedSpan.textContent =
              record.assigned_at !== undefined && record.assigned_at !== null
                ? record.assigned_at
                : "—";
            li.appendChild(assignedSpan);

            const header = list.querySelector(".qr-header");
            if (header) {
              list.insertBefore(li, header.nextSibling);
            } else {
              list.insertBefore(li, list.firstChild);
            }
            wireQrItem(li);
            updateSelectAllState();
          }
          if (li) {
            wireQrItem(li);
            li.dataset.code = qrCode;
            li.querySelector(".qr-user").textContent = userId;
            const displayName =
              customerName ||
              (userField &&
                userField.options[userField.selectedIndex] &&
                userField.options[userField.selectedIndex].text) ||
              "—";
            li.querySelector(".qr-name").textContent = displayName;
            li.querySelector(".qr-status").textContent = "Assigned";
            li.querySelector(".qr-assigned").textContent = new Date()
              .toISOString()
              .slice(0, 19)
              .replace("T", " ");
          }
          if (qrSelect) {
            const opt = qrSelect.querySelector(
              `option[value="${cssEscape(qrCode)}"]`,
            );
            if (opt) {
              opt.remove();
            }
            qrSelect.value = "";
            if (qrSelect._searchable) {
              qrSelect._searchable.updateOptions();
              qrSelect._searchable.input.value = "";
            }
          }
          if (assignedSelect && userField && userField.value === userId) {
            const exists = assignedSelect.querySelector(
              `option[value="${cssEscape(qrCode)}"]`,
            );
            if (!exists) {
              const opt2 = document.createElement("option");
              opt2.value = qrCode;
              opt2.textContent = qrCode;
              assignedSelect.appendChild(opt2);
            }
            if (assignedSelect._searchable) {
              assignedSelect._searchable.updateOptions();
            }
          }
          adjustCounts(-1, 1);
          document.dispatchEvent(
            new CustomEvent("kerbcycle-qr-code-assigned", {
              detail: { code: qrCode, userId, data, source, customerName },
            }),
          );
          return { success: true, data };
        }
        const err =
          (data.data && data.data.message)
            ? data.data.message
            : "Failed to assign QR code.";
        showToast(err, true);
        const result = {
          success: false,
          data,
          reason: "request-failed",
          message: err,
        };
        document.dispatchEvent(
          new CustomEvent("kerbcycle-qr-code-assignment-failed", {
            detail: { code: qrCode, userId, data, source, message: err },
          }),
        );
        return result;
      })
      .catch((error) => {
        console.error("Error:", error);
        const message = "An error occurred while assigning the QR code.";
        showToast(message, true);
        const result = {
          success: false,
          error,
          reason: "network-error",
          message,
        };
        document.dispatchEvent(
          new CustomEvent("kerbcycle-qr-code-assignment-error", {
            detail: { code: qrCode, userId, error, source },
          }),
        );
        return result;
      });
  }

  if (assignBtn) {
    assignBtn.addEventListener("click", function () {
      const userId = userField ? userField.value : "";
      const qrCode = qrSelect ? qrSelect.value : "";
      const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
      const sendSms = sendSmsCheckbox ? sendSmsCheckbox.checked : false;
      const sendReminder = sendReminderCheckbox
        ? sendReminderCheckbox.checked
        : false;
      const customerName =
        userField && userField.selectedIndex >= 0
          ? userField.options[userField.selectedIndex].text
          : "";

      assignQrCodeToCustomer(qrCode, userId, {
        sendEmail,
        sendSms,
        sendReminder,
        showAlertOnMissing: true,
        source: "manual",
        customerName,
      });
    });
  }
  if (releaseBtn) {
    releaseBtn.addEventListener("click", function () {
      const qrCode = assignedSelect ? assignedSelect.value : "";
      const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
      const sendSms = sendSmsCheckbox ? sendSmsCheckbox.checked : false;
      if (!qrCode) {
        alert("Please select a QR code to release.");
        return;
      }

      fetch(kerbcycle_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: `action=release_qr_code&qr_code=${encodeURIComponent(qrCode)}&send_email=${sendEmail ? 1 : 0}&send_sms=${sendSms ? 1 : 0}&security=${kerbcycle_ajax.nonce}`,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            let msg = "QR code released successfully.";
            if (data.data && typeof data.data.sms_sent !== "undefined") {
              if (data.data.sms_sent) {
                msg += " SMS notification sent.";
              } else {
                msg +=
                  " SMS failed: " +
                  (data.data.sms_error || "Unknown error") +
                  ".";
              }
            }
            showToast(msg);
            const li = document.querySelector(
              `#qr-code-list .qr-item[data-code="${qrCode}"]`,
            );
            if (li) {
              li.querySelector(".qr-user").textContent = "—";
              li.querySelector(".qr-name").textContent = "—";
              li.querySelector(".qr-status").textContent = "Available";
              li.querySelector(".qr-assigned").textContent = "—";
            }
            if (
              qrSelect &&
              !qrSelect.querySelector(`option[value="${qrCode}"]`)
            ) {
              const opt = document.createElement("option");
              opt.value = qrCode;
              opt.textContent = qrCode;
              qrSelect.appendChild(opt);
              if (qrSelect._searchable) {
                qrSelect._searchable.updateOptions();
              }
            }
            if (assignedSelect) {
              const opt = assignedSelect.querySelector(
                `option[value="${qrCode}"]`,
              );
              if (opt) opt.remove();
              assignedSelect.value = "";
              if (assignedSelect._searchable) {
                assignedSelect._searchable.updateOptions();
                assignedSelect._searchable.input.value = "";
              }
            }
            adjustCounts(1, -1);
          } else {
            showToast("Failed to release QR code.", true);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showToast("An error occurred while releasing the QR code.", true);
        });
    });
  }

  function addQrCodeToRepository(rawQrCode, options = {}) {
    const {
      showAlertOnEmpty = false,
      clearInput = false,
      source = "manual",
    } = options;

    const qrCode = rawQrCode ? rawQrCode.trim() : "";

    if (!qrCode) {
      if (showAlertOnEmpty) {
        alert("Please enter a QR code.");
      }
      document.dispatchEvent(
        new CustomEvent("kerbcycle-qr-code-add-failed", {
          detail: { code: qrCode, source, reason: "empty" },
        }),
      );
      return Promise.resolve({ success: false, reason: "empty" });
    }

    const payload = `action=add_qr_code&qr_code=${encodeURIComponent(qrCode)}&security=${kerbcycle_ajax.nonce}`;

    return fetch(kerbcycle_ajax.ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: payload,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          const msg =
            data.data && data.data.message
              ? data.data.message
              : "QR code added successfully.";
          showToast(msg);
          if (qrSelect && !qrSelect.querySelector(`option[value="${qrCode}"]`)) {
            const opt = document.createElement("option");
            opt.value = qrCode;
            opt.textContent = qrCode;
            qrSelect.appendChild(opt);
            if (qrSelect._searchable) {
              qrSelect._searchable.updateOptions();
            }
          }
          if (clearInput && newCodeInput) {
            newCodeInput.value = "";
          }
          adjustCounts(1, 0);
          if (data.data && data.data.row) {
            const row = data.data.row;
            const list = document.getElementById("qr-code-list");
            if (list) {
              const li = document.createElement("li");
              li.className = "qr-item";
              li.dataset.code = row.qr_code;
              li.dataset.id = row.id;
              li.innerHTML = `
<input type="checkbox" class="qr-select" />
<span class="qr-id">${row.id}</span>
<span class="qr-text" contenteditable="true">${row.qr_code}</span>
<span class="qr-user">—</span>
<span class="qr-name">—</span>
<span class="qr-status">Available</span>
<span class="qr-assigned">—</span>`;
              const header = list.querySelector(".qr-header");
              if (header && header.nextSibling) {
                list.insertBefore(li, header.nextSibling);
              } else {
                list.appendChild(li);
              }
              wireQrItem(li);
              updateSelectAllState();
            }
          }
          document.dispatchEvent(
            new CustomEvent("kerbcycle-qr-code-added", {
              detail: { code: qrCode, data, source },
            }),
          );
          return { success: true, data };
        }

        const err =
          data.data && data.data.message
            ? data.data.message
            : "Failed to add QR code.";
        showToast(err, true);
        document.dispatchEvent(
          new CustomEvent("kerbcycle-qr-code-add-failed", {
            detail: { code: qrCode, data, source },
          }),
        );
        return { success: false, data };
      })
      .catch((error) => {
        console.error("Error:", error);
        showToast("An error occurred while adding the QR code.", true);
        document.dispatchEvent(
          new CustomEvent("kerbcycle-qr-code-add-error", {
            detail: { code: qrCode, error, source },
          }),
        );
        return { success: false, error };
      });
  }

  window.kerbcycleAssignQrCodeToCustomer = assignQrCodeToCustomer;
  window.kerbcycleAddQrCodeToRepository = addQrCodeToRepository;

  if (addBtn) {
    addBtn.addEventListener("click", function () {
      addQrCodeToRepository(newCodeInput ? newCodeInput.value : "", {
        showAlertOnEmpty: true,
        clearInput: true,
        source: "manual",
      });
    });
  }

  if (importBtn) {
    importBtn.addEventListener("click", function () {
      if (!importFile || !importFile.files.length) {
        alert("Please select a CSV file.");
        return;
      }
      const formData = new FormData();
      formData.append("action", "import_qr_codes");
      formData.append("security", kerbcycle_ajax.nonce);
      formData.append("import_file", importFile.files[0]);
      fetch(kerbcycle_ajax.ajax_url, {
        method: "POST",
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            const msg =
              data.data && data.data.message
                ? data.data.message
                : "QR codes imported.";
            showToast(msg);
            location.reload();
          } else {
            const err =
              data.data && data.data.message
                ? data.data.message
                : "Failed to import QR codes.";
            showToast(err, true);
          }
        })
        .catch((error) => {
          console.error("Error:", error);
          showToast("An error occurred while importing QR codes.", true);
        });
    });
  }

  const bulkForm = document.getElementById("qr-code-bulk-form");
  if (bulkForm) {
    if (!kerbcycle_ajax.drag_drop_disabled) {
      jQuery("#qr-code-list").sortable({ items: "li.qr-item" });
    }

    const selectAll = document.getElementById("qr-select-all");
    if (selectAll) {
      selectAll.addEventListener("change", function () {
        const checked = selectAll.checked;
        document
          .querySelectorAll("#qr-code-list .qr-item .qr-select")
          .forEach((cb) => {
            cb.checked = checked;
          });
        updateSelectAllState();
      });
    }

    document
      .querySelectorAll("#qr-code-list .qr-item")
      .forEach((item) => wireQrItem(item));
    updateSelectAllState();

    document
      .querySelectorAll("#apply-bulk, #apply-bulk-top")
      .forEach((button) => {
        button.addEventListener("click", function (e) {
          e.preventDefault();
          const targetSelect = document.getElementById(button.dataset.target);
          const action = targetSelect ? targetSelect.value : "";
          if (action === "release") {
            const codes = Array.from(
              document.querySelectorAll(
                "#qr-code-list .qr-item .qr-select:checked",
              ),
            ).map((cb) => cb.closest("li").dataset.code);
            if (!codes.length) {
              alert("Please select one or more QR codes to release.");
              return;
            }

            if (
              !confirm(
                "Are you sure you want to release the selected QR codes?",
              )
            ) {
              return;
            }

            fetch(kerbcycle_ajax.ajax_url, {
              method: "POST",
              headers: {
                "Content-Type":
                  "application/x-www-form-urlencoded; charset=UTF-8",
              },
              body: `action=bulk_release_qr_codes&qr_codes=${encodeURIComponent(codes.join(","))}&security=${kerbcycle_ajax.nonce}`,
            })
              .then((res) => res.json())
              .then((data) => {
                if (data.success) {
                  alert(data.data.message);
                  location.reload();
                } else {
                  alert(
                    "Error: " +
                      (data.data.message || "Failed to release QR codes."),
                  );
                }
              })
              .catch((error) => {
                console.error("Error:", error);
                alert("An unexpected error occurred. Please try again.");
              });
          } else if (action === "delete") {
            const selected = Array.from(
              document.querySelectorAll(
                "#qr-code-list .qr-item .qr-select:checked",
              ),
            );
            if (!selected.length) {
              alert("Please select one or more QR codes to delete.");
              return;
            }

            const availableItems = selected.filter(
              (cb) =>
                cb
                  .closest("li")
                  .querySelector(".qr-status")
                  .textContent.trim()
                  .toLowerCase() === "available",
            );
            if (availableItems.length !== selected.length) {
              alert("Only QR codes with Available status can be deleted.");
              return;
            }

            const codes = availableItems.map(
              (cb) => cb.closest("li").dataset.code,
            );

            if (
              !confirm("Are you sure you want to delete the selected QR codes?")
            ) {
              return;
            }

            fetch(kerbcycle_ajax.ajax_url, {
              method: "POST",
              headers: {
                "Content-Type":
                  "application/x-www-form-urlencoded; charset=UTF-8",
              },
              body: `action=bulk_delete_qr_codes&qr_codes=${encodeURIComponent(codes.join(","))}&security=${kerbcycle_ajax.nonce}`,
            })
              .then((res) => res.json())
              .then((data) => {
                if (data.success) {
                  alert(data.data.message);
                  location.reload();
                } else {
                  alert(
                    "Error: " +
                      (data.data.message || "Failed to delete QR codes."),
                  );
                }
              })
              .catch((error) => {
                console.error("Error:", error);
                alert("An unexpected error occurred. Please try again.");
              });
          }
        });
      });
  } else {
    document
      .querySelectorAll("#qr-code-list .qr-item")
      .forEach((item) => wireQrItem(item));
    updateSelectAllState();
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initKerbcycleAdmin);
} else {
  initKerbcycleAdmin();
}
