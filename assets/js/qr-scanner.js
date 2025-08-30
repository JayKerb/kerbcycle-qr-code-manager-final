function initKerbcycleScanner() {
    const scannerAllowed = kerbcycle_ajax.scanner_enabled;
    const scanResult = document.getElementById("scan-result");
    const qrSelect = document.getElementById("qr-code-select");
    let scannedCode = '';

    if (scannerAllowed && typeof Html5Qrcode !== 'undefined' && document.getElementById('reader')) {
        const scanner = new Html5Qrcode("reader", true);

        function onScanSuccess(decodedText) {
            scanner.pause(); // Stop scanning after success
            scannedCode = decodedText;
            scanResult.style.display = 'block'; // Make the result visible
            scanResult.classList.add('updated'); // Use WordPress success styles
            scanResult.innerHTML = `<strong>✅ QR Code Scanned Successfully!</strong><br>Content: <code>${decodedText}</code>`;
            if (qrSelect) {
                // Check if this option already exists. If not, create it.
                let option = qrSelect.querySelector(`option[value="${decodedText}"]`);
                if (!option) {
                    option = document.createElement('option');
                    option.value = decodedText;
                    option.textContent = decodedText;
                    qrSelect.appendChild(option);
                }
                qrSelect.value = decodedText;
            }
        }

        scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: 250 }, onScanSuccess)
            .catch(err => {
                console.error(`Unable to start scanning, error: ${err}`);
            });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKerbcycleScanner);
} else {
    initKerbcycleScanner();
}
