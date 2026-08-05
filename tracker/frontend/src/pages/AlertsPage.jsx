import { useState, useEffect, useCallback } from 'react';
import { alerts as alertsApi } from '../api/client';
import { formatDistanceToNow, format } from 'date-fns';
import {
  Bell, AlertTriangle, Shield, ShieldOff, Battery,
  CheckCheck, Loader,
} from 'lucide-react';

const alertIcons = {
  geofence_enter: Shield,
  geofence_exit: ShieldOff,
  sos: AlertTriangle,
  low_battery: Battery,
};

const alertColors = {
  geofence_enter: 'text-green-600 bg-green-100',
  geofence_exit: 'text-amber-600 bg-amber-100',
  sos: 'text-red-600 bg-red-100',
  low_battery: 'text-orange-600 bg-orange-100',
};

export default function AlertsPage() {
  const [alertList, setAlertList] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(true);

  const fetchAlerts = useCallback(async () => {
    try {
      const { data } = await alertsApi.list(100, 0);
      setAlertList(data.alerts);
      setUnreadCount(data.unread_count);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchAlerts(); }, [fetchAlerts]);

  const handleMarkRead = async (id) => {
    try {
      await alertsApi.markRead(id);
      fetchAlerts();
    } catch { /* ignore */ }
  };

  const handleMarkAllRead = async () => {
    try {
      await alertsApi.markAllRead();
      fetchAlerts();
    } catch { /* ignore */ }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader className="w-6 h-6 animate-spin text-gray-400" />
      </div>
    );
  }

  return (
    <div className="p-4 lg:p-6 max-w-3xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Alerts</h1>
          {unreadCount > 0 && (
            <p className="text-sm text-gray-500 mt-1">{unreadCount} unread</p>
          )}
        </div>
        {unreadCount > 0 && (
          <button
            onClick={handleMarkAllRead}
            className="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center gap-1"
          >
            <CheckCheck className="w-4 h-4" /> Mark all read
          </button>
        )}
      </div>

      {alertList.length === 0 ? (
        <div className="text-center py-16">
          <Bell className="w-12 h-12 text-gray-300 mx-auto mb-3" />
          <p className="text-gray-500">No alerts yet</p>
          <p className="text-sm text-gray-400 mt-1">Alerts from geofences and SOS will appear here.</p>
        </div>
      ) : (
        <div className="space-y-2">
          {alertList.map((alert) => {
            const Icon = alertIcons[alert.type] || Bell;
            const colors = alertColors[alert.type] || 'text-gray-600 bg-gray-100';

            return (
              <div
                key={alert.id}
                className={`rounded-lg border p-4 flex items-start gap-3 transition-colors ${
                  alert.read ? 'border-gray-100 bg-white' : 'border-primary-200 bg-primary-50/30'
                }`}
              >
                <div className={`w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 ${colors}`}>
                  <Icon className="w-4 h-4" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className={`text-sm ${alert.read ? 'text-gray-700' : 'text-gray-900 font-medium'}`}>
                    {alert.message || `${alert.type.replace('_', ' ')} alert`}
                  </p>
                  <p className="text-xs text-gray-400 mt-1">
                    {formatDistanceToNow(new Date(alert.created_at), { addSuffix: true })}
                    {' '}&middot;{' '}
                    {format(new Date(alert.created_at), 'MMM d, h:mm a')}
                  </p>
                </div>
                {!alert.read && (
                  <button
                    onClick={() => handleMarkRead(alert.id)}
                    className="text-xs text-primary-600 hover:text-primary-700 font-medium flex-shrink-0"
                  >
                    Mark read
                  </button>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
