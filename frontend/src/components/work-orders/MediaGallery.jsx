import { useState, useEffect, useRef } from 'react';
import { Camera, Video, Upload, Trash2, AlertCircle, CheckCircle, Loader2, X } from 'lucide-react';
import { workOrders } from '../../api/client';

/**
 * MediaGallery — optional photo/video attachments for a work order.
 *
 * Media is NOT required to complete a work order. When attached, it
 * enables downstream content production via CutBot (social media,
 * QA verification, marketing reels).
 */
export default function MediaGallery({ workOrderId, canEdit = true }) {
  const [media, setMedia] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [error, setError] = useState(null);
  const [lightbox, setLightbox] = useState(null);
  const fileInputRef = useRef(null);

  const loadMedia = async () => {
    try {
      const res = await workOrders.listMedia(workOrderId);
      setMedia(res.data || []);
    } catch (err) {
      console.error('Failed to load media:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (workOrderId) loadMedia();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [workOrderId]);

  const handleFileSelect = async (e) => {
    const files = Array.from(e.target.files);
    if (files.length === 0) return;

    setUploading(true);
    setUploadProgress(0);
    setError(null);

    try {
      // Upload files one at a time so we can track progress per file
      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        await workOrders.uploadMedia(workOrderId, file, (progressEvent) => {
          if (progressEvent.total) {
            const fileProgress = (progressEvent.loaded / progressEvent.total) * 100;
            const totalProgress = ((i + fileProgress / 100) / files.length) * 100;
            setUploadProgress(Math.round(totalProgress));
          }
        });
      }

      await loadMedia();
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Upload failed';
      setError(msg);
    } finally {
      setUploading(false);
      setUploadProgress(0);
      if (fileInputRef.current) fileInputRef.current.value = '';
    }
  };

  const handleDelete = async (mediaId) => {
    if (!confirm('Delete this media? This cannot be undone.')) return;

    try {
      await workOrders.deleteMedia(workOrderId, mediaId);
      setMedia((prev) => prev.filter((m) => m.id !== mediaId));
    } catch (err) {
      alert('Failed to delete: ' + (err.response?.data?.message || err.message));
    }
  };

  const formatSize = (bytes) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
  };

  const photoCount = media.filter((m) => m.type === 'photo').length;
  const videoCount = media.filter((m) => m.type === 'video').length;

  return (
    <div className="bg-white rounded-lg shadow p-6">
      <div className="flex items-center justify-between mb-4">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">Media Attachments</h3>
          <p className="text-xs text-gray-500 mt-0.5">
            Optional — photos and videos of the job for records and content production
          </p>
        </div>
        {canEdit && (
          <button
            onClick={() => fileInputRef.current?.click()}
            disabled={uploading}
            className="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            {uploading ? (
              <>
                <Loader2 className="w-4 h-4 mr-1.5 animate-spin" />
                {uploadProgress}%
              </>
            ) : (
              <>
                <Upload className="w-4 h-4 mr-1.5" />
                Upload
              </>
            )}
          </button>
        )}
        <input
          ref={fileInputRef}
          type="file"
          multiple
          accept="image/jpeg,image/png,image/webp,image/heic,image/heif,video/mp4,video/quicktime,video/webm,video/x-matroska"
          onChange={handleFileSelect}
          className="hidden"
        />
      </div>

      {/* Upload progress bar */}
      {uploading && (
        <div className="mb-4">
          <div className="w-full bg-gray-200 rounded-full h-1.5">
            <div
              className="bg-blue-600 h-1.5 rounded-full transition-all"
              style={{ width: `${uploadProgress}%` }}
            />
          </div>
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="mb-4 flex items-start gap-2 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
          <div className="flex-1">{error}</div>
          <button onClick={() => setError(null)}>
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Summary */}
      {!loading && media.length > 0 && (
        <div className="mb-3 flex items-center gap-4 text-xs text-gray-600">
          {photoCount > 0 && (
            <span className="flex items-center gap-1">
              <Camera className="w-3 h-3" />
              {photoCount} photo{photoCount !== 1 ? 's' : ''}
            </span>
          )}
          {videoCount > 0 && (
            <span className="flex items-center gap-1">
              <Video className="w-3 h-3" />
              {videoCount} video{videoCount !== 1 ? 's' : ''}
            </span>
          )}
        </div>
      )}

      {/* Empty state */}
      {loading ? (
        <div className="py-8 text-center text-sm text-gray-500">Loading media...</div>
      ) : media.length === 0 ? (
        <div className="py-8 text-center">
          <Camera className="w-10 h-10 mx-auto text-gray-300" />
          <p className="mt-2 text-sm text-gray-500">No media attached</p>
          {canEdit && (
            <p className="mt-1 text-xs text-gray-400">
              Click Upload to add photos or videos
            </p>
          )}
        </div>
      ) : (
        /* Media grid */
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
          {media.map((item) => (
            <div key={item.id} className="relative group rounded overflow-hidden border border-gray-200">
              <button
                onClick={() => setLightbox(item)}
                className="block w-full aspect-square bg-gray-100"
              >
                {item.type === 'photo' ? (
                  <img
                    src={item.url}
                    alt={item.filename}
                    className="w-full h-full object-cover"
                    loading="lazy"
                  />
                ) : (
                  <div className="w-full h-full flex flex-col items-center justify-center bg-gray-900 text-white">
                    <Video className="w-8 h-8 mb-1" />
                    <span className="text-[10px] opacity-70">Video</span>
                  </div>
                )}
              </button>

              {/* Overlay with filename + delete */}
              <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-1.5 pt-4">
                <p className="text-[10px] text-white truncate">
                  {item.filename}
                </p>
                <p className="text-[9px] text-white/70">
                  {formatSize(item.size)}
                </p>
              </div>

              {canEdit && (
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    handleDelete(item.id);
                  }}
                  className="absolute top-1 right-1 p-1 bg-red-600/80 text-white rounded opacity-0 group-hover:opacity-100 transition-opacity"
                  title="Delete"
                >
                  <Trash2 className="w-3 h-3" />
                </button>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Lightbox modal */}
      {lightbox && (
        <div
          className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
          onClick={() => setLightbox(null)}
        >
          <button
            className="absolute top-4 right-4 text-white/80 hover:text-white"
            onClick={() => setLightbox(null)}
          >
            <X className="w-6 h-6" />
          </button>
          <div
            className="max-w-full max-h-full"
            onClick={(e) => e.stopPropagation()}
          >
            {lightbox.type === 'photo' ? (
              <img
                src={lightbox.url}
                alt={lightbox.filename}
                className="max-w-full max-h-[90vh] object-contain"
              />
            ) : (
              <video
                src={lightbox.url}
                controls
                autoPlay
                className="max-w-full max-h-[90vh]"
              />
            )}
            <p className="mt-2 text-center text-xs text-white/80">
              {lightbox.filename} &middot; {formatSize(lightbox.size)}
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
