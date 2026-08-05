import { useState, useRef } from 'react';
import { Camera, X, Image as ImageIcon, Plus, Loader2 } from 'lucide-react';

export default function PhotoCapture({ photos = [], onAdd, onRemove, maxPhotos = 10, label = 'Photos', disabled = false }) {
  const [uploading, setUploading] = useState(false);
  const inputRef = useRef(null);

  const handleCapture = async (e) => {
    const files = Array.from(e.target.files || []);
    if (files.length === 0) return;

    setUploading(true);
    try {
      for (const file of files) {
        // Validate file
        if (!file.type.startsWith('image/')) continue;
        if (file.size > 10 * 1024 * 1024) continue; // 10MB max

        // Create local preview URL and base64 for storage
        const dataUrl = await fileToDataUrl(file);
        const photo = {
          id: `photo_${Date.now()}_${Math.random().toString(36).slice(2)}`,
          name: file.name,
          url: dataUrl,
          timestamp: new Date().toISOString(),
          size: file.size,
        };
        onAdd?.(photo);
      }
    } catch (err) {
      console.error('Photo capture failed:', err);
    } finally {
      setUploading(false);
      // Reset input so same file can be selected again
      if (inputRef.current) inputRef.current.value = '';
    }
  };

  const fileToDataUrl = (file) =>
    new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = reject;

      // Resize large images to save storage
      const img = new window.Image();
      img.onload = () => {
        const maxDim = 1920;
        let { width, height } = img;

        if (width > maxDim || height > maxDim) {
          const ratio = Math.min(maxDim / width, maxDim / height);
          width = Math.round(width * ratio);
          height = Math.round(height * ratio);
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, width, height);
        resolve(canvas.toDataURL('image/jpeg', 0.85));
      };
      img.onerror = reject;
      img.src = URL.createObjectURL(file);
    });

  return (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-2">{label}</label>

      <div className="flex flex-wrap gap-3">
        {photos.map((photo) => (
          <div key={photo.id} className="relative group w-20 h-20 rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
            <img src={photo.url} alt="" className="w-full h-full object-cover" />
            {!disabled && (
              <button
                type="button"
                onClick={() => onRemove?.(photo.id)}
                className="absolute top-0.5 right-0.5 p-0.5 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
              >
                <X className="h-3 w-3" />
              </button>
            )}
          </div>
        ))}

        {!disabled && photos.length < maxPhotos && (
          <button
            type="button"
            onClick={() => inputRef.current?.click()}
            disabled={uploading}
            className="w-20 h-20 rounded-lg border-2 border-dashed border-gray-300 hover:border-primary-400 flex flex-col items-center justify-center text-gray-400 hover:text-primary-500 transition-colors"
          >
            {uploading ? (
              <Loader2 className="h-5 w-5 animate-spin" />
            ) : (
              <>
                <Camera className="h-5 w-5 mb-0.5" />
                <span className="text-[10px]">Add</span>
              </>
            )}
          </button>
        )}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        capture="environment"
        multiple
        onChange={handleCapture}
        className="hidden"
      />

      {photos.length > 0 && (
        <p className="text-xs text-gray-500 mt-1">
          {photos.length}/{maxPhotos} photos
        </p>
      )}
    </div>
  );
}
