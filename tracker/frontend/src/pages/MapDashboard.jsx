import { useState, useEffect } from 'react';
import { useLocations } from '../context/LocationContext';
import { useAuth } from '../context/AuthContext';
import { geofences as geofencesApi } from '../api/client';
import MapContainer from '../components/map/MapContainer';
import MemberCard from '../components/map/MemberCard';
import { RefreshCw, ChevronDown, ChevronUp } from 'lucide-react';

export default function MapDashboard() {
  const { familyLocations, fetchLocations, updateOwnLocation, loading } = useLocations();
  const { family } = useAuth();
  const [geofences, setGeofences] = useState([]);
  const [panelOpen, setPanelOpen] = useState(true);
  const [updating, setUpdating] = useState(false);

  useEffect(() => {
    geofencesApi.list().then(({ data }) => setGeofences(data)).catch(() => {});
  }, []);

  const handleUpdateLocation = async () => {
    setUpdating(true);
    try {
      await updateOwnLocation();
      await fetchLocations();
    } catch {
      // ignore
    } finally {
      setUpdating(false);
    }
  };

  return (
    <div className="flex flex-col lg:flex-row h-full">
      {/* Map */}
      <div className="flex-1 relative">
        <MapContainer members={familyLocations} geofences={geofences} />

        {/* Update location button */}
        <button
          onClick={handleUpdateLocation}
          disabled={updating}
          className="absolute top-3 right-3 z-[1000] bg-white shadow-md rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-2 disabled:opacity-50"
        >
          <RefreshCw className={`w-4 h-4 ${updating ? 'animate-spin' : ''}`} />
          {updating ? 'Updating...' : 'Update My Location'}
        </button>
      </div>

      {/* Side panel - member list */}
      <div className={`bg-white border-t lg:border-t-0 lg:border-l border-gray-200 lg:w-80 flex-shrink-0 ${
        panelOpen ? '' : 'lg:w-auto'
      }`}>
        {/* Toggle */}
        <button
          onClick={() => setPanelOpen(!panelOpen)}
          className="w-full flex items-center justify-between px-4 py-3 border-b border-gray-200 hover:bg-gray-50 lg:hidden"
        >
          <span className="text-sm font-semibold text-gray-900">
            {family?.name || 'Family'} ({familyLocations.length} members)
          </span>
          {panelOpen ? <ChevronDown className="w-4 h-4" /> : <ChevronUp className="w-4 h-4" />}
        </button>

        <div className={`${panelOpen ? '' : 'hidden lg:block'}`}>
          <div className="hidden lg:block px-4 py-3 border-b border-gray-200">
            <h2 className="text-sm font-semibold text-gray-900">{family?.name || 'Family'}</h2>
            <p className="text-xs text-gray-500">{familyLocations.length} members</p>
          </div>

          <div className="p-3 space-y-2 max-h-[50vh] lg:max-h-none overflow-y-auto">
            {familyLocations.length === 0 ? (
              <p className="text-sm text-gray-500 text-center py-8">
                No location data yet. Tap "Update My Location" to share yours.
              </p>
            ) : (
              familyLocations.map(({ member, location }) => (
                <MemberCard key={member.id} member={member} location={location} />
              ))
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
