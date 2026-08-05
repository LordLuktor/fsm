import { MapContainer as LeafletMap, TileLayer, Circle } from 'react-leaflet';
import MemberMarker from './MemberMarker';

const DEFAULT_CENTER = [39.8283, -98.5795]; // Center of US
const DEFAULT_ZOOM = 4;

export default function MapContainer({ members = [], geofences = [], places = [], center, zoom, children }) {
  // Calculate bounds if members have locations
  const membersWithLocation = members.filter(m => m.location);
  const mapCenter = center || (
    membersWithLocation.length > 0
      ? [membersWithLocation[0].location.latitude, membersWithLocation[0].location.longitude]
      : DEFAULT_CENTER
  );
  const mapZoom = zoom || (membersWithLocation.length > 0 ? 14 : DEFAULT_ZOOM);

  return (
    <LeafletMap
      center={mapCenter}
      zoom={mapZoom}
      className="w-full h-full rounded-lg"
      zoomControl={true}
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />

      {/* Member markers */}
      {members.map(({ member, location }) =>
        location ? (
          <MemberMarker key={member.id} member={member} location={location} />
        ) : null
      )}

      {/* Geofence circles */}
      {geofences.map((g) => (
        <Circle
          key={g.id}
          center={[g.latitude, g.longitude]}
          radius={g.radius_meters}
          pathOptions={{
            color: g.type === 'safe_zone' ? '#10B981' : '#EF4444',
            fillColor: g.type === 'safe_zone' ? '#10B981' : '#EF4444',
            fillOpacity: 0.1,
            weight: 2,
          }}
        />
      ))}

      {children}
    </LeafletMap>
  );
}
