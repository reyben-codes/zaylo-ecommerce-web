/**
 * "Use camera" on the logistics Scan parcel page: reads the waybill's barcode or QR code with the phone camera
 * and looks the parcel up, so pickup staff need no separate scanner. Uses the browser's BarcodeDetector, so the
 * button only appears where it is supported (Chrome on Android, for example); typing or a USB/Bluetooth scanner
 * still works everywhere.
 */
(function () {
    const button = document.querySelector('[data-camera-scan]');
    const dialog = document.querySelector('[data-camera-dialog]');
    const form = document.querySelector('[data-parcel-scan-form]');
    if (!button || !dialog || !form || !('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) return;

    const video = dialog.querySelector('[data-camera-video]');
    const status = dialog.querySelector('[data-camera-status]');
    const input = form.querySelector('input[name="tracking"]');
    // Waybills carry a Code 128 barcode and a QR code; both hold the tracking number.
    const WANTED = ['qr_code', 'code_128'];
    let stream = null;
    let timer = null;
    let detector = null;

    BarcodeDetector.getSupportedFormats().then((formats) => {
        const usable = WANTED.filter((format) => formats.includes(format));
        if (!usable.length) return;
        detector = new BarcodeDetector({ formats: usable });
        button.hidden = false;
    }).catch(() => {});

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
            const [code] = await detector.detect(video);
            const value = code?.rawValue?.trim();
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
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
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
