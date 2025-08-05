document.addEventListener('DOMContentLoaded', function () {
    const scanner = new Html5Qrcode("reader", true);
    const scanResult = document.getElementById("scan-result");
    const qrSelect = document.getElementById("qr-code-select");
    const sendEmailCheckbox = document.getElementById("send-email");
    const assignBtn = document.getElementById("assign-qr-btn");
    const releaseBtn = document.getElementById("release-qr-btn");
    let scannedCode = '';

    if (assignBtn) {
        assignBtn.addEventListener("click", function () {
            const userField = document.getElementById("customer-id");
            const userId = userField ? userField.value : '';
            const qrCode = scannedCode || (qrSelect ? qrSelect.value : '');
            const sendEmail = sendEmailCheckbox ? sendEmailCheckbox.checked : false;

            if (!userId || !qrCode) {
                alert("Please select a user and scan or choose a QR code.");
                return;
            }

            fetch(kerbcycle_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: `action=assign_qr_code&qr_code=${encodeURIComponent(qrCode)}&customer_id=${encodeURIComponent(userId)}&send_email=${sendEmail ? 1 : 0}&security=${kerbcycle_ajax.nonce}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("QR code assigned successfully.");
                } else {
                    alert("Failed to assign QR code.");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("An error occurred while assigning the QR code.");
            });
        });
    }

    if (releaseBtn) {
        releaseBtn.addEventListener("click", function () {
            const qrCode = scannedCode || (qrSelect ? qrSelect.value : '');
            if (!qrCode) {
                alert("Please scan or select a QR code to release.");
                return;
            }

            fetch(kerbcycle_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: `action=release_qr_code&qr_code=${encodeURIComponent(qrCode)}&security=${kerbcycle_ajax.nonce}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("QR code released successfully.");
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
});