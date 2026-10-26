/**
 * "Use camera" on the logistics Scan parcel page: reads the waybill or return slip barcode / QR code
 * with the phone camera and looks the parcel up. Features torch toggle, front/rear camera flip,
 * and audio confirmation beep on scan detection.
 */
(function () {
    const button = document.querySelector('[data-camera-scan]');
    const dialog = document.querySelector('[data-camera-dialog]');
    const form = document.querySelector('[data-parcel-scan-form]');
    if (!button || !dialog || !form || !navigator.mediaDevices?.getUserMedia) return;

    const video = dialog.querySelector('[data-camera-video]');
    const status = dialog.querySelector('[data-camera-status]');
    const torchBtn = dialog.querySelector('[data-camera-torch]');
    const flipBtn = dialog.querySelector('[data-camera-flip]');
    const input = form.querySelector('input[name="tracking"]');

    // Waybills and return slips carry a Code 128 barcode and a QR code; both hold tracking numbers.
    const WANTED = ['qr_code', 'code_128'];
    const ZXING_URL = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';
    const ZXING_INTEGRITY = 'sha384-BzBxP10ZE72aitqj5UMmUsbKFliP/DZqA8Wq+BNNhlIJDGoEd1tpkMYXOg9+n6sB';

    let stream = null;
    let timer = null;
    let read = null;
    let facingMode = 'environment';
    let isTorchOn = false;

    button.hidden = false;

    function playBeep() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.18, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.12);
        } catch (_) {}
    }

    async function nativeReader() {
        if (!('BarcodeDetector' in window)) return null;
        try {
            const formats = await BarcodeDetector.getSupportedFormats();
            const usable = WANTED.filter((format) => formats.includes(format));
            if (!usable.length) return null;
            const detector = new BarcodeDetector({ formats: usable });

            return async () => (await detector.detect(video))[0]?.rawValue ?? null;
        } catch (error) {
            return null;
        }
    }

    function loadZxing() {
        if (window.ZXing) return Promise.resolve(window.ZXing);

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = ZXING_URL;
            script.integrity = ZXING_INTEGRITY;
            script.crossOrigin = 'anonymous';
            script.onload = () => (window.ZXing ? resolve(window.ZXing) : reject(new Error('ZXing missing')));
            script.onerror = () => reject(new Error('ZXing failed to load'));
            document.head.appendChild(script);
        });
    }

    async function zxingReader() {
        const ZXing = await loadZxing();
        const hints = new Map([
            [ZXing.DecodeHintType.POSSIBLE_FORMATS, [ZXing.BarcodeFormat.QR_CODE, ZXing.BarcodeFormat.CODE_128]],
            [ZXing.DecodeHintType.TRY_HARDER, true],
        ]);
        const reader = new ZXing.MultiFormatReader();
        reader.setHints(hints);
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });

        return async () => {
            if (!video.videoWidth) return null;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            try {
                const bitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(new ZXing.HTMLCanvasElementLuminanceSource(canvas)));

                return reader.decodeWithState(bitmap).getText();
            } catch (error) {
                return null;
            }
        };
    }

    function updateTorchSupport() {
        if (!torchBtn) return;
        const track = stream?.getVideoTracks()[0];
        const capabilities = track?.getCapabilities ? track.getCapabilities() : {};
        if (capabilities.torch) {
            torchBtn.hidden = false;
            torchBtn.classList.toggle('active', isTorchOn);
        } else {
            torchBtn.hidden = true;
            isTorchOn = false;
        }
    }

    async function toggleTorch() {
        const track = stream?.getVideoTracks()[0];
        if (!track) return;
        try {
            isTorchOn = !isTorchOn;
            await track.applyConstraints({
                advanced: [{ torch: isTorchOn }],
            });
            torchBtn?.classList.toggle('active', isTorchOn);
        } catch (e) {
            isTorchOn = false;
            torchBtn?.classList.remove('active');
        }
    }

    function stop() {
        clearTimeout(timer);
        timer = null;
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
        video.srcObject = null;
        isTorchOn = false;
        if (torchBtn) {
            torchBtn.hidden = true;
            torchBtn.classList.remove('active');
        }
    }

    function close() {
        stop();
        if (dialog.open) dialog.close();
        button.focus();
    }

    async function startCamera() {
        stop();
        status.textContent = 'Starting camera…';
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            });

            if (!dialog.open) {
                stop();
                return;
            }

            video.srcObject = stream;
            await video.play();
            updateTorchSupport();
            if (flipBtn) flipBtn.hidden = false;
            status.textContent = 'Point the camera at the barcode or QR code.';
            detect();
        } catch (error) {
            stop();
            status.textContent = error?.name === 'NotAllowedError'
                ? 'Camera access was blocked. Allow camera access in your browser settings.'
                : 'The camera could not start. Please type the tracking number manually.';
        }
    }

    async function detect() {
        if (!stream) return;
        try {
            const value = (await read?.())?.trim();
            if (value) {
                playBeep();
                status.textContent = `Found ${value}. Looking up parcel…`;
                stop();
                dialog.close();
                input.value = value;
                form.requestSubmit();
                return;
            }
        } catch (error) {
            // Wait for next frame
        }
        timer = setTimeout(detect, 180);
    }

    button.addEventListener('click', async () => {
        status.textContent = 'Initializing barcode scanner…';
        dialog.showModal();
        try {
            read ??= (await nativeReader()) ?? (await zxingReader());
        } catch (error) {
            status.textContent = 'The barcode reader could not load. Check your internet connection or enter tracking manually.';
            return;
        }
        await startCamera();
    });

    if (torchBtn) {
        torchBtn.addEventListener('click', toggleTorch);
    }

    if (flipBtn) {
        flipBtn.addEventListener('click', async () => {
            facingMode = facingMode === 'environment' ? 'user' : 'environment';
            await startCamera();
        });
    }

    dialog.querySelector('[data-camera-close]')?.addEventListener('click', close);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });
    window.addEventListener('pagehide', stop);
})();
