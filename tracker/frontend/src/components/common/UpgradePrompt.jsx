import { useNavigate } from 'react-router-dom';
import { Sparkles } from 'lucide-react';

export default function UpgradePrompt({ feature }) {
  const navigate = useNavigate();

  const featureNames = {
    geofences: 'Geofences',
    location_history: 'Location History',
    sharing: 'Location Sharing',
    extended_members: 'More Family Members',
    ptt: 'Push-to-Talk',
  };

  const isUltimateFeature = feature === 'ptt';
  const targetTier = isUltimateFeature ? 'Ultimate' : 'Premium';

  return (
    <div className={`bg-gradient-to-br ${
      isUltimateFeature
        ? 'from-violet-50 to-purple-50 border-violet-200'
        : 'from-primary-50 to-accent-50 border-primary-200'
    } border rounded-xl p-6 text-center`}>
      <Sparkles className={`w-10 h-10 ${isUltimateFeature ? 'text-violet-500' : 'text-primary-500'} mx-auto mb-3`} />
      <h3 className="text-lg font-semibold text-gray-900 mb-1">
        {featureNames[feature] || `${targetTier} Feature`}
      </h3>
      <p className="text-sm text-gray-600 mb-4">
        Upgrade to {targetTier} to unlock {(featureNames[feature] || 'this feature').toLowerCase()} and more.
      </p>
      <button
        onClick={() => navigate('/settings')}
        className={`${
          isUltimateFeature
            ? 'bg-violet-600 hover:bg-violet-700'
            : 'bg-primary-600 hover:bg-primary-700'
        } text-white px-6 py-2 rounded-lg text-sm font-medium transition-colors`}
      >
        Upgrade to {targetTier}
      </button>
    </div>
  );
}
