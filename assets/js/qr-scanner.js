function initKerbcycleScanner() {
    const scanner = new Html5Qrcode("reader", true);
    const scanResult = document.getElementById("scan-result");
    const qrSelect = document.getElementById("qr-code-select");
    const sendEmailCheckbox = document.getElementById("send-email");
    const sendSmsCheckbox = document.getElementById("send-sms");
    const sendReminderCheckbox = document.getElementById("send-reminder");
    const assignBtn = document.getElementById("assign-qr-btn");
    const releaseBtn = document.getElementById("release-qr-btn");
    const addBtn = document.getElementById("add-qr-btn");
    let scannedCode = '';

    if (assignBtn) {
        assignBtn.addEventListener("click", function () {
            const userField = document.getElementById("customer-id");
            const userId = userField ? userField.value : '';
            const qrCode = scannedCode || (qrSelect ? qrSelect.value : '');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            const sendSms = sendSmsCheckbox ? sendSmsCheckbox.checked : false;
            const sendReminder = sendReminderCheckbox ? sendReminderCheckbox.checked : false;

            if (!userId || !qrCode) {
                alert("Please select a user and scan or choose a QR code.");
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
                    alert(msg);
                    try {
                        localStorage.setItem('kerbcycleAssignment', Date.now().toString());
                    } catch (e) {
                        console.warn('LocalStorage unavailable', e);
                    }
                    location.reload();
                } else {
                    const err = data.data && data.data.message ? data.data.message : "Failed to assign QR code.";
                    alert(err);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("An error occurred while assigning the QR code.");
            });
        });
    }

    if (addBtn) {
        addBtn.addEventListener("click", function (e) {
            e.preventDefault();
            const input = document.getElementById("manual-qr-input");
            const qrCode = input ? input.value.trim() : '';
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
                    alert('QR code added successfully.');
                    location.reload();
                } else {
                    const err = data.data && data.data.message ? data.data.message : 'Failed to add QR code.';
                    alert(err);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the QR code.');
            });
        });
    }

    if (releaseBtn) {
        releaseBtn.addEventListener("click", function () {
            const qrCode = scannedCode || (qrSelect ? qrSelect.value : '');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;
            const sendSms = sendSmsCheckbox ? sendSmsCheckbox.checked : false;
            if (!qrCode) {
                alert("Please scan or select a QR code to release.");
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
                    alert(msg);
                    location.reload();
                } else {
                    alert("Failed to release QR code.");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("An error occurred while releasing the QR code.");
            });
        });
    }

    function onScanSuccess(decodedText) {
        scanner.pause(); // Stop scanning after success
        scannedCode = decodedText;
        scanResult.style.display = 'block'; // Make the result visible
        scanResult.classList.add('updated'); // Use WordPress success styles
        scanResult.innerHTML = `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${decodedText}</code>`;
        if (qrSelect) {
            qrSelect.value = decodedText;
        }
    }

    scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess);

    const bulkForm = document.getElementById('qr-code-bulk-form');
    if (bulkForm) {
        jQuery('#qr-code-list').sortable({ items: 'li.qr-item' });

        document.getElementById('apply-bulk').addEventListener('click', function(e) {
            e.preventDefault();
            const action = document.getElementById('bulk-action').value;
            if (action === 'release') {
                const codes = Array.from(document.querySelectorAll('#qr-code-list .qr-select:checked')).map(cb => cb.closest('li').dataset.code);
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
                        alert('Failed to update QR code');
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

