function initKerbcycleScanner() {
    let scanner = null;
    const reader = document.getElementById("reader");
    const scanResult = document.getElementById("scan-result");
    const qrSelect = document.getElementById("qr-code-select");
    const sendEmailCheckbox = document.getElementById("send-email");
    const sendSmsCheckbox = document.getElementById("send-sms");
    const sendReminderCheckbox = document.getElementById("send-reminder");
    const assignBtn = document.getElementById("assign-qr-btn");
    const releaseBtn = document.getElementById("release-qr-btn");
    let scannedCode = '';

    if (typeof Html5Qrcode !== 'undefined' && reader) {
        try {
            scanner = new Html5Qrcode("reader", true);
        } catch (e) {
            console.error('Failed to initialize QR code scanner', e);
        }
    } else {
        console.warn('Html5Qrcode library not loaded. Scanner functionality disabled.');
    }

    if (assignBtn) {
        assignBtn.addEventListener("click", function () {
            const userField = document.getElementById("customer-id");
            const userId = userField ? userField.value : '';
            const qrCode = scannedCode || (qrSelect ? qrSelect.value : '');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            const sendSms = sendSmsCheckbox ? sendSmsCheckbox.checked : false;
            const sendReminder = sendReminderCheckbox ? sendReminderCheckbox.checked : false;

            if (!userId || !qrCode) {
                alert(kerbcycle_i18n.select_user);
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
                    let msg = kerbcycle_i18n.assign_success;
                    if (data.data && typeof data.data.sms_sent !== 'undefined') {
                        if (data.data.sms_sent) {
                            msg += ' ' + kerbcycle_i18n.sms_sent;
                        } else {
                            msg += ' ' + kerbcycle_i18n.sms_failed + ' ' + (data.data.sms_error || kerbcycle_i18n.unknown_error) + '.';
                        }
                    }
                    alert(msg);
                    try {
                        localStorage.setItem('kerbcycleAssignment', Date.now().toString());
                    } catch (e) {
                        console.warn('LocalStorage unavailable', e);
                    }
                    location.reload();
                } else {
                    const err = data.data && data.data.message ? data.data.message : kerbcycle_i18n.assign_failed;
                    alert(err);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(kerbcycle_i18n.assign_error);
            });
        });
    }

    if (releaseBtn) {
        releaseBtn.addEventListener("click", function () {
            const qrCode = scannedCode || (qrSelect ? qrSelect.value : '');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            const sendSms = sendSmsCheckbox ? sendSmsCheckbox.checked : false;
            if (!qrCode) {
                alert(kerbcycle_i18n.scan_or_select);
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
                    let msg = kerbcycle_i18n.release_success;
                    if (data.data && typeof data.data.sms_sent !== 'undefined') {
                        if (data.data.sms_sent) {
                            msg += ' ' + kerbcycle_i18n.sms_sent;
                        } else {
                            msg += ' ' + kerbcycle_i18n.sms_failed + ' ' + (data.data.sms_error || kerbcycle_i18n.unknown_error) + '.';
                        }
                    }
                    alert(msg);
                    location.reload();
                } else {
                    alert(kerbcycle_i18n.release_failed);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(kerbcycle_i18n.release_error);
            });
        });
    }

    function onScanSuccess(decodedText) {
        if (scanner && scanner.pause) {
            scanner.pause(); // Stop scanning after success
        }
        scannedCode = decodedText;
        scanResult.style.display = 'block'; // Make the result visible
        scanResult.classList.add('updated'); // Use WordPress success styles
        scanResult.innerHTML = `<strong>✅ ${kerbcycle_i18n.scan_success}</strong><br>${kerbcycle_i18n.content_label} <code></code>`;
        const codeEl = scanResult.querySelector('code');
        if (codeEl) {
            codeEl.textContent = decodedText;
        }
        if (qrSelect) {
            qrSelect.value = decodedText;
        }
    }

    if (scanner && typeof scanner.start === 'function') {
        try {
            scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess);
        } catch (e) {
            console.error('Failed to start QR code scanner', e);
        }
    }

    const bulkForm = document.getElementById('qr-code-bulk-form');
    if (bulkForm) {
        jQuery('#qr-code-list').sortable({ items: 'li.qr-item' });

        document.getElementById('apply-bulk').addEventListener('click', function(e) {
            e.preventDefault();
            const action = document.getElementById('bulk-action').value;
            if (action === 'release') {
                const codes = Array.from(document.querySelectorAll('#qr-code-list .qr-select:checked')).map(cb => cb.closest('li').dataset.code);
                if (!codes.length) {
                    alert(kerbcycle_i18n.bulk_select);
                    return;
                }

                if (!confirm(kerbcycle_i18n.bulk_confirm)) {
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
                        alert(kerbcycle_i18n.error_prefix + ' ' + (data.data.message || kerbcycle_i18n.release_failed));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(kerbcycle_i18n.unexpected_error);
                });
            }
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
                    } else {
                        alert(kerbcycle_i18n.update_failed);
                        span.textContent = oldCode;
                    }
                });
            });
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKerbcycleScanner);
} else {
    initKerbcycleScanner();
}

