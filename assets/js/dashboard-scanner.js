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
    offscreenCtx = offscreenCanvas.getContext("2d", { willReadFrequently: true });

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
      throw new Error("No scanner implementation available (BarcodeDetector/ZXing/jsQR).");
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

function initDashboardScanner() {
    const readerEl = document.getElementById("reader");
    const scannerEnabled = kerbcycle_ajax.scanner_enabled;

    if (scannerEnabled && readerEl) {
        const video = document.createElement("video");
        video.setAttribute("playsinline", "true");
        video.style.width = "100%";
        video.style.maxWidth = "400px";
        readerEl.innerHTML = "";
        readerEl.appendChild(video);

        const onScanSuccess = (decodedText) => {
            // The logic for handling the scanned code on the dashboard is not yet developed.
            // For now, we can dispatch an event so other scripts could hook into it if needed.
            const event = new CustomEvent('dashboard-qr-scanned', { detail: { code: decodedText } });
            document.dispatchEvent(event);
            console.log(`Scanned QR Code on dashboard: ${decodedText}`);
        };

        createQrScannerAdapter({
            videoEl: video,
            onResult: onScanSuccess,
            constraints: { facingMode: "environment" },
        })
        .then(scanner => {
            scanner.start().catch(err => {
                console.error("Failed to start dashboard scanner", err);
                if (readerEl) {
                    readerEl.innerHTML = '<strong>❌ Unable to start scanner.</strong> Please ensure you have a camera and have granted permission.';
                }
            });
        })
        .catch(err => {
            console.error("Failed to create dashboard scanner adapter", err);
            if (readerEl) {
                readerEl.innerHTML = '<strong>❌ Unable to initialize scanner.</strong> A suitable camera may not be available.';
            }
        });
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDashboardScanner);
} else {
    initDashboardScanner();
}
