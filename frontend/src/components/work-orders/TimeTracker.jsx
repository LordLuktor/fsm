import { useState, useEffect } from 'react';
import { Clock, Play, Pause, CheckCircle } from 'lucide-react';
import { parseServerDate } from '../../utils/dates';

export default function TimeTracker({ order }) {
  const [elapsed, setElapsed] = useState('');

  useEffect(() => {
    if (!order?.started_at || order.status === 'completed') return;

    const tick = () => {
      const start = parseServerDate(order.started_at);
      const now = new Date();
      const diff = Math.floor((now - start) / 1000);

      const hours = Math.floor(diff / 3600);
      const minutes = Math.floor((diff % 3600) / 60);
      const seconds = diff % 60;

      setElapsed(
        hours > 0
          ? `${hours}h ${minutes}m ${seconds}s`
          : `${minutes}m ${seconds}s`
      );
    };

    tick();
    const interval = setInterval(tick, 1000);
    return () => clearInterval(interval);
  }, [order?.started_at, order?.status]);

  if (!order) return null;

  const getTimeInfo = () => {
    const info = [];

    if (order.started_at) {
      info.push({
        label: 'Started',
        value: parseServerDate(order.started_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        icon: Play,
      });
    }

    if (order.completed_at) {
      info.push({
        label: 'Completed',
        value: parseServerDate(order.completed_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        icon: CheckCircle,
      });

      // Calculate total duration
      if (order.started_at) {
        const start = parseServerDate(order.started_at);
        const end = parseServerDate(order.completed_at);
        const diff = Math.floor((end - start) / 60000); // minutes
        const hours = Math.floor(diff / 60);
        const mins = diff % 60;
        info.push({
          label: 'Total Time',
          value: hours > 0 ? `${hours}h ${mins}m` : `${mins}m`,
          icon: Clock,
          highlight: true,
        });
      }
    }

    return info;
  };

  const timeInfo = getTimeInfo();
  const isActive = ['en_route', 'in_progress'].includes(order.status);

  if (timeInfo.length === 0 && !isActive) return null;

  return (
    <div className="bg-white rounded-lg shadow p-6">
      <h2 className="text-lg font-medium text-gray-900 flex items-center mb-4">
        <Clock className="h-5 w-5 mr-2" />
        Time Tracking
      </h2>

      <div className="space-y-3">
        {timeInfo.map((item) => (
          <div
            key={item.label}
            className={`flex items-center justify-between text-sm ${
              item.highlight ? 'p-3 bg-primary-50 rounded-lg' : ''
            }`}
          >
            <span className="flex items-center gap-2 text-gray-500">
              <item.icon className="h-4 w-4" />
              {item.label}
            </span>
            <span className={`font-medium ${item.highlight ? 'text-primary-700 text-base' : 'text-gray-900'}`}>
              {item.value}
            </span>
          </div>
        ))}

        {isActive && order.started_at && (
          <div className="flex items-center justify-between text-sm p-3 bg-orange-50 rounded-lg border border-orange-100">
            <span className="flex items-center gap-2 text-orange-600">
              <Clock className="h-4 w-4 animate-pulse" />
              Elapsed
            </span>
            <span className="font-mono font-bold text-orange-700">{elapsed}</span>
          </div>
        )}

        {order.status === 'on_hold' && (
          <div className="flex items-center justify-between text-sm p-3 bg-gray-50 rounded-lg border border-gray-200">
            <span className="flex items-center gap-2 text-gray-500">
              <Pause className="h-4 w-4" />
              Status
            </span>
            <span className="font-medium text-gray-600">On Hold</span>
          </div>
        )}

        {/* Estimated vs actual */}
        {order.completed_at && order.started_at && order.estimated_duration && (
          <div className="pt-3 mt-3 border-t border-gray-100">
            {(() => {
              const actual = Math.floor(
                (parseServerDate(order.completed_at) - parseServerDate(order.started_at)) / 60000
              );
              const estimated = parseInt(order.estimated_duration);
              const diff = actual - estimated;
              const overUnder = diff > 0 ? 'over' : diff < 0 ? 'under' : 'on time';

              return (
                <div className="flex items-center justify-between text-xs">
                  <span className="text-gray-500">
                    Est. {estimated}m vs. Actual {actual}m
                  </span>
                  <span
                    className={`font-medium ${
                      diff > 15 ? 'text-red-600' : diff < -5 ? 'text-green-600' : 'text-gray-600'
                    }`}
                  >
                    {Math.abs(diff)}m {overUnder}
                  </span>
                </div>
              );
            })()}
          </div>
        )}
      </div>
    </div>
  );
}
