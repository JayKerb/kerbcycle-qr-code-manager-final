function initKerbcycleScanner() {
    const scannerAllowed = kerbcycle_ajax.scanner_enabled;
    const scanResult = document.getElementById("scan-result");
    const qrSelect = document.getElementById("qr-code-select");
    const qrList = document.getElementById("qr-code-list");
    let scannedCode = '';

    if (scannerAllowed && typeof Html5Qrcode !== 'undefined' && document.getElementById('reader')) {
        const scanner = new Html5Qrcode("reader", true);

        function onScanSuccess(decodedText) {
            scanner.pause();
            scannedCode = decodedText;
            scanResult.style.display = 'block';
            scanResult.classList.add('updated');
            scanResult.innerHTML = `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${decodedText}</code>`;
            if (typeof showToast === 'function') {
                showToast('QR code scanned successfully.');
            }

            if (qrList && !qrList.querySelector(`.qr-item[data-code="${decodedText}"]`)) {
                fetch(kerbcycle_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        action: 'add_qr_code',
                        qr_code: decodedText,
                        security: kerbcycle_ajax.nonce
                    })
                })
                .then(resp => resp.json())
                .then(data => {
                    if (data.success && data.data && data.data.row) {
                        const row = data.data.row;
                        if (qrSelect && !qrSelect.querySelector(`option[value="${row.qr_code}"]`)) {
                            const opt = document.createElement('option');
                            opt.value = row.qr_code;
                            opt.textContent = row.qr_code;
                            qrSelect.appendChild(opt);
                        }
                        if (qrSelect) {
                            qrSelect.value = row.qr_code;
                        }
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
                        const header = qrList.querySelector('.qr-header');
                        if (header && header.nextSibling) {
                            qrList.insertBefore(li, header.nextSibling);
                        } else {
                            qrList.appendChild(li);
                        }
                    } else {
                        const msg = data.data && data.data.message ? data.data.message : 'Failed to add QR code.';
                        if (typeof showToast === 'function') {
                            showToast(msg, true);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (typeof showToast === 'function') {
                        showToast('An error occurred while adding the QR code.', true);
                    }
                });
            } else if (qrSelect) {
                qrSelect.value = decodedText;
            }
        }

        scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess)
            .catch(err => {
                console.error(`Unable to start scanning, error: ${err}`);
                scanResult.style.display = 'block';
                scanResult.classList.add('error');
                scanResult.innerHTML = '<strong>❌ Unable to start scanner.</strong> Please ensure you have a camera and have granted permission.';
                if (typeof showToast === 'function') {
                    showToast('Unable to start scanner.', true);
                }
            });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKerbcycleScanner);
} else {
    initKerbcycleScanner();
}
