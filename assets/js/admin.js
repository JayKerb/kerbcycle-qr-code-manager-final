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
  const listingWrapper = document.getElementById("qr-listing");
  const paginationWrappers = listingWrapper
    ? Array.from(listingWrapper.querySelectorAll(".qr-pagination"))
    : [];
  const listContainer = document.getElementById("qr-code-list");
  const sortButtons = listContainer
    ? Array.from(listContainer.querySelectorAll(".qr-header .qr-sort-control"))
    : [];
  let isPaginating = false;
  let currentSortKey = null;
  let currentSortDirection = "asc";

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

  function getListingState() {
    if (!listingWrapper) {
      return null;
    }
    const parseNumber = (value, fallback) => {
      const parsed = parseInt(value, 10);
      return Number.isNaN(parsed) ? fallback : parsed;
    };
    const currentPage = parseNumber(listingWrapper.dataset.currentPage, 1);
    const totalPages = parseNumber(listingWrapper.dataset.totalPages, 0);
    const totalItems = parseNumber(listingWrapper.dataset.totalItems, 0);
    let perPage = parseNumber(listingWrapper.dataset.perPage, 20);
    if (perPage < 1) {
      perPage = 20;
    }
    return {
      currentPage,
      totalPages,
      totalItems,
      perPage,
      filters: {
        status_filter: listingWrapper.dataset.statusFilter || "",
        start_date: listingWrapper.dataset.startDate || "",
        end_date: listingWrapper.dataset.endDate || "",
        search: listingWrapper.dataset.search || "",
      },
    };
  }

  function updatePaginationUI(pagination) {
    if (!listingWrapper || !pagination) {
      return;
    }

    const filters = pagination.filters || {};

    if (
      typeof pagination.current_page !== "undefined" &&
      pagination.current_page !== null
    ) {
      listingWrapper.dataset.currentPage = String(pagination.current_page);
    }

    if (
      typeof pagination.total_pages !== "undefined" &&
      pagination.total_pages !== null
    ) {
      listingWrapper.dataset.totalPages = String(pagination.total_pages);
    }

    if (
      typeof pagination.total_items !== "undefined" &&
      pagination.total_items !== null
    ) {
      listingWrapper.dataset.totalItems = String(pagination.total_items);
    }

    if (
      typeof pagination.per_page !== "undefined" &&
      pagination.per_page !== null
    ) {
      listingWrapper.dataset.perPage = String(pagination.per_page);
    }

    if (
      typeof filters.status_filter !== "undefined" &&
      filters.status_filter !== null
    ) {
      listingWrapper.dataset.statusFilter = filters.status_filter;
    }

    if (
      typeof filters.start_date !== "undefined" &&
      filters.start_date !== null
    ) {
      listingWrapper.dataset.startDate = filters.start_date;
    }

    if (typeof filters.end_date !== "undefined" && filters.end_date !== null) {
      listingWrapper.dataset.endDate = filters.end_date;
    }

    if (typeof filters.search !== "undefined" && filters.search !== null) {
      listingWrapper.dataset.search = filters.search;
    }

    paginationWrappers.forEach((wrapper) => {
      const controls = wrapper.querySelector(".qr-pagination-controls");
      if (controls) {
        controls.innerHTML = pagination.links || "";
      }

      const fallback = wrapper.querySelector(".qr-pagination-fallback");
      if (fallback) {
        const pages = fallback.querySelector(".tablenav-pages");
        if (pages) {
          pages.innerHTML = pagination.links || "";
        }
        if (
          listingWrapper.classList.contains("qr-pagination-enhanced") ||
          pagination.links
        ) {
          fallback.style.display = "none";
        } else {
          fallback.style.display = "";
        }
      }
    });
  }

  function updateCountsFromServer(counts) {
    if (!counts) {
      return;
    }
    if (typeof counts.available !== "undefined") {
      document.querySelectorAll(".qr-available-count").forEach((el) => {
        el.textContent = counts.available;
      });
    }
    if (typeof counts.assigned !== "undefined") {
      document.querySelectorAll(".qr-assigned-count").forEach((el) => {
        el.textContent = counts.assigned;
      });
    }
  }

  function replaceQrItems(html) {
    if (!listContainer) {
      return;
    }
    listContainer.querySelectorAll(".qr-item").forEach((item) => item.remove());
    if (html) {
      listContainer.insertAdjacentHTML("beforeend", html);
    }
    listContainer
      .querySelectorAll(".qr-item")
      .forEach((item) => wireQrItem(item));
  }

  function getPageFromLink(link) {
    if (!link) {
      return null;
    }
    const pageAttr = link.getAttribute("data-page");
    if (pageAttr) {
      const parsedPage = parseInt(pageAttr, 10);
      if (!Number.isNaN(parsedPage)) {
        return parsedPage;
      }
    }
    const href = link.getAttribute("href");
    if (href) {
      try {
        const url = new URL(href, window.location.href);
        const pagedParam = url.searchParams.get("paged");
        if (pagedParam) {
          const parsed = parseInt(pagedParam, 10);
          if (!Number.isNaN(parsed)) {
            return parsed;
          }
        }
      } catch (error) {
        // ignore malformed URLs
      }
    }
    const state = getListingState();
    if (!state) {
      return null;
    }
    if (link.classList.contains("prev")) {
      return Math.max(1, state.currentPage - 1);
    }
    if (link.classList.contains("next")) {
      const total = state.totalPages || state.currentPage;
      return Math.min(total, state.currentPage + 1);
    }
    return null;
  }

  function requestPage(targetPage) {
    if (!listingWrapper || !listContainer) {
      return Promise.resolve();
    }
    const state = getListingState();
    if (!state) {
      return Promise.resolve();
    }
    isPaginating = true;
    listingWrapper.classList.add("is-loading");

    const params = new URLSearchParams();
    params.append("action", "kerbcycle_paginate_qr_codes");
    params.append("security", kerbcycle_ajax.nonce);
    params.append("paged", targetPage);
    if (state.perPage > 0) {
      params.append("per_page", state.perPage);
    }
    if (state.filters.status_filter) {
      params.append("status_filter", state.filters.status_filter);
    }
    if (state.filters.start_date) {
      params.append("start_date", state.filters.start_date);
    }
    if (state.filters.end_date) {
      params.append("end_date", state.filters.end_date);
    }
    if (state.filters.search) {
      params.append("search", state.filters.search);
    }

    return fetch(kerbcycle_ajax.ajax_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: params.toString(),
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`Request failed with status ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        if (!data || !data.success) {
          const message =
            data && data.data && data.data.message
              ? data.data.message
              : "Unable to load QR codes.";
          showToast(message, true);
          return;
        }
        const payload = data.data || {};
        replaceQrItems(payload.items_html || "");
        updatePaginationUI(payload.pagination || {});
        updateCountsFromServer(payload.counts || null);
        applyCurrentSort();
        updateSelectAllState();
      })
      .catch((error) => {
        console.error("Error loading paginated QR codes:", error);
        showToast("Failed to load QR codes. Please try again.", true);
      })
      .finally(() => {
        isPaginating = false;
        listingWrapper.classList.remove("is-loading");
      });
  }

  function handlePaginationClick(event) {
    const link = event.target.closest("a.page-numbers");
    if (!link) {
      return;
    }
    const state = getListingState();
    if (!state) {
      return;
    }
    const targetPage = getPageFromLink(link);
    if (targetPage === null || targetPage === state.currentPage) {
      return;
    }
    event.preventDefault();
    if (isPaginating) {
      return;
    }
    requestPage(targetPage);
  }

  function enhancePagination() {
    if (!listingWrapper) {
      return;
    }
    const state = getListingState();
    let initialLinks = "";
    paginationWrappers.forEach((wrapper) => {
      const fallbackPages = wrapper.querySelector(
        ".qr-pagination-fallback .tablenav-pages",
      );
      if (fallbackPages && !initialLinks) {
        initialLinks = fallbackPages.innerHTML;
      }
    });
    updatePaginationUI({
      links: initialLinks,
      current_page: state ? state.currentPage : undefined,
      total_pages: state ? state.totalPages : undefined,
      total_items: state ? state.totalItems : undefined,
      per_page: state ? state.perPage : undefined,
      filters: state ? state.filters : undefined,
    });
    listingWrapper.classList.add("qr-pagination-enhanced");
  }

  const normalizeDateValue = (value) => {
    if (!value) {
      return Number.NaN;
    }
    const normalized = value.includes("T") ? value : value.replace(" ", "T");
    const parsed = Date.parse(normalized);
    if (!Number.isNaN(parsed)) {
      return parsed;
    }
    const fallback = Date.parse(value);
    return Number.isNaN(fallback) ? Number.NaN : fallback;
  };

  function updateSortIndicators() {
    sortButtons.forEach((btn) => {
      const isActive = btn.dataset.sortKey === currentSortKey;
      const isAscending = isActive && currentSortDirection === "asc";
      const isDescending = isActive && currentSortDirection === "desc";
      btn.classList.toggle("is-active", isActive);
      btn.classList.toggle("is-ascending", isAscending);
      btn.classList.toggle("is-descending", isDescending);
      btn.setAttribute("aria-pressed", isActive ? "true" : "false");
      if (isActive) {
        btn.dataset.direction = currentSortDirection;
      } else {
        delete btn.dataset.direction;
      }
    });
  }

  function sortQrItems(sortKey, direction, type) {
    if (!listContainer) {
      return;
    }
    const items = Array.from(listContainer.querySelectorAll(".qr-item"));
    if (!items.length) {
      return;
    }
    const multiplier = direction === "desc" ? -1 : 1;
    const collator = new Intl.Collator(undefined, {
      sensitivity: "base",
      numeric: type === "number",
    });

    items.sort((a, b) => {
      const aVal = (a.dataset[sortKey] || "").trim();
      const bVal = (b.dataset[sortKey] || "").trim();
      let result = 0;

      if (type === "number") {
        const aNum = parseFloat(aVal);
        const bNum = parseFloat(bVal);
        const aValid = !Number.isNaN(aNum);
        const bValid = !Number.isNaN(bNum);
        if (aValid && bValid) {
          result = aNum - bNum;
        } else if (aValid) {
          result = -1;
        } else if (bValid) {
          result = 1;
        } else {
          result = collator.compare(aVal, bVal);
        }
      } else if (type === "date") {
        const aTime = normalizeDateValue(aVal);
        const bTime = normalizeDateValue(bVal);
        const aValid = !Number.isNaN(aTime);
        const bValid = !Number.isNaN(bTime);
        if (aValid && bValid) {
          result = aTime - bTime;
        } else if (aValid) {
          result = -1;
        } else if (bValid) {
          result = 1;
        } else {
          result = collator.compare(aVal, bVal);
        }
      } else {
        result = collator.compare(aVal, bVal);
      }

      if (result === 0) {
        const aId = parseFloat(a.dataset.id || "0");
        const bId = parseFloat(b.dataset.id || "0");
        if (!Number.isNaN(aId) && !Number.isNaN(bId)) {
          result = aId - bId;
        }
      }

      if (result === 0) {
        result = collator.compare(a.dataset.code || "", b.dataset.code || "");
      }

      return result * multiplier;
    });

    const fragment = document.createDocumentFragment();
    items.forEach((item) => fragment.appendChild(item));
    listContainer.appendChild(fragment);
    updateSelectAllState();
  }

  function applyCurrentSort() {
    if (!currentSortKey) {
      return;
    }
    const activeButton = sortButtons.find(
      (btn) => btn.dataset.sortKey === currentSortKey,
    );
    const sortType = activeButton
      ? activeButton.dataset.sortType || "text"
      : "text";
    sortQrItems(currentSortKey, currentSortDirection, sortType);
    updateSortIndicators();
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
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
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
          applyCurrentSort();
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

  if (listingWrapper) {
    enhancePagination();
    paginationWrappers.forEach((wrapper) => {
      wrapper.addEventListener("click", handlePaginationClick);
    });
  }

  sortButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const sortKey = button.dataset.sortKey;
      if (!sortKey) {
        return;
      }
      const sortType = button.dataset.sortType || "text";
      let direction = "asc";
      if (currentSortKey === sortKey) {
        direction = currentSortDirection === "asc" ? "desc" : "asc";
      }
      currentSortKey = sortKey;
      currentSortDirection = direction;
      sortQrItems(sortKey, direction, sortType);
      updateSortIndicators();
    });
  });
  updateSortIndicators();

  const aiFromDate = document.getElementById("kerbcycle-ai-from-date");
  const aiQrExceptionsBtn = document.getElementById(
    "kerbcycle-ai-qr-exceptions-btn",
  );
  const aiDraftTemplateBtn = document.getElementById(
    "kerbcycle-ai-draft-template-btn",
  );
  const aiStatus = document.getElementById("kerbcycle-ai-status");
  const aiResult = document.getElementById("kerbcycle-ai-result");
  const pickupExceptionTestBtn = document.getElementById(
    "kerbcycle-submit-pickup-exception",
  );
  const pickupExceptionTestResult = document.getElementById(
    "kerbcycle-ai-test-result",
  );
  const pickupExceptionQrCode = document.getElementById(
    "kerbcycle-pickup-exception-qr-code",
  );
  const pickupExceptionCustomerId = document.getElementById(
    "kerbcycle-pickup-exception-customer-id",
  );
  const pickupExceptionIssue = document.getElementById(
    "kerbcycle-pickup-exception-issue",
  );
  const pickupExceptionNotes = document.getElementById(
    "kerbcycle-pickup-exception-notes",
  );

  function renderAiList(items) {
    if (!Array.isArray(items) || !items.length) {
      return "";
    }
    const lis = items
      .slice(0, 5)
      .map((item) => `<li>${escapeHtml(String(item))}</li>`)
      .join("");
    return `<ul>${lis}</ul>`;
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll("\"", "&quot;")
      .replaceAll("'", "&#039;");
  }

  function setAiResult(html, statusClass = "notice-info") {
    if (!aiResult) {
      return;
    }
    aiResult.className = `notice inline ${statusClass}`;
    aiResult.innerHTML = html;
    aiResult.style.display = "block";
  }

  function callAiAction(action) {
    if (!kerbcycle_ajax.rest_url || !kerbcycle_ajax.rest_nonce) {
      setAiResult("<p>AI endpoint is not configured in admin assets.</p>", "notice-error");
      return;
    }

    const fromDate = aiFromDate ? aiFromDate.value : "";
    const body = { action };
    if (fromDate) {
      body.from_date = fromDate;
      body.to_date = new Date().toISOString().slice(0, 10);
    }

    if (aiStatus) {
      aiStatus.textContent = "Loading AI response...";
    }

    setAiResult("<p>Loading...</p>", "notice-info");

    return fetch(kerbcycle_ajax.rest_url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": kerbcycle_ajax.rest_nonce,
      },
      body: JSON.stringify(body),
    })
      .then((res) => res.json().then((data) => ({ ok: res.ok, data })))
      .then(({ ok, data }) => {
        if (!ok || !data || data.success !== true) {
          const message =
            (data && data.message) ||
            (data && data.code) ||
            "Unable to load AI response.";
          throw new Error(message);
        }

        if (action === "qr_exceptions") {
          const aiData = data.data || {};
          const priorities = Array.isArray(aiData.priority_exceptions)
            ? aiData.priority_exceptions
                .slice(0, 5)
                .map(
                  (item) =>
                    `${item.type || "Issue"}: ${item.count || 0} (${item.reason || "No reason provided"})`,
                )
            : [];
          const totalExceptions = Array.isArray(data.source?.groups)
            ? data.source.groups.reduce((sum, group) => sum + (group.count || 0), 0)
            : 0;

          setAiResult(
            `<p><strong>Summary:</strong> ${escapeHtml(aiData.overview || "No summary returned.")}</p>` +
              `<p><strong>Total exceptions scanned:</strong> ${escapeHtml(totalExceptions)}</p>` +
              `<p><strong>Top issues:</strong></p>${renderAiList(priorities)}`,
            "notice-success",
          );
        } else {
          const aiData = data.data || {};
          setAiResult(
            `<p><strong>Title:</strong> ${escapeHtml(aiData.title || "Untitled")}</p>` +
              `<p><strong>Audience:</strong> ${escapeHtml(aiData.audience || "General")}</p>` +
              `<p><strong>Draft:</strong> ${escapeHtml(aiData.message || "No draft returned.")}</p>`,
            "notice-success",
          );
        }

        if (aiStatus) {
          aiStatus.textContent = "AI response loaded.";
        }
      })
      .catch((error) => {
        console.error("AI request failed:", error);
        setAiResult(`<p>${escapeHtml(error.message || "AI request failed.")}</p>`, "notice-error");
        if (aiStatus) {
          aiStatus.textContent = "AI request failed.";
        }
      });
  }

  if (aiQrExceptionsBtn) {
    aiQrExceptionsBtn.addEventListener("click", function () {
      callAiAction("qr_exceptions");
    });
  }

  if (aiDraftTemplateBtn) {
    aiDraftTemplateBtn.addEventListener("click", function () {
      callAiAction("draft_template");
    });
  }

  if (pickupExceptionTestBtn) {
    pickupExceptionTestBtn.addEventListener("click", function () {
      const originalText = pickupExceptionTestBtn.textContent;
      pickupExceptionTestBtn.disabled = true;
      pickupExceptionTestBtn.textContent = "Submitting...";
      if (pickupExceptionTestResult) {
        pickupExceptionTestResult.textContent = "Saving and sending pickup exception...";
      }

      const params = new URLSearchParams();
      params.append("action", "kerbcycle_test_pickup_exception");
      params.append("security", kerbcycle_ajax.nonce);
      params.append("qr_code", pickupExceptionQrCode ? pickupExceptionQrCode.value : "");
      params.append(
        "customer_id",
        pickupExceptionCustomerId ? pickupExceptionCustomerId.value : "",
      );
      params.append("issue", pickupExceptionIssue ? pickupExceptionIssue.value : "");
      params.append("notes", pickupExceptionNotes ? pickupExceptionNotes.value : "");

      fetch(kerbcycle_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: params.toString(),
      })
        .then((res) => res.json())
        .then((data) => {
          if (pickupExceptionTestResult) {
            const payload = data && data.data ? data.data : {};
            const output = {
              status: payload.status || (data.success ? "success" : "error"),
              message: payload.message || "",
              local_save: payload.local_save || { success: false },
              webhook: payload.webhook || {},
              ai_summary: payload.ai_summary || "",
              ai_category: payload.ai_category || "",
              ai_severity: payload.ai_severity || "",
            };
            pickupExceptionTestResult.textContent = JSON.stringify(output, null, 2);
          }
        })
        .catch((error) => {
          if (pickupExceptionTestResult) {
            pickupExceptionTestResult.textContent = JSON.stringify(
              {
                success: false,
                message: error.message || "Request failed.",
              },
              null,
              2,
            );
          }
        })
        .finally(() => {
          pickupExceptionTestBtn.disabled = false;
          pickupExceptionTestBtn.textContent = originalText;
        });
    });
  }

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
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          let msg = "QR code assigned successfully.";
          if (data.data && typeof data.data.email_sent !== "undefined") {
            if (data.data.email_sent) {
              msg += " Email notification sent.";
            } else {
              msg +=
                " Email failed: " +
                (data.data.email_error || "Unknown error") +
                ".";
            }
          }
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
            localStorage.setItem("kerbcycleAssignment", Date.now().toString());
          } catch (e) {
            console.warn("LocalStorage unavailable", e);
          }
          const list = document.getElementById("qr-code-list");
          const escapedCode = cssEscape(qrCode);
          let li = list
            ? list.querySelector(`.qr-item[data-code="${escapedCode}"]`)
            : null;
          const record =
            data.data && data.data.record ? data.data.record : null;
          if (!li && record && list) {
            li = document.createElement("li");
            li.className = "qr-item";
            li.dataset.code = record.qr_code || qrCode;
            if (record.id !== undefined && record.id !== null) {
              li.dataset.id = record.id;
            }
            const userValue =
              record.user_id !== undefined && record.user_id !== null
                ? String(record.user_id)
                : "";
            const nameValue =
              record.display_name !== undefined && record.display_name !== null
                ? String(record.display_name)
                : "";
            const statusValueRaw =
              record.status !== undefined && record.status !== null
                ? String(record.status)
                : "assigned";
            const statusValue = statusValueRaw.toLowerCase();
            const statusText =
              statusValue && statusValue.length
                ? statusValue.charAt(0).toUpperCase() + statusValue.slice(1)
                : "Assigned";
            const assignedValue =
              record.assigned_at !== undefined && record.assigned_at !== null
                ? String(record.assigned_at)
                : "";
            li.dataset.userId = userValue;
            li.dataset.displayName = nameValue;
            li.dataset.status = statusValue;
            li.dataset.assignedAt = assignedValue;

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
            userSpan.textContent = userValue || "—";
            li.appendChild(userSpan);

            const nameSpan = document.createElement("span");
            nameSpan.className = "qr-name";
            nameSpan.textContent = nameValue || "—";
            li.appendChild(nameSpan);

            const statusSpan = document.createElement("span");
            statusSpan.className = "qr-status";
            statusSpan.textContent = statusText;
            li.appendChild(statusSpan);

            const assignedSpan = document.createElement("span");
            assignedSpan.className = "qr-assigned";
            assignedSpan.textContent = assignedValue || "—";
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
            const displayName =
              customerName ||
              (userField &&
                userField.options[userField.selectedIndex] &&
                userField.options[userField.selectedIndex].text) ||
              "—";
            const displayNameValue =
              displayName && displayName !== "—" ? displayName.trim() : "";
            const statusValue =
              record && record.status
                ? String(record.status).toLowerCase()
                : "assigned";
            const statusText =
              statusValue && statusValue.length
                ? statusValue.charAt(0).toUpperCase() + statusValue.slice(1)
                : "Assigned";
            const assignedAt =
              record && record.assigned_at
                ? String(record.assigned_at)
                : new Date().toISOString().slice(0, 19).replace("T", " ");
            li.dataset.userId = userId || "";
            li.dataset.displayName = displayNameValue;
            li.dataset.status = statusValue;
            li.dataset.assignedAt = assignedAt;
            li.querySelector(".qr-user").textContent = userId || "—";
            li.querySelector(".qr-name").textContent = displayNameValue || "—";
            li.querySelector(".qr-status").textContent = statusText;
            li.querySelector(".qr-assigned").textContent = assignedAt || "—";
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
          applyCurrentSort();
          document.dispatchEvent(
            new CustomEvent("kerbcycle-qr-code-assigned", {
              detail: { code: qrCode, userId, data, source, customerName },
            }),
          );
          return { success: true, data };
        }
        const err =
          data.data && data.data.message
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
            if (data.data && typeof data.data.email_sent !== "undefined") {
              if (data.data.email_sent) {
                msg += " Email notification sent.";
              } else {
                msg +=
                  " Email failed: " +
                  (data.data.email_error || "Unknown error") +
                  ".";
              }
            }
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
              li.dataset.userId = "";
              li.dataset.displayName = "";
              li.dataset.status = "available";
              li.dataset.assignedAt = "";
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
            applyCurrentSort();
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
              li.dataset.userId = "";
              li.dataset.displayName = "";
              li.dataset.status = "available";
              li.dataset.assignedAt = "";
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
              applyCurrentSort();
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
