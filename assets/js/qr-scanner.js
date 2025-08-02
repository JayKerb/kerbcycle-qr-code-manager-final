document.addEventListener('DOMContentLoaded', function () {
    const scanner = new Html5Qrcode("reader", true);
    const scanResult = document.getElementById("scan-result");
    let scannedCode = '';

    document.getElementById("assign-qr-btn").addEventListener("click", function () {
        const userId = document.getElementById("customer-id").value;
        const qrCode = scannedCode;

        if (!userId || !qrCode) {
            alert("Please enter user ID and scan a QR code.");
            return;
        }

        fetch(kerbcycle_ajax.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: `action=assign_qr_code&qr_code=${encodeURIComponent(qrCode)}&customer_id=${encodeURIComponent(userId)}&security=${kerbcycle_ajax.nonce}`
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

    function onScanSuccess(decodedText) {
        scanner.pause(); // Stop scanning after success
        scannedCode = decodedText;
        scanResult.style.display = 'block'; // Make the result visible
        scanResult.classList.add('updated'); // Use WordPress success styles
        scanResult.innerHTML = `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${decodedText}</code>`;
    }

    scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess);
});