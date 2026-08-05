import { useNavigate } from 'react-router-dom';
import { formatDistanceToNow } from 'date-fns';
import { Clock, Navigation, Battery, History } from 'lucide-react';

export default function MemberCard({ member, location }) {
  const navigate = useNavigate();

  const timeAgo = location?.recorded_at
    ? formatDistanceToNow(new Date(location.recorded_at), { addSuffix: true })
    : null;

  const isOnline = location?.recorded_at &&
    (Date.now() - new Date(location.recorded_at).getTime()) < 5 * 60 * 1000;

  return (
    <div className="bg-white rounded-lg border border-gray-200 p-3 hover:border-gray-300 transition-colors">
      <div className="flex items-center gap-3">
        {/* Avatar */}
        <div
          className="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm flex-shrink-0 relative"
          style={{ backgroundColor: member.avatar_color }}
        >
          {member.display_name?.charAt(0)?.toUpperCase() || '?'}
          <span className={`absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white ${
            isOnline ? 'bg-green-500' : 'bg-gray-400'
          }`} />
        </div>

        {/* Info */}
        <div className="flex-1 min-w-0">
          <p className="font-medium text-gray-900 text-sm truncate">{member.display_name}</p>
          <div className="flex items-center gap-3 mt-0.5">
            {timeAgo && (
              <span className="flex items-center gap-1 text-xs text-gray-500">
                <Clock className="w-3 h-3" />
                {timeAgo}
              </span>
            )}
            {location?.speed != null && location.speed > 0 && (
              <span className="flex items-center gap-1 text-xs text-gray-500">
                <Navigation className="w-3 h-3" />
                {Math.round(location.speed * 3.6)} km/h
              </span>
            )}
            {location?.battery_level != null && (
              <span className={`flex items-center gap-1 text-xs ${
                location.battery_level < 20 ? 'text-red-500' : 'text-gray-500'
              }`}>
                <Battery className="w-3 h-3" />
                {location.battery_level}%
              </span>
            )}
          </div>
        </div>

        {/* History link */}
        <button
          onClick={() => navigate(`/history/${member.id}`)}
          className="text-gray-400 hover:text-primary-600 transition-colors p-1"
          title="View history"
        >
          <History className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}
