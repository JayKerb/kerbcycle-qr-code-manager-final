document.addEventListener('DOMContentLoaded', function () {
    const scanner = new Html5Qrcode("reader", true);
    const scanResult = document.getElementById("scan-result");
    let scannedQrCode = null; // Variable to store the scanned QR code

    // Function to escape HTML to prevent XSS
    function escapeHTML(str) {
        const p = document.createElement('p');
        p.appendChild(document.createTextNode(str));
        return p.innerHTML;
    }

    document.getElementById("assign-qr-btn").addEventListener("click", function () {
        const userId = document.getElementById("customer-id").value;

        if (!userId || !scannedQrCode) {
            alert("Please enter user ID and scan a QR code.");
            return;
        }

        fetch(kerbcycle_ajax.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: `action=assign_qr_code&qr_code=${encodeURIComponent(scannedQrCode)}&customer_id=${encodeURIComponent(userId)}&security=${kerbcycle_ajax.nonce}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("QR code assigned successfully.");
                scannedQrCode = null; // Reset after assignment
                scanResult.style.display = 'none'; // Hide result
            } else {
                alert("Failed to assign QR code: " + data.data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("An error occurred while assigning the QR code.");
        });
    });

    function onScanSuccess(decodedText) {
        scanner.pause(); // Stop scanning after success
        scannedQrCode = decodedText; // Store the result in a variable

        scanResult.style.display = 'block'; // Make the result visible
        scanResult.classList.add('updated'); // Use WordPress success styles
        scanResult.innerHTML = `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${escapeHTML(decodedText)}</code>`;
    }

    function onScanError(errorMessage) {
        // Handle scan error, you can ignore it or log it
        // console.error(errorMessage);
    }

    scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess, onScanError);
});