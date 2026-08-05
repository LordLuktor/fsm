import { useState } from 'react';
import { CheckCircle, Camera, PenTool, FileText, ArrowRight, Loader2 } from 'lucide-react';
import { workOrders } from '../../api/client';
import PhotoCapture from '../common/PhotoCapture';
import SignaturePad from '../common/SignaturePad';
import Modal from '../common/Modal';

export default function CompletionFlow({ isOpen, onClose, orderId, onComplete }) {
  const [step, setStep] = useState(0); // 0: photos, 1: signature, 2: notes, 3: confirm
  const [photos, setPhotos] = useState([]);
  const [signature, setSignature] = useState(null);
  const [completionNotes, setCompletionNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const steps = [
    { icon: Camera, label: 'Photos' },
    { icon: PenTool, label: 'Signature' },
    { icon: FileText, label: 'Notes' },
    { icon: CheckCircle, label: 'Confirm' },
  ];

  const handleAddPhoto = (photo) => {
    setPhotos((prev) => [...prev, photo]);
  };

  const handleRemovePhoto = (photoId) => {
    setPhotos((prev) => prev.filter((p) => p.id !== photoId));
  };

  const handleComplete = async () => {
    setSubmitting(true);
    setError(null);

    try {
      // Upload any captured photos to the media API.
      // PhotoCapture currently gives us dataURLs — convert each to a File
      // and upload via the media endpoint. Failures are non-fatal so
      // completion still succeeds even if a photo fails to upload.
      let uploadedCount = 0;
      let uploadFailures = 0;

      for (const photo of photos) {
        if (!photo?.url) continue;
        try {
          const file = await dataURLtoFile(photo.url, `completion-${photo.id || Date.now()}.jpg`);
          await workOrders.uploadMedia(orderId, file);
          uploadedCount++;
        } catch (uploadErr) {
          console.error('Photo upload failed:', uploadErr);
          uploadFailures++;
        }
      }

      // Update status to completed
      await workOrders.updateStatus(orderId, 'completed');

      // Add completion note with details
      const noteContent = [
        'Work order completed.',
        completionNotes && `Notes: ${completionNotes}`,
        uploadedCount > 0 && `${uploadedCount} photo(s) uploaded.`,
        uploadFailures > 0 && `${uploadFailures} photo(s) failed to upload (kept locally).`,
        signature && 'Customer signature collected.',
      ]
        .filter(Boolean)
        .join('\n');

      await workOrders.addNote(orderId, noteContent, 'status_change');

      // Keep signature in WO meta (future: dedicated endpoint).
      // For now, if any uploads failed, still save to localStorage as a backup.
      if (uploadFailures > 0 || signature) {
        const fallbackData = {
          photos: uploadFailures > 0 ? photos.map((p) => ({ id: p.id, url: p.url, timestamp: p.timestamp })) : [],
          signature,
          notes: completionNotes,
          completedAt: new Date().toISOString(),
        };
        localStorage.setItem(`fsm_completion_${orderId}`, JSON.stringify(fallbackData));
      }

      onComplete?.();
      onClose();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to complete work order');
    } finally {
      setSubmitting(false);
    }
  };

  // Helper: convert a data URL to a File object for upload
  const dataURLtoFile = async (dataUrl, filename) => {
    const res = await fetch(dataUrl);
    const blob = await res.blob();
    return new File([blob], filename, { type: blob.type || 'image/jpeg' });
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Complete Work Order" size="lg">
      {/* Step indicator */}
      <div className="flex items-center justify-between mb-8 px-2">
        {steps.map((s, i) => (
          <div key={s.label} className="flex items-center">
            <button
              onClick={() => setStep(i)}
              className={`flex flex-col items-center gap-1 transition-colors ${
                i === step
                  ? 'text-primary-600'
                  : i < step
                  ? 'text-green-500'
                  : 'text-gray-300'
              }`}
            >
              <div
                className={`w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all ${
                  i === step
                    ? 'border-primary-600 bg-primary-50'
                    : i < step
                    ? 'border-green-500 bg-green-50'
                    : 'border-gray-200 bg-white'
                }`}
              >
                {i < step ? (
                  <CheckCircle className="h-5 w-5" />
                ) : (
                  <s.icon className="h-5 w-5" />
                )}
              </div>
              <span className="text-xs font-medium">{s.label}</span>
            </button>
            {i < steps.length - 1 && (
              <div className={`w-12 sm:w-20 h-0.5 mx-1 ${i < step ? 'bg-green-500' : 'bg-gray-200'}`} />
            )}
          </div>
        ))}
      </div>

      {error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          {error}
        </div>
      )}

      {/* Step content */}
      <div className="min-h-[300px]">
        {step === 0 && (
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Take photos of the completed work for documentation.
            </p>
            <PhotoCapture
              photos={photos}
              onAdd={handleAddPhoto}
              onRemove={handleRemovePhoto}
              maxPhotos={10}
              label="Work Photos (optional)"
            />
          </div>
        )}

        {step === 1 && (
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Have the customer sign to acknowledge work completion.
            </p>
            {signature ? (
              <div className="space-y-3">
                <div className="border-2 border-green-200 rounded-lg p-4 bg-green-50 text-center">
                  <CheckCircle className="h-8 w-8 text-green-500 mx-auto mb-2" />
                  <p className="text-sm font-medium text-green-700">Signature captured</p>
                  <img
                    src={signature}
                    alt="Customer signature"
                    className="mt-3 mx-auto max-h-24 border border-gray-200 rounded bg-white p-2"
                  />
                </div>
                <button
                  type="button"
                  onClick={() => setSignature(null)}
                  className="text-sm text-gray-500 hover:text-gray-700"
                >
                  Recapture signature
                </button>
              </div>
            ) : (
              <SignaturePad
                onSave={(dataUrl) => setSignature(dataUrl)}
                onClear={() => setSignature(null)}
                height={180}
              />
            )}
            <p className="text-xs text-gray-400">Signature is optional but recommended.</p>
          </div>
        )}

        {step === 2 && (
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Add any notes about the completed work.
            </p>
            <textarea
              value={completionNotes}
              onChange={(e) => setCompletionNotes(e.target.value)}
              placeholder="Describe the work performed, any issues found, follow-up needed..."
              rows={6}
              className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none resize-none"
            />
          </div>
        )}

        {step === 3 && (
          <div className="space-y-4">
            <div className="bg-gray-50 rounded-lg p-6 space-y-4">
              <h3 className="font-semibold text-gray-900">Completion Summary</h3>

              <div className="space-y-3 text-sm">
                <div className="flex items-center justify-between">
                  <span className="text-gray-500">Photos</span>
                  <span className="font-medium">
                    {photos.length > 0 ? `${photos.length} captured` : 'None'}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-gray-500">Customer Signature</span>
                  <span className={`font-medium ${signature ? 'text-green-600' : 'text-gray-400'}`}>
                    {signature ? 'Collected' : 'Skipped'}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-gray-500">Completion Notes</span>
                  <span className="font-medium">
                    {completionNotes ? 'Added' : 'None'}
                  </span>
                </div>
              </div>
            </div>

            <p className="text-sm text-gray-500">
              This will mark the work order as <strong>Completed</strong>. This action cannot be undone.
            </p>
          </div>
        )}
      </div>

      {/* Navigation */}
      <div className="flex items-center justify-between pt-6 mt-6 border-t border-gray-100">
        <button
          type="button"
          onClick={step === 0 ? onClose : () => setStep(step - 1)}
          className="px-4 py-2 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
        >
          {step === 0 ? 'Cancel' : 'Back'}
        </button>

        {step < 3 ? (
          <button
            type="button"
            onClick={() => setStep(step + 1)}
            className="inline-flex items-center gap-2 px-5 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 transition-colors"
          >
            Next <ArrowRight className="h-4 w-4" />
          </button>
        ) : (
          <button
            type="button"
            onClick={handleComplete}
            disabled={submitting}
            className="inline-flex items-center gap-2 px-5 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Completing...
              </>
            ) : (
              <>
                <CheckCircle className="h-4 w-4" />
                Complete Work Order
              </>
            )}
          </button>
        )}
      </div>
    </Modal>
  );
}
