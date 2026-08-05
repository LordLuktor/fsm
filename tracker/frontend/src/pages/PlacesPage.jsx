import { useState, useEffect, useCallback } from 'react';
import { places as placesApi } from '../api/client';
import Modal from '../components/common/Modal';
import { MapContainer, TileLayer, Marker, Popup, useMapEvents } from 'react-leaflet';
import L from 'leaflet';
import { MapPin, Plus, Trash2, Home, Briefcase, GraduationCap, Heart, Coffee } from 'lucide-react';

const placeIcons = {
  pin: MapPin, home: Home, work: Briefcase,
  school: GraduationCap, favorite: Heart, cafe: Coffee,
};

function ClickHandler({ onClick }) {
  useMapEvents({ click: (e) => onClick(e.latlng) });
  return null;
}

export default function PlacesPage() {
  const [placeList, setPlaceList] = useState([]);
  const [showCreate, setShowCreate] = useState(false);
  const [placing, setPlacing] = useState(false);
  const [form, setForm] = useState({ name: '', latitude: '', longitude: '', icon: 'pin', address: '' });
  const [loading, setLoading] = useState(true);

  const fetchPlaces = useCallback(async () => {
    try {
      const { data } = await placesApi.list();
      setPlaceList(data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchPlaces(); }, [fetchPlaces]);

  const handleMapClick = (latlng) => {
    if (placing) {
      setForm({ ...form, latitude: latlng.lat, longitude: latlng.lng });
      setPlacing(false);
      setShowCreate(true);
    }
  };

  const handleCreate = async (e) => {
    e.preventDefault();
    try {
      await placesApi.create({
        ...form,
        latitude: parseFloat(form.latitude),
        longitude: parseFloat(form.longitude),
      });
      setShowCreate(false);
      setForm({ name: '', latitude: '', longitude: '', icon: 'pin', address: '' });
      fetchPlaces();
    } catch { /* ignore */ }
  };

  const handleDelete = async (id) => {
    if (!confirm('Delete this place?')) return;
    try {
      await placesApi.delete(id);
      fetchPlaces();
    } catch { /* ignore */ }
  };

  return (
    <div className="flex flex-col lg:flex-row h-full">
      {/* Map */}
      <div className="flex-1 relative">
        <MapContainer center={[39.8283, -98.5795]} zoom={4} className="w-full h-full">
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          <ClickHandler onClick={handleMapClick} />

          {placeList.map((p) => (
            <Marker
              key={p.id}
              position={[p.latitude, p.longitude]}
              icon={L.divIcon({
                className: '',
                html: `<div style="background:#6366f1;width:28px;height:28px;border-radius:50%;border:2px solid white;box-shadow:0 2px 6px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;color:white;font-size:14px">&#x1F4CD;</div>`,
                iconSize: [28, 28],
                iconAnchor: [14, 14],
              })}
            >
              <Popup>
                <div className="text-sm">
                  <p className="font-semibold">{p.name}</p>
                  {p.address && <p className="text-gray-500 text-xs">{p.address}</p>}
                </div>
              </Popup>
            </Marker>
          ))}
        </MapContainer>

        {placing ? (
          <div className="absolute top-3 left-1/2 -translate-x-1/2 z-[1000] bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-lg">
            Tap the map to place a pin
          </div>
        ) : (
          <button
            onClick={() => setPlacing(true)}
            className="absolute top-3 right-3 z-[1000] bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 flex items-center gap-2 shadow-lg"
          >
            <Plus className="w-4 h-4" /> Add Place
          </button>
        )}
      </div>

      {/* Sidebar */}
      <div className="bg-white border-t lg:border-t-0 lg:border-l border-gray-200 lg:w-72 flex-shrink-0 overflow-y-auto">
        <div className="px-4 py-3 border-b border-gray-200">
          <h2 className="font-semibold text-gray-900 flex items-center gap-2">
            <MapPin className="w-4 h-4" /> Saved Places ({placeList.length})
          </h2>
        </div>

        <div className="p-3 space-y-2">
          {placeList.length === 0 ? (
            <p className="text-sm text-gray-500 text-center py-8">
              No places saved yet. Click "Add Place" to start.
            </p>
          ) : (
            placeList.map((p) => (
              <div key={p.id} className="rounded-lg border border-gray-200 p-3 flex items-center gap-3">
                <div className="w-8 h-8 bg-primary-100 rounded-lg flex items-center justify-center flex-shrink-0">
                  <MapPin className="w-4 h-4 text-primary-600" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-sm text-gray-900 truncate">{p.name}</p>
                  {p.address && <p className="text-xs text-gray-500 truncate">{p.address}</p>}
                </div>
                <button onClick={() => handleDelete(p.id)} className="text-gray-400 hover:text-red-500">
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Create Modal */}
      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Save Place">
        <form onSubmit={handleCreate} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input
              type="text" required value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none"
              placeholder="Home, School, Park..."
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Address (optional)</label>
            <input
              type="text" value={form.address}
              onChange={(e) => setForm({ ...form, address: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm outline-none"
              placeholder="123 Main St"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Icon</label>
            <div className="flex gap-2">
              {Object.entries(placeIcons).map(([key, Icon]) => (
                <button
                  key={key}
                  type="button"
                  onClick={() => setForm({ ...form, icon: key })}
                  className={`w-10 h-10 rounded-lg flex items-center justify-center transition-colors ${
                    form.icon === key ? 'bg-primary-100 text-primary-700 ring-2 ring-primary-500' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                  }`}
                >
                  <Icon className="w-4 h-4" />
                </button>
              ))}
            </div>
          </div>
          <button
            type="submit"
            className="w-full bg-primary-600 text-white py-2.5 rounded-lg font-medium text-sm hover:bg-primary-700"
          >
            Save Place
          </button>
        </form>
      </Modal>
    </div>
  );
}
