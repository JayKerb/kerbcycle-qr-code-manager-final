document.addEventListener('DOMContentLoaded', function () {
    let scannedQRCode = ''; // Store QR code data
    const scanResult = document.getElementById("scan-result");
    const assignBtn = document.getElementById("assign-qr-btn");
    const customerIdInput = document.getElementById("customer-id");

    // Check if required elements exist
    if (!scanResult || !assignBtn || !customerIdInput) {
        console.error('Required DOM elements not found');
        return;
    }

    // Initialize scanner with error handling
    let scanner;
    try {
        scanner = new Html5Qrcode("reader", true);
    } catch (error) {
        console.error('Failed to initialize QR scanner:', error);
        scanResult.style.display = 'block';
        scanResult.innerHTML = '<strong>❌ Error: Failed to initialize QR scanner</strong>';
        return;
    }

    assignBtn.addEventListener("click", function () {
        const userId = customerIdInput.value;
        const qrCode = scannedQRCode; // Use stored QR code data

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
                // Clear the form
                customerIdInput.value = '';
                scannedQRCode = '';
                scanResult.style.display = 'none';
            } else {
                alert("Failed to assign QR code: " + (data.data?.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("An error occurred while assigning the QR code.");
        });
    });

    function onScanSuccess(decodedText) {
        scannedQRCode = decodedText; // Store QR code data
        scanner.pause(); // Stop scanning after success
        scanResult.style.display = 'block'; // Make the result visible
        scanResult.classList.add('updated'); // Use WordPress success styles
        scanResult.innerHTML = `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${decodedText}</code>`;
    }

    function onScanError(error) {
        // Handle scan errors gracefully
        console.warn('QR scan error:', error);
    }

    // Start scanner with error handling
    scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess, onScanError)
        .catch(error => {
            console.error('Failed to start scanner:', error);
            scanResult.style.display = 'block';
            scanResult.innerHTML = '<strong>❌ Error: Failed to start camera. Please check permissions.</strong>';
        });
});