import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { sharing } from '../api/client';
import { MapContainer, TileLayer } from 'react-leaflet';
import MemberMarker from '../components/map/MemberMarker';
import { MapPin, Loader, AlertCircle } from 'lucide-react';

export default function SharedViewPage() {
  const { token } = useParams();
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchSharedLocation = async () => {
      try {
        const { data: result } = await sharing.view(token);
        setData(result);
      } catch (err) {
        setError(err.response?.data?.message || 'This share link is invalid or has expired.');
      } finally {
        setLoading(false);
      }
    };

    fetchSharedLocation();
    // Auto-refresh every 15 seconds
    const interval = setInterval(fetchSharedLocation, 15000);
    return () => clearInterval(interval);
  }, [token]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <Loader className="w-8 h-8 animate-spin text-primary-600" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div className="text-center">
          <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-3" />
          <h1 className="text-xl font-bold text-gray-900 mb-2">Link Not Found</h1>
          <p className="text-gray-600 mb-4">{error}</p>
          <Link to="/" className="text-primary-600 hover:text-primary-700 font-medium">
            Go to Family Tracker
          </Link>
        </div>
      </div>
    );
  }

  const membersWithLocation = (data?.members || []).filter(m => m.location);
  const center = membersWithLocation.length > 0
    ? [membersWithLocation[0].location.latitude, membersWithLocation[0].location.longitude]
    : [39.8283, -98.5795];

  return (
    <div className="h-screen flex flex-col">
      {/* Header */}
      <header className="bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3">
        <div className="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
          <MapPin className="w-5 h-5 text-white" />
        </div>
        <div>
          <p className="font-semibold text-gray-900">
            Shared Location
          </p>
          <p className="text-xs text-gray-500">
            {membersWithLocation.length} {membersWithLocation.length === 1 ? 'person' : 'people'} sharing
          </p>
        </div>
        <Link to="/register" className="ml-auto text-sm text-primary-600 hover:text-primary-700 font-medium">
          Get Family Tracker
        </Link>
      </header>

      {/* Map */}
      <div className="flex-1">
        <MapContainer center={center} zoom={membersWithLocation.length > 0 ? 14 : 4} className="w-full h-full">
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          {(data?.members || []).map((m, i) =>
            m.location ? (
              <MemberMarker
                key={i}
                member={{ display_name: m.display_name, avatar_color: m.avatar_color }}
                location={m.location}
              />
            ) : null
          )}
        </MapContainer>
      </div>
    </div>
  );
}
