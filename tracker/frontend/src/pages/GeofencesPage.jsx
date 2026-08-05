import { useState, useEffect, useCallback } from 'react';
import { geofences as geofencesApi } from '../api/client';
import { useSubscription } from '../context/SubscriptionContext';
import UpgradePrompt from '../components/common/UpgradePrompt';
import Modal from '../components/common/Modal';
import { MapContainer, TileLayer, Circle, useMapEvents } from 'react-leaflet';
import { Shield, Plus, Trash2, Edit3, ToggleLeft, ToggleRight } from 'lucide-react';

function ClickHandler({ onClick }) {
  useMapEvents({ click: (e) => onClick(e.latlng) });
  return null;
}

export default function GeofencesPage() {
  const { canUse } = useSubscription();
  const [geofenceList, setGeofenceList] = useState([]);
  const [showCreate, setShowCreate] = useState(false);
  const [placing, setPlacing] = useState(false);
  const [form, setForm] = useState({
    name: '', latitude: '', longitude: '',
    radius_meters: 200, type: 'safe_zone', notify_on: 'both',
  });
  const [loading, setLoading] = useState(true);

  const fetchGeofences = useCallback(async () => {
    try {
      const { data } = await geofencesApi.list();
      setGeofenceList(data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchGeofences(); }, [fetchGeofences]);

  if (!canUse('geofences')) {
    return (
      <div className="p-6 max-w-lg mx-auto">
        <UpgradePrompt feature="geofences" />
      </div>
    );
  }

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
      await geofencesApi.create({
        ...form,
        latitude: parseFloat(form.latitude),
        longitude: parseFloat(form.longitude),
        radius_meters: parseInt(form.radius_meters),
      });
      setShowCreate(false);
      setForm({ name: '', latitude: '', longitude: '', radius_meters: 200, type: 'safe_zone', notify_on: 'both' });
      fetchGeofences();
    } catch { /* ignore */ }
  };

  const handleDelete = async (id) => {
    if (!confirm('Delete this geofence?')) return;
    try {
      await geofencesApi.delete(id);
      fetchGeofences();
    } catch { /* ignore */ }
  };

  const handleToggle = async (g) => {
    try {
      await geofencesApi.update(g.id, { active: !g.active });
      fetchGeofences();
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

          {geofenceList.map((g) => (
            <Circle
              key={g.id}
              center={[g.latitude, g.longitude]}
              radius={g.radius_meters}
              pathOptions={{
                color: g.type === 'safe_zone' ? '#10B981' : '#EF4444',
                fillColor: g.type === 'safe_zone' ? '#10B981' : '#EF4444',
                fillOpacity: g.active ? 0.15 : 0.05,
                weight: g.active ? 2 : 1,
                dashArray: g.active ? '' : '5,5',
              }}
            />
          ))}

          {/* Preview circle */}
          {form.latitude && form.longitude && showCreate && (
            <Circle
              center={[parseFloat(form.latitude), parseFloat(form.longitude)]}
              radius={parseInt(form.radius_meters) || 200}
              pathOptions={{ color: '#6366F1', fillColor: '#6366F1', fillOpacity: 0.1, weight: 2, dashArray: '5,5' }}
            />
          )}
        </MapContainer>

        {/* Place button */}
        {placing ? (
          <div className="absolute top-3 left-1/2 -translate-x-1/2 z-[1000] bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-lg">
            Tap the map to place geofence
          </div>
        ) : (
          <button
            onClick={() => setPlacing(true)}
            className="absolute top-3 right-3 z-[1000] bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 flex items-center gap-2 shadow-lg"
          >
            <Plus className="w-4 h-4" /> New Geofence
          </button>
        )}
      </div>

      {/* Sidebar */}
      <div className="bg-white border-t lg:border-t-0 lg:border-l border-gray-200 lg:w-80 flex-shrink-0 overflow-y-auto">
        <div className="px-4 py-3 border-b border-gray-200">
          <h2 className="font-semibold text-gray-900 flex items-center gap-2">
            <Shield className="w-4 h-4" /> Geofences ({geofenceList.length})
          </h2>
        </div>

        <div className="p-3 space-y-2">
          {geofenceList.length === 0 ? (
            <p className="text-sm text-gray-500 text-center py-8">
              No geofences yet. Click "New Geofence" then tap the map.
            </p>
          ) : (
            geofenceList.map((g) => (
              <div key={g.id} className={`rounded-lg border p-3 ${g.active ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-60'}`}>
                <div className="flex items-center gap-2">
                  <div className={`w-3 h-3 rounded-full ${g.type === 'safe_zone' ? 'bg-green-500' : 'bg-red-500'}`} />
                  <span className="font-medium text-sm text-gray-900 flex-1 truncate">{g.name}</span>
                  <button onClick={() => handleToggle(g)} className="text-gray-400 hover:text-gray-600">
                    {g.active ? <ToggleRight className="w-5 h-5 text-green-500" /> : <ToggleLeft className="w-5 h-5" />}
                  </button>
                  <button onClick={() => handleDelete(g.id)} className="text-gray-400 hover:text-red-500">
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
                <p className="text-xs text-gray-500 mt-1">
                  {g.type === 'safe_zone' ? 'Safe Zone' : 'Alert Zone'} &middot; {g.radius_meters}m &middot; Notify on {g.notify_on}
                </p>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Create Modal */}
      <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Create Geofence">
        <form onSubmit={handleCreate} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input
              type="text" required value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 outline-none"
              placeholder="Home, School, Work..."
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Latitude</label>
              <input
                type="number" step="any" required value={form.latitude}
                onChange={(e) => setForm({ ...form, latitude: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Longitude</label>
              <input
                type="number" step="any" required value={form.longitude}
                onChange={(e) => setForm({ ...form, longitude: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Radius (meters)</label>
            <input
              type="range" min="50" max="2000" step="50" value={form.radius_meters}
              onChange={(e) => setForm({ ...form, radius_meters: e.target.value })}
              className="w-full"
            />
            <p className="text-xs text-gray-500 text-right">{form.radius_meters}m</p>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
              <select
                value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              >
                <option value="safe_zone">Safe Zone</option>
                <option value="alert_zone">Alert Zone</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Notify On</label>
              <select
                value={form.notify_on} onChange={(e) => setForm({ ...form, notify_on: e.target.value })}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
              >
                <option value="both">Enter & Exit</option>
                <option value="enter">Enter Only</option>
                <option value="exit">Exit Only</option>
              </select>
            </div>
          </div>

          <button
            type="submit"
            className="w-full bg-primary-600 text-white py-2.5 rounded-lg font-medium text-sm hover:bg-primary-700"
          >
            Create Geofence
          </button>
        </form>
      </Modal>
    </div>
  );
}
