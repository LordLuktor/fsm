import { useState, useEffect, useRef } from 'react';
import { Html5Qrcode } from 'html5-qrcode';
import { Camera, X, ScanLine, AlertCircle } from 'lucide-react';
import Modal from './Modal';

export default function BarcodeScanner({ isOpen, onClose, onScan }) {
  const [error, setError] = useState(null);
  const [cameraReady, setCameraReady] = useState(false);
  const scannerRef = useRef(null);
  const containerRef = useRef(null);

  useEffect(() => {
    if (!isOpen) return;

    let scanner = null;

    const startScanner = async () => {
      setError(null);
      setCameraReady(false);

      try {
        scanner = new Html5Qrcode('barcode-reader');
        scannerRef.current = scanner;

        await scanner.start(
          { facingMode: 'environment' },
          {
            fps: 10,
            qrbox: { width: 280, height: 160 },
            aspectRatio: 1.5,
          },
          (decodedText) => {
            // Barcode detected
            onScan(decodedText);
            stopScanner(scanner);
            onClose();
          },
          () => {
            // Scan frame - no match yet (ignore)
          }
        );

        setCameraReady(true);
      } catch (err) {
        console.error('Scanner error:', err);
        if (typeof err === 'string' && err.includes('Permission')) {
          setError('Camera permission denied. Please allow camera access in your browser settings.');
        } else if (typeof err === 'string' && err.includes('NotFound')) {
          setError('No camera found on this device.');
        } else {
          setError('Could not start camera. Please check permissions and try again.');
        }
      }
    };

    // Small delay to let the modal DOM render
    const timeout = setTimeout(startScanner, 300);

    return () => {
      clearTimeout(timeout);
      if (scanner) {
        stopScanner(scanner);
      }
    };
  }, [isOpen, onScan, onClose]);

  const stopScanner = async (scanner) => {
    try {
      const state = scanner.getState();
      if (state === 2) { // SCANNING
        await scanner.stop();
      }
    } catch {
      // ignore stop errors
    }
  };

  const handleClose = async () => {
    if (scannerRef.current) {
      await stopScanner(scannerRef.current);
      scannerRef.current = null;
    }
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title="Scan Barcode" size="md">
      <div className="space-y-4">
        {error ? (
          <div className="flex flex-col items-center py-8 text-center">
            <AlertCircle className="h-12 w-12 text-red-400 mb-3" />
            <p className="text-red-600 text-sm">{error}</p>
            <button
              onClick={handleClose}
              className="mt-4 btn btn-secondary"
            >
              Close
            </button>
          </div>
        ) : (
          <>
            {!cameraReady && (
              <div className="flex flex-col items-center py-12 text-center">
                <Camera className="h-10 w-10 text-gray-400 mb-3 animate-pulse" />
                <p className="text-gray-500 text-sm">Starting camera...</p>
              </div>
            )}
            <div
              id="barcode-reader"
              ref={containerRef}
              className="rounded-lg overflow-hidden"
              style={{ display: cameraReady ? 'block' : 'none' }}
            />
            <p className="text-center text-sm text-gray-500">
              Point your camera at a barcode or QR code
            </p>
          </>
        )}

        {/* Manual entry fallback */}
        <div className="border-t border-gray-100 pt-4">
          <p className="text-xs text-gray-400 text-center mb-2">
            Or enter SKU manually
          </p>
          <form
            onSubmit={(e) => {
              e.preventDefault();
              const val = e.target.elements.sku.value.trim();
              if (val) {
                onScan(val);
                handleClose();
              }
            }}
            className="flex gap-2"
          >
            <input
              name="sku"
              type="text"
              placeholder="Enter SKU or barcode..."
              className="flex-1 px-4 py-2 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none text-sm"
            />
            <button type="submit" className="btn btn-primary text-sm">
              Look Up
            </button>
          </form>
        </div>
      </div>
    </Modal>
  );
}
