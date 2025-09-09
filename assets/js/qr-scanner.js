function initKerbcycleScanner() {
    const scannerAllowed = kerbcycle_ajax.scanner_enabled;
    const scanResult = document.getElementById("scan-result");
    const assignBtn = document.getElementById("assign-qr-btn");
    const customerIdField = document.getElementById("customer-id");
    let scannedCode = '';

    if (scannerAllowed && typeof Html5Qrcode !== 'undefined' && document.getElementById('reader')) {
        const scanner = new Html5Qrcode("reader", true);

        function onScanSuccess(decodedText) {
            scanner.pause();
            scannedCode = decodedText;
            scanResult.style.display = 'block';
            scanResult.classList.add('updated');
            scanResult.innerHTML = `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${decodedText}</code>`;
        }

        scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess)
            .catch(err => {
                console.error(`Unable to start scanning, error: ${err}`);
                scanResult.style.display = 'block';
                scanResult.classList.add('error');
                scanResult.innerHTML = '<strong>❌ Unable to start scanner.</strong> Please ensure you have a camera and have granted permission.';
            });
    }

    if (assignBtn) {
        assignBtn.addEventListener("click", function () {
            const userId = customerIdField ? customerIdField.value : '';

            if (!userId || !scannedCode) {
                alert("Please enter a customer ID and scan a QR code.");
                return;
            }

            fetch(kerbcycle_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams({
                    action: 'assign_qr_code',
                    qr_code: scannedCode,
                    customer_id: userId,
                    security: kerbcycle_ajax.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("QR code assigned successfully.");
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
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKerbcycleScanner);
} else {
    initKerbcycleScanner();
}
