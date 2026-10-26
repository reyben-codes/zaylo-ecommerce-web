/**
 * "Use camera" on the logistics Scan parcel page: reads the waybill's barcode or QR code with the phone camera
 * and looks the parcel up, so pickup staff need no separate scanner. Uses the browser's BarcodeDetector where it
 * exists (Chrome on Android, for example) and otherwise loads the ZXing reader on first use (iPhone, Firefox,
 * desktop browsers). Typing or a USB/Bluetooth scanner still works everywhere.
 */
(function () {
    const button = document.querySelector('[data-camera-scan]');
    const dialog = document.querySelector('[data-camera-dialog]');
    const form = document.querySelector('[data-parcel-scan-form]');
    if (!button || !dialog || !form || !navigator.mediaDevices?.getUserMedia) return;

    const video = dialog.querySelector('[data-camera-video]');
    const status = dialog.querySelector('[data-camera-status]');
    const input = form.querySelector('input[name="tracking"]');
    // Waybills carry a Code 128 barcode and a QR code; both hold the tracking number.
    const WANTED = ['qr_code', 'code_128'];
    const ZXING_URL = 'https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js';
    const ZXING_INTEGRITY = 'sha384-BzBxP10ZE72aitqj5UMmUsbKFliP/DZqA8Wq+BNNhlIJDGoEd1tpkMYXOg9+n6sB';
    let stream = null;
    let timer = null;
    let read = null; // async () => string|null, reading one video frame

    button.hidden = false;

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
                return null; // No code in this frame.
            }
        };
    }

    function stop() {
        clearTimeout(timer);
        timer = null;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
    }

    function close() {
        stop();
        if (dialog.open) dialog.close();
        button.focus();
    }

    async function detect() {
        if (!stream) return;
        try {
            const value = (await read())?.trim();
            if (value) {
                status.textContent = `Found ${value}. Opening the parcel…`;
                stop();
                dialog.close();
                input.value = value;
                form.requestSubmit();
                return;
            }
        } catch (error) {
            // The video may not have a frame yet; try again on the next tick.
        }
        timer = setTimeout(detect, 200);
    }

    button.addEventListener('click', async () => {
        status.textContent = 'Point the camera at the barcode or QR code on the waybill.';
        dialog.showModal();
        try {
            read ??= (await nativeReader()) ?? (await zxingReader());
        } catch (error) {
            status.textContent = 'The barcode reader could not load. Check your connection, or type the tracking number.';
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            if (!dialog.open) {
                stop();
                return;
            }
            video.srcObject = stream;
            await video.play();
            detect();
        } catch (error) {
            stop();
            status.textContent = error?.name === 'NotAllowedError'
                ? 'Camera access was blocked. Allow the camera for this site in your browser settings, or type the tracking number.'
                : 'The camera could not start. Type the tracking number instead.';
        }
    });

    dialog.querySelector('[data-camera-close]').addEventListener('click', close);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });
    window.addEventListener('pagehide', stop);
})();
