// === KerbCycle QR Scanner Adapter (BarcodeDetector → ZXing → jsQR) ===
async function createQrScannerAdapter({
  videoEl,
  onResult,
  constraints = { facingMode: "environment" },
}) {
  let stream = null;
  let paused = false;
  let running = false;
  let rafId = null;
  let currentImpl = null; // "native" | "zxing" | "jsqr"
  let zxingReader = null;
  let offscreenCanvas = null,
    offscreenCtx = null;

  function stopStream() {
    if (rafId) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
    running = false;
  }

  async function getCameraStream() {
    if (stream) return stream;
    stream = await navigator.mediaDevices.getUserMedia({
      video: constraints,
      audio: false,
    });
    videoEl.srcObject = stream;
    await videoEl.play();
    return stream;
  }

  // --- Impl #1: Native BarcodeDetector (if supported) ---
  async function tryNative() {
    if (!("BarcodeDetector" in window)) return false;
    const formats =
      typeof BarcodeDetector.getSupportedFormats === "function"
        ? await BarcodeDetector.getSupportedFormats()
        : ["qr_code"]; // older impls
    if (!formats || !formats.map(String).includes("qr_code")) return false;

    await getCameraStream();
    const detector = new BarcodeDetector({ formats: ["qr_code"] });
    currentImpl = "native";
    running = true;
    paused = false;

    const loop = async () => {
      if (!running || paused) {
        rafId = requestAnimationFrame(loop);
        return;
      }
      try {
        const barcodes = await detector.detect(videoEl);
        if (barcodes && barcodes.length) {
          const text = barcodes[0].rawValue || barcodes[0].rawValueText || "";
          if (text) {
            paused = true; // emulate "pause on success" behavior
            onResult(String(text));
          }
        }
      } catch (e) {
        // ignore frame-level errors
      }
      rafId = requestAnimationFrame(loop);
    };
    loop();
    return true;
  }

  // --- Impl #2: ZXing (@zxing/browser) ---
  async function tryZxing() {
    if (!window.ZXingBrowser && !window.ZXing) return false;
    const ZXingBrowser = window.ZXingBrowser || window.ZXing?.Browser;
    if (!ZXingBrowser) return false;

    await getCameraStream();
    currentImpl = "zxing";
    running = true;
    paused = false;

    // Use decodeFromVideoDevice for continuous scanning.
    zxingReader = new ZXingBrowser.BrowserQRCodeReader();
    await zxingReader.decodeFromVideoDevice(null, videoEl, (result, err) => {
      if (paused) return;
      if (result && result.getText) {
        paused = true;
        onResult(String(result.getText()));
      }
      // err is normal per frame; ignore
    });

    return true;
  }

  // --- Impl #3: jsQR (canvas-based) ---
  async function tryJsqr() {
    if (typeof window.jsQR !== "function") return false;
    await getCameraStream();
    currentImpl = "jsqr";
    running = true;
    paused = false;

    offscreenCanvas = document.createElement("canvas");
    offscreenCtx = offscreenCanvas.getContext("2d", {
      willReadFrequently: true,
    });

    const loop = () => {
      if (!running || paused) {
        rafId = requestAnimationFrame(loop);
        return;
      }
      const w = videoEl.videoWidth || 640;
      const h = videoEl.videoHeight || 480;
      if (w && h) {
        offscreenCanvas.width = w;
        offscreenCanvas.height = h;
        offscreenCtx.drawImage(videoEl, 0, 0, w, h);
        const img = offscreenCtx.getImageData(0, 0, w, h);
        const code = window.jsQR(img.data, w, h, {
          inversionAttempts: "dontInvert",
        });
        if (code && code.data) {
          paused = true;
          onResult(String(code.data));
        }
      }
      rafId = requestAnimationFrame(loop);
    };
    loop();
    return true;
  }

  return {
    async start() {
      if (running) return;
      if (await tryNative()) return;
      if (await tryZxing()) return;
      if (await tryJsqr()) return;
      throw new Error(
        "No scanner implementation available (BarcodeDetector/ZXing/jsQR).",
      );
    },
    pause() {
      paused = true;
    },
    resume() {
      if (running) {
        paused = false;
      }
    },
    stop() {
      try {
        if (zxingReader && zxingReader.reset) zxingReader.reset();
      } catch (e) {
        // ignore errors when resetting reader
      }
      zxingReader = null;
      stopStream();
      paused = false;
      currentImpl = null;
    },
    getStateLabel() {
      if (!running) return "NOT_STARTED";
      return paused ? "PAUSED" : "SCANNING";
    },
    getImplementation() {
      return currentImpl;
    },
  };
}

function escapeHtml(value) {
  if (value === null || value === undefined) {
    return "";
  }
  return String(value).replace(/[&<>"']/g, (char) => {
    switch (char) {
      case "&":
        return "&amp;";
      case "<":
        return "&lt;";
      case ">":
        return "&gt;";
      case '"':
        return "&quot;";
      case "'":
        return "&#039;";
      default:
        return char;
    }
  });
}

function setScanResult(element, type, html) {
  if (!element) return;
  element.style.display = "block";
  element.classList.remove("error", "updated");
  if (type === "error") {
    element.classList.add("error");
  } else {
    element.classList.add("updated");
  }
  element.innerHTML = html;
}

function initDashboardScanner() {
  const readerEl = document.getElementById("reader");
  const scanResult = document.getElementById("scan-result");
  const scannerEnabled = kerbcycle_ajax.scanner_enabled;
  const addFromScannerBtn = document.getElementById("dashboard-add-qr-btn");
  const resetScannerBtn = document.getElementById("dashboard-reset-scan-btn");
  let scanner = null;
  let lastScannedCode = "";
  let addInProgress = false;

  function updateAddButtonState() {
    if (!addFromScannerBtn) {
      return;
    }
    const shouldDisable =
      addInProgress || !scannerEnabled || !lastScannedCode;
    addFromScannerBtn.disabled = shouldDisable;
  }

  updateAddButtonState();

  if (resetScannerBtn && !scannerEnabled) {
    resetScannerBtn.disabled = true;
  }

  function pauseActiveScanner() {
    if (scanner && typeof scanner.pause === "function") {
      try {
        scanner.pause();
      } catch (e) {
        console.warn("Unable to pause dashboard scanner", e);
      }
    }
  }

  if (addFromScannerBtn) {
    addFromScannerBtn.addEventListener("click", () => {
      if (!scannerEnabled) {
        setScanResult(
          scanResult,
          "error",
          "<strong>❌ QR code scanner is disabled in settings.</strong>",
        );
        return;
      }

      if (addInProgress) {
        return;
      }

      if (!lastScannedCode) {
        setScanResult(
          scanResult,
          "error",
          "<strong>❌ Please scan a QR code before adding.</strong>",
        );
        return;
      }

      const addHandler = window.kerbcycleAddQrCodeToRepository;
      if (typeof addHandler !== "function") {
        setScanResult(
          scanResult,
          "error",
          "<strong>❌ Unable to add QR code.</strong> Please use the manual form below.",
        );
        return;
      }

      const safeCode = escapeHtml(lastScannedCode);
      addInProgress = true;
      addFromScannerBtn.setAttribute("aria-busy", "true");
      updateAddButtonState();

      setScanResult(
        scanResult,
        "success",
        `<strong>⏳ Adding QR Code...</strong><br>Code: <code>${safeCode}</code>`,
      );

      let addPromise;
      try {
        addPromise = addHandler(lastScannedCode, {
          source: "dashboard-scanner",
          showAlertOnEmpty: false,
          clearInput: false,
        });
      } catch (error) {
        addInProgress = false;
        addFromScannerBtn.removeAttribute("aria-busy");
        updateAddButtonState();
        console.error("Unable to add QR code from dashboard scanner", error);
        setScanResult(
          scanResult,
          "error",
          `<strong>❌ Unable to add QR code.</strong> ${escapeHtml(
            error && error.message ? error.message : String(error),
          )}`,
        );
        return;
      }

      Promise.resolve(addPromise)
        .then((result) => {
          if (result && result.success) {
            lastScannedCode = "";
            setScanResult(
              scanResult,
              "success",
              `<strong>✅ QR Code added to repository.</strong><br>Code: <code>${safeCode}</code><br>Use "Scan Reset" to scan another code.`,
            );
            return;
          }

          if (result && result.reason === "empty") {
            setScanResult(
              scanResult,
              "error",
              "<strong>❌ Please scan a QR code before adding.</strong>",
            );
            return;
          }

          const errMessage =
            (result &&
              result.data &&
              result.data.data &&
              result.data.data.message) ||
            (result && result.data && result.data.message) ||
            (result && result.error && result.error.message) ||
            (result &&
              typeof result.error === "string" &&
              result.error) ||
            "Failed to add QR code.";

          setScanResult(
            scanResult,
            "error",
            `<strong>❌ ${escapeHtml(errMessage)}</strong>`,
          );
        })
        .catch((error) => {
          console.error("Unable to add QR code from dashboard scanner", error);
          setScanResult(
            scanResult,
            "error",
            `<strong>❌ Unable to add QR code.</strong> ${escapeHtml(
              error && error.message ? error.message : String(error),
            )}`,
          );
        })
        .finally(() => {
          addInProgress = false;
          addFromScannerBtn.removeAttribute("aria-busy");
          updateAddButtonState();
        });
    });
  }

  if (resetScannerBtn) {
    resetScannerBtn.addEventListener("click", () => {
      if (!scannerEnabled) {
        setScanResult(
          scanResult,
          "error",
          "<strong>❌ QR code scanner is disabled in settings.</strong>",
        );
        return;
      }

      lastScannedCode = "";
      updateAddButtonState();

      if (!scanner) {
        setScanResult(
          scanResult,
          "error",
          "<strong>❌ Scanner is not ready yet.</strong>",
        );
        return;
      }

      const successMessage =
        "<strong>🔄 Scanner reset.</strong> Ready to scan a new QR code.";

      try {
        const resumeResult =
          typeof scanner.resume === "function"
            ? scanner.resume()
            : typeof scanner.start === "function"
            ? scanner.start()
            : null;

        if (resumeResult && typeof resumeResult.then === "function") {
          resumeResult
            .then(() => {
              setScanResult(scanResult, "success", successMessage);
            })
            .catch((error) => {
              console.error(
                "Unable to reset dashboard scanner",
                error,
              );
              setScanResult(
                scanResult,
                "error",
                `<strong>❌ Unable to reset scanner.</strong> ${escapeHtml(
                  error && error.message ? error.message : String(error),
                )}`,
              );
            });
        } else {
          setScanResult(scanResult, "success", successMessage);
        }
      } catch (error) {
        console.error("Unable to reset dashboard scanner", error);
        setScanResult(
          scanResult,
          "error",
          `<strong>❌ Unable to reset scanner.</strong> ${escapeHtml(
            error && error.message ? error.message : String(error),
          )}`,
        );
      }
    });
  }

  if (scannerEnabled && readerEl) {
    const video = document.createElement("video");
    video.setAttribute("playsinline", "true");
    video.style.width = "100%";
    video.style.maxWidth = "400px";
    readerEl.innerHTML = "";
    readerEl.appendChild(video);

    const onScanSuccess = (decodedText) => {
      pauseActiveScanner();
      lastScannedCode = decodedText || "";
      updateAddButtonState();
      const safeCode = escapeHtml(decodedText || "");
      setScanResult(
        scanResult,
        "success",
        `<strong>✅ QR Code Scanned!</strong><br>Code: <code>${safeCode}</code>`,
      );

      const event = new CustomEvent("dashboard-qr-scanned", {
        detail: { code: decodedText },
      });
      document.dispatchEvent(event);
    };

    createQrScannerAdapter({
      videoEl: video,
      onResult: onScanSuccess,
      constraints: { facingMode: "environment" },
    })
      .then((scannerCtrl) => {
        scanner = scannerCtrl;
        scanner.start().catch((err) => {
          console.error("Failed to start dashboard scanner", err);
          setScanResult(
            scanResult,
            "error",
            `<strong>❌ Unable to start scanner.</strong> Please ensure you have a camera and have granted permission.`,
          );
        });
      })
      .catch((err) => {
        console.error("Failed to create dashboard scanner adapter", err);
        setScanResult(
          scanResult,
          "error",
          `<strong>❌ Unable to initialize scanner.</strong> A suitable camera may not be available.`,
        );
      });
  }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initDashboardScanner);
} else {
  initDashboardScanner();
}
