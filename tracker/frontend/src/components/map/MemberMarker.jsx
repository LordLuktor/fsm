import { Marker, Popup } from 'react-leaflet';
import L from 'leaflet';
import { formatDistanceToNow } from 'date-fns';

function sanitizeColor(color) {
  return (color || '').replace(/[^a-zA-Z0-9#(),.\s]/g, '');
}

function createMemberIcon(name, color) {
  const initial = (name || '?').charAt(0).toUpperCase();
  const safeColor = sanitizeColor(color);
  return L.divIcon({
    className: '',
    html: `<div class="member-marker" style="background:${safeColor};width:36px;height:36px">${initial}</div>`,
    iconSize: [36, 36],
    iconAnchor: [18, 18],
    popupAnchor: [0, -20],
  });
}

export default function MemberMarker({ member, location, onClick }) {
  if (!location) return null;

  const icon = createMemberIcon(member.display_name, member.avatar_color);
  const timeAgo = location.recorded_at
    ? formatDistanceToNow(new Date(location.recorded_at), { addSuffix: true })
    : 'unknown';

  return (
    <Marker
      position={[location.latitude, location.longitude]}
      icon={icon}
      eventHandlers={{ click: onClick }}
    >
      <Popup>
        <div className="text-sm">
          <p className="font-semibold" style={{ color: member.avatar_color }}>
            {member.display_name}
          </p>
          <p className="text-gray-500 text-xs mt-1">Updated {timeAgo}</p>
          {location.speed != null && location.speed > 0 && (
            <p className="text-gray-500 text-xs">
              {Math.round(location.speed * 3.6)} km/h
            </p>
          )}
          {location.battery_level != null && (
            <p className="text-gray-500 text-xs">
              Battery: {location.battery_level}%
            </p>
          )}
        </div>
      </Popup>
    </Marker>
  );
}
