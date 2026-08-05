import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { gps } from '../api/client';
import { useSubscription } from '../context/SubscriptionContext';
import MapContainer from '../components/map/MapContainer';
import UpgradePrompt from '../components/common/UpgradePrompt';
import { MapContainer as LeafletMap, TileLayer, Polyline, CircleMarker, Popup } from 'react-leaflet';
import { format, subDays, subHours } from 'date-fns';
import { Clock, Calendar } from 'lucide-react';

export default function HistoryPage() {
  const { id } = useParams();
  const { plan, canUse } = useSubscription();
  const [member, setMember] = useState(null);
  const [locations, setLocations] = useState([]);
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const from = plan === 'premium'
      ? format(subDays(new Date(), 1), "yyyy-MM-dd'T'HH:mm")
      : format(subHours(new Date(), 24), "yyyy-MM-dd'T'HH:mm");
    const to = format(new Date(), "yyyy-MM-dd'T'HH:mm");

    setDateFrom(from);
    setDateTo(to);
    fetchHistory(from, to);
  }, [id]);

  const fetchHistory = async (from, to) => {
    setLoading(true);
    try {
      const { data } = await gps.getMemberHistory(id, from, to);
      setMember(data.member);
      setLocations(data.locations || []);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (e) => {
    e.preventDefault();
    fetchHistory(dateFrom, dateTo);
  };

  const polylinePositions = locations.map(l => [l.latitude, l.longitude]);
  const center = locations.length > 0
    ? [locations[locations.length - 1].latitude, locations[locations.length - 1].longitude]
    : [39.8283, -98.5795];

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 px-4 py-3">
        <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3">
          <div className="flex items-center gap-3">
            {member && (
              <div
                className="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm"
                style={{ backgroundColor: member.avatar_color }}
              >
                {member.display_name?.charAt(0)?.toUpperCase()}
              </div>
            )}
            <div>
              <h1 className="text-lg font-semibold text-gray-900">
                {member?.display_name || 'Member'} - History
              </h1>
              <p className="text-xs text-gray-500">{locations.length} locations</p>
            </div>
          </div>

          <form onSubmit={handleSearch} className="flex items-center gap-2 sm:ml-auto">
            <input
              type="datetime-local"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="px-2 py-1.5 border border-gray-300 rounded text-xs"
            />
            <span className="text-gray-400 text-xs">to</span>
            <input
              type="datetime-local"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="px-2 py-1.5 border border-gray-300 rounded text-xs"
            />
            <button
              type="submit"
              className="bg-primary-600 text-white px-3 py-1.5 rounded text-xs font-medium hover:bg-primary-700"
            >
              Search
            </button>
          </form>
        </div>
      </div>

      {/* Map */}
      <div className="flex-1">
        {plan === 'free' && !canUse('location_history') ? (
          <div className="p-6">
            <UpgradePrompt feature="location_history" />
            <p className="text-sm text-gray-500 mt-3 text-center">
              Free tier includes only the last 24 hours of history.
            </p>
          </div>
        ) : null}

        <LeafletMap center={center} zoom={locations.length > 0 ? 14 : 4} className="w-full h-full">
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />

          {/* Route polyline */}
          {polylinePositions.length > 1 && (
            <Polyline
              positions={polylinePositions}
              pathOptions={{ color: member?.avatar_color || '#3B82F6', weight: 3, opacity: 0.7 }}
            />
          )}

          {/* Location points */}
          {locations.map((loc, i) => (
            <CircleMarker
              key={i}
              center={[loc.latitude, loc.longitude]}
              radius={i === locations.length - 1 ? 6 : 3}
              pathOptions={{
                color: member?.avatar_color || '#3B82F6',
                fillColor: member?.avatar_color || '#3B82F6',
                fillOpacity: i === locations.length - 1 ? 1 : 0.5,
              }}
            >
              <Popup>
                <div className="text-xs">
                  <p className="font-medium">{format(new Date(loc.recorded_at), 'MMM d, h:mm a')}</p>
                  {loc.speed != null && <p>Speed: {Math.round(loc.speed * 3.6)} km/h</p>}
                  {loc.battery_level != null && <p>Battery: {loc.battery_level}%</p>}
                </div>
              </Popup>
            </CircleMarker>
          ))}
        </LeafletMap>
      </div>
    </div>
  );
}
