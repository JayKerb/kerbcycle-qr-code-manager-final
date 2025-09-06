function showToast(message, isError = false) {
    let toast = document.getElementById('qr-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'qr-toast';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.className = isError ? 'error show' : 'show';
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function makeSearchableSelect(select) {
    if (!select) return;
    const listId = select.id + '-list';
    const dataList = document.createElement('datalist');
    dataList.id = listId;
    const input = document.createElement('input');
    input.setAttribute('list', listId);
    input.className = 'kc-search-input';
    const updateOptions = () => {
        dataList.innerHTML = '';
        Array.from(select.options).forEach(opt => {
            const option = document.createElement('option');
            option.value = opt.textContent;
            option.dataset.value = opt.value;
            dataList.appendChild(option);
        });
    };
    updateOptions();
    input.addEventListener('input', () => {
        const found = dataList.querySelector(`option[value="${CSS.escape(input.value)}"]`);
        select.value = found ? found.dataset.value : '';
        select.dispatchEvent(new Event('change'));
    });
    select.parentNode.insertBefore(input, select);
    select.parentNode.insertBefore(dataList, select);
    select.style.display = 'none';
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

    document.querySelectorAll('select.kc-searchable').forEach(makeSearchableSelect);

    if (userField && assignedSelect) {
        userField.addEventListener("change", function () {
            const userId = userField.value;
            assignedSelect.innerHTML = '<option value="">Select Assigned QR Code</option>';
            if (assignedSelect._searchable) {
                assignedSelect._searchable.updateOptions();
                assignedSelect._searchable.input.value = '';
            }
            if (!userId) {
                return;
            }
            fetch(kerbcycle_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: `action=get_assigned_qr_codes&customer_id=${encodeURIComponent(userId)}&security=${kerbcycle_ajax.nonce}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.data)) {
                    data.data.forEach(code => {
                        const opt = document.createElement('option');
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
        userField.dispatchEvent(new Event('change'));
    }

    if (assignBtn) {
        assignBtn.addEventListener("click", function () {
            const userId = userField ? userField.value : '';
            const qrCode = qrSelect ? qrSelect.value : '';
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            const sendSms = sendSmsCheckbox ? sendSmsCheckbox.checked : false;
            const sendReminder = sendReminderCheckbox ? sendReminderCheckbox.checked : false;

            if (!userId || !qrCode) {
                alert("Please select a user and choose a QR code.");
                return;
            }

            fetch(kerbcycle_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: `action=assign_qr_code&qr_code=${encodeURIComponent(qrCode)}&customer_id=${encodeURIComponent(userId)}&send_email=${sendEmail ? 1 : 0}&send_sms=${sendSms ? 1 : 0}&send_reminder=${sendReminder ? 1 : 0}&security=${kerbcycle_ajax.nonce}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let msg = "QR code assigned successfully.";
                    if (data.data && typeof data.data.sms_sent !== 'undefined') {
                        if (data.data.sms_sent) {
                            msg += " SMS notification sent.";
                        } else {
                            msg += " SMS failed: " + (data.data.sms_error || "Unknown error") + ".";
                        }
                    }
                    showToast(msg);
                    try {
                        localStorage.setItem('kerbcycleAssignment', Date.now().toString());
                    } catch (e) {
                        console.warn('LocalStorage unavailable', e);
                    }
                    const li = document.querySelector(`#qr-code-list .qr-item[data-code="${qrCode}"]`);
                    if (li) {
                        li.querySelector('.qr-user').textContent = userId;
                        const userName = userField.options[userField.selectedIndex].text || '—';
                        li.querySelector('.qr-name').textContent = userName;
                        li.querySelector('.qr-status').textContent = 'Assigned';
                        li.querySelector('.qr-assigned').textContent = new Date().toISOString().slice(0,19).replace('T',' ');
                    }
                    const opt = qrSelect ? qrSelect.querySelector(`option[value="${qrCode}"]`) : null;
                    if (opt) opt.remove();
                    if (qrSelect) {
                        qrSelect.value = '';
                        if (qrSelect._searchable) {
                            qrSelect._searchable.updateOptions();
                            qrSelect._searchable.input.value = '';
                        }
                    }
                    if (assignedSelect && userField && userField.value === userId) {
                        const opt2 = document.createElement('option');
                        opt2.value = qrCode;
                        opt2.textContent = qrCode;
                        assignedSelect.appendChild(opt2);
                        if (assignedSelect._searchable) {
                            assignedSelect._searchable.updateOptions();
                        }
                    }
                } else {
                    const err = data.data && data.data.message ? data.data.message : "Failed to assign QR code.";
                    showToast(err, true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast("An error occurred while assigning the QR code.", true);
            });
        });
    }

    if (releaseBtn) {
        releaseBtn.addEventListener("click", function () {
            const qrCode = assignedSelect ? assignedSelect.value : '';
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            const sendSms = sendSmsCheckbox ? sendSmsCheckbox.checked : false;
            if (!qrCode) {
                alert("Please select a QR code to release.");
                return;
            }

            fetch(kerbcycle_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: `action=release_qr_code&qr_code=${encodeURIComponent(qrCode)}&send_email=${sendEmail ? 1 : 0}&send_sms=${sendSms ? 1 : 0}&security=${kerbcycle_ajax.nonce}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let msg = "QR code released successfully.";
                    if (data.data && typeof data.data.sms_sent !== 'undefined') {
                        if (data.data.sms_sent) {
                            msg += " SMS notification sent.";
                        } else {
                            msg += " SMS failed: " + (data.data.sms_error || "Unknown error") + ".";
                        }
                    }
                    showToast(msg);
                    const li = document.querySelector(`#qr-code-list .qr-item[data-code="${qrCode}"]`);
                    if (li) {
                        li.querySelector('.qr-user').textContent = '—';
                        li.querySelector('.qr-name').textContent = '—';
                        li.querySelector('.qr-status').textContent = 'Available';
                        li.querySelector('.qr-assigned').textContent = '—';
                    }
                    if (qrSelect && !qrSelect.querySelector(`option[value="${qrCode}"]`)) {
                        const opt = document.createElement('option');
                        opt.value = qrCode;
                        opt.textContent = qrCode;
                        qrSelect.appendChild(opt);
                        if (qrSelect._searchable) {
                            qrSelect._searchable.updateOptions();
                        }
                    }
                    if (assignedSelect) {
                        const opt = assignedSelect.querySelector(`option[value="${qrCode}"]`);
                        if (opt) opt.remove();
                        assignedSelect.value = '';
                        if (assignedSelect._searchable) {
                            assignedSelect._searchable.updateOptions();
                            assignedSelect._searchable.input.value = '';
                        }
                    }
                } else {
                    showToast("Failed to release QR code.", true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast("An error occurred while releasing the QR code.", true);
            });
        });
    }

    if (addBtn) {
        addBtn.addEventListener("click", function () {
            const qrCode = newCodeInput ? newCodeInput.value.trim() : '';
            if (!qrCode) {
                alert("Please enter a QR code.");
                return;
            }

            fetch(kerbcycle_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: `action=add_qr_code&qr_code=${encodeURIComponent(qrCode)}&security=${kerbcycle_ajax.nonce}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const msg = data.data && data.data.message ? data.data.message : 'QR code added successfully.';
                    showToast(msg);
                    if (qrSelect && !qrSelect.querySelector(`option[value="${qrCode}"]`)) {
                        const opt = document.createElement('option');
                        opt.value = qrCode;
                        opt.textContent = qrCode;
                        qrSelect.appendChild(opt);
                        if (qrSelect._searchable) {
                            qrSelect._searchable.updateOptions();
                        }
                    }
                    newCodeInput.value = '';
                    if (data.data && data.data.row) {
                        const row = data.data.row;
                        const list = document.getElementById('qr-code-list');
                        if (list) {
                            const li = document.createElement('li');
                            li.className = 'qr-item';
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
                            const header = list.querySelector('.qr-header');
                            if (header && header.nextSibling) {
                                list.insertBefore(li, header.nextSibling);
                            } else {
                                list.appendChild(li);
                            }
                            const checkbox = li.querySelector('.qr-select');
                            if (checkbox) {
                                checkbox.addEventListener('change', function() {
                                    const items = document.querySelectorAll('#qr-code-list .qr-item .qr-select');
                                    const allChecked = Array.from(items).every(cb => cb.checked);
                                    const anyChecked = Array.from(items).some(cb => cb.checked);
                                    const selectAll = document.getElementById('qr-select-all');
                                    if (selectAll) {
                                        selectAll.checked = allChecked;
                                        selectAll.indeterminate = !allChecked && anyChecked;
                                    }
                                });
                            }
                            const span = li.querySelector('.qr-text');
                            if (span) {
                                span.addEventListener('blur', function() {
                                    const liElem = span.closest('li');
                                    const oldCode = liElem.dataset.code;
                                    const newCode = span.textContent.trim();
                                    if (oldCode === newCode) {
                                        return;
                                    }
                                    fetch(kerbcycle_ajax.ajax_url, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                                        body: `action=update_qr_code&old_code=${encodeURIComponent(oldCode)}&new_code=${encodeURIComponent(newCode)}&security=${kerbcycle_ajax.nonce}`
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        if (data.success) {
                                            liElem.dataset.code = newCode;
                                            const msg = data.data && data.data.message ? data.data.message : 'QR code updated';
                                            showToast(msg);
                                        } else {
                                            const err = data.data && data.data.message ? data.data.message : 'Failed to update QR code';
                                            showToast(err, true);
                                            span.textContent = oldCode;
                                        }
                                    });
                                });
                            }
                        }
                    }
                } else {
                    const err = data.data && data.data.message ? data.data.message : 'Failed to add QR code.';
                    showToast(err, true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while adding the QR code.', true);
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
            formData.append('action', 'import_qr_codes');
            formData.append('security', kerbcycle_ajax.nonce);
            formData.append('import_file', importFile.files[0]);
            fetch(kerbcycle_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const msg = data.data && data.data.message ? data.data.message : 'QR codes imported.';
                    showToast(msg);
                    location.reload();
                } else {
                    const err = data.data && data.data.message ? data.data.message : 'Failed to import QR codes.';
                    showToast(err, true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while importing QR codes.', true);
            });
        });
    }

    const bulkForm = document.getElementById('qr-code-bulk-form');
    if (bulkForm) {
        if (!kerbcycle_ajax.drag_drop_disabled) {
            jQuery('#qr-code-list').sortable({ items: 'li.qr-item' });
        }

        const selectAll = document.getElementById('qr-select-all');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                const checked = selectAll.checked;
                document.querySelectorAll('#qr-code-list .qr-item .qr-select').forEach(cb => {
                    cb.checked = checked;
                });
            });

            document.querySelectorAll('#qr-code-list .qr-item .qr-select').forEach(cb => {
                cb.addEventListener('change', function() {
                    const items = document.querySelectorAll('#qr-code-list .qr-item .qr-select');
                    const allChecked = Array.from(items).every(cb => cb.checked);
                    const anyChecked = Array.from(items).some(cb => cb.checked);
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = !allChecked && anyChecked;
                });
            });
        }

        document.querySelectorAll('#apply-bulk, #apply-bulk-top').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const targetSelect = document.getElementById(button.dataset.target);
                const action = targetSelect ? targetSelect.value : '';
                if (action === 'release') {
                    const codes = Array.from(document.querySelectorAll('#qr-code-list .qr-item .qr-select:checked')).map(cb => cb.closest('li').dataset.code);
                    if (!codes.length) {
                        alert('Please select one or more QR codes to release.');
                        return;
                    }

                    if (!confirm('Are you sure you want to release the selected QR codes?')) {
                        return;
                    }

                    fetch(kerbcycle_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: `action=bulk_release_qr_codes&qr_codes=${encodeURIComponent(codes.join(','))}&security=${kerbcycle_ajax.nonce}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + (data.data.message || 'Failed to release QR codes.'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred. Please try again.');
                    });
                } else if (action === 'delete') {
                    const selected = Array.from(document.querySelectorAll('#qr-code-list .qr-item .qr-select:checked'));
                    if (!selected.length) {
                        alert('Please select one or more QR codes to delete.');
                        return;
                    }

                    const availableItems = selected.filter(cb => cb.closest('li').querySelector('.qr-status').textContent.trim().toLowerCase() === 'available');
                    if (availableItems.length !== selected.length) {
                        alert('Only QR codes with Available status can be deleted.');
                        return;
                    }

                    const codes = availableItems.map(cb => cb.closest('li').dataset.code);

                    if (!confirm('Are you sure you want to delete the selected QR codes?')) {
                        return;
                    }

                    fetch(kerbcycle_ajax.ajax_url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: `action=bulk_delete_qr_codes&qr_codes=${encodeURIComponent(codes.join(','))}&security=${kerbcycle_ajax.nonce}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.data.message);
                            location.reload();
                        } else {
                            alert('Error: ' + (data.data.message || 'Failed to delete QR codes.'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An unexpected error occurred. Please try again.');
                    });
                }
            });
        });

        document.querySelectorAll('#qr-code-list .qr-item .qr-text').forEach(span => {
            span.addEventListener('blur', function() {
                const li = span.closest('li');
                const oldCode = li.dataset.code;
                const newCode = span.textContent.trim();
                if (oldCode === newCode) {
                    return;
                }
                fetch(kerbcycle_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: `action=update_qr_code&old_code=${encodeURIComponent(oldCode)}&new_code=${encodeURIComponent(newCode)}&security=${kerbcycle_ajax.nonce}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        li.dataset.code = newCode;
                        const msg = data.data && data.data.message ? data.data.message : 'QR code updated';
                        showToast(msg);
                    } else {
                        const err = data.data && data.data.message ? data.data.message : 'Failed to update QR code';
                        showToast(err, true);
                        span.textContent = oldCode;
                    }
                });
            });
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKerbcycleAdmin);
} else {
    initKerbcycleAdmin();
}
