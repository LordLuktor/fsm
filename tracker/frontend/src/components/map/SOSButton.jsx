import { useState } from 'react';
import { AlertTriangle } from 'lucide-react';
import { useLocations } from '../../context/LocationContext';

export default function SOSButton() {
  const { triggerSOS, sosActive, cancelSOS } = useLocations();
  const [confirming, setConfirming] = useState(false);

  const handleClick = () => {
    if (sosActive) {
      cancelSOS();
      return;
    }
    if (confirming) {
      triggerSOS('Emergency SOS triggered!');
      setConfirming(false);
    } else {
      setConfirming(true);
      setTimeout(() => setConfirming(false), 3000);
    }
  };

  if (sosActive) {
    return (
      <button
        onClick={handleClick}
        className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-red-600 text-white rounded-lg font-semibold text-sm sos-pulse"
      >
        <AlertTriangle className="w-5 h-5" />
        Cancel SOS
      </button>
    );
  }

  return (
    <button
      onClick={handleClick}
      className={`w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-semibold text-sm transition-all ${
        confirming
          ? 'bg-red-600 text-white'
          : 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200'
      }`}
    >
      <AlertTriangle className="w-5 h-5" />
      {confirming ? 'TAP AGAIN TO CONFIRM' : 'SOS Emergency'}
    </button>
  );
}
