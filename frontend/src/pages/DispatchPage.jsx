import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { dispatch } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  Calendar,
  ChevronLeft,
  ChevronRight,
  Clock,
  User,
  MapPin,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';

function DispatchPage() {
  const { hasCapability } = useAuth();
  const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);
  const [board, setBoard] = useState({ date: '', technicians: [] });
  const [unscheduled, setUnscheduled] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadDispatchBoard();
  }, [selectedDate]);

  const loadDispatchBoard = async () => {
    setLoading(true);
    try {
      const [boardRes, unscheduledRes] = await Promise.all([
        dispatch.getBoard(selectedDate),
        dispatch.getUnscheduled(),
      ]);
      setBoard(boardRes.data);
      setUnscheduled(unscheduledRes.data);
    } catch (err) {
      console.error('Failed to load dispatch board:', err);
    } finally {
      setLoading(false);
    }
  };

  const changeDate = (days) => {
    const date = new Date(selectedDate);
    date.setDate(date.getDate() + days);
    setSelectedDate(date.toISOString().split('T')[0]);
  };

  const statusColors = {
    new: 'border-l-blue-500 bg-blue-50',
    assigned: 'border-l-purple-500 bg-purple-50',
    en_route: 'border-l-yellow-500 bg-yellow-50',
    in_progress: 'border-l-orange-500 bg-orange-50',
    on_hold: 'border-l-gray-500 bg-gray-50',
  };

  const techStatusColors = {
    available: 'bg-green-100 text-green-800',
    busy: 'bg-orange-100 text-orange-800',
    offline: 'bg-gray-100 text-gray-800',
  };

  if (!hasCapability('fsm_view_dispatch')) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">You don't have permission to view the dispatch board.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
          <Calendar className="h-6 w-6 mr-2" />
          Dispatch Board
        </h1>

        {/* Date Navigation */}
        <div className="flex items-center gap-2">
          <button
            onClick={() => changeDate(-1)}
            className="p-2 hover:bg-gray-100 rounded-md"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <input
            type="date"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            className="px-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
          />
          <button
            onClick={() => changeDate(1)}
            className="p-2 hover:bg-gray-100 rounded-md"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
          <button
            onClick={() => setSelectedDate(new Date().toISOString().split('T')[0])}
            className="btn btn-secondary"
          >
            Today
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-64">
          <LoadingSpinner size="medium" />
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Unscheduled Work Orders */}
          <div className="bg-white rounded-lg shadow">
            <div className="px-4 py-3 border-b border-gray-200 bg-gray-50">
              <h2 className="font-medium text-gray-900">
                Unscheduled ({unscheduled.length})
              </h2>
            </div>
            <div className="p-2 max-h-[600px] overflow-y-auto">
              {unscheduled.length === 0 ? (
                <p className="text-sm text-gray-500 text-center py-4">
                  No unscheduled work orders
                </p>
              ) : (
                <div className="space-y-2">
                  {unscheduled.map((wo) => (
                    <Link
                      key={wo.id}
                      to={`/work-orders/${wo.id}`}
                      className="block p-3 bg-gray-50 rounded-md hover:bg-gray-100 transition-colors"
                    >
                      <div className="text-xs font-mono text-gray-500">{wo.reference}</div>
                      <div className="text-sm font-medium text-gray-900 truncate">
                        {wo.title}
                      </div>
                      <div className="text-xs text-gray-500 mt-1">
                        {wo.customer_name} - {wo.estimated_duration}min
                      </div>
                    </Link>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Technician Columns */}
          <div className="lg:col-span-3 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {board.technicians.length === 0 ? (
              <div className="col-span-full text-center py-12 text-gray-500">
                No active technicians
              </div>
            ) : (
              board.technicians.map((tech) => (
                <div key={tech.technician.id} className="bg-white rounded-lg shadow">
                  {/* Technician Header */}
                  <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div className="flex items-center">
                      <div className="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center">
                        <User className="h-4 w-4 text-primary-600" />
                      </div>
                      <div className="ml-3">
                        <div className="font-medium text-gray-900">
                          {tech.technician.name}
                        </div>
                        <div className="text-xs text-gray-500">
                          {tech.work_orders.length} jobs - {tech.total_duration}min
                        </div>
                      </div>
                    </div>
                    <span
                      className={`text-xs px-2 py-1 rounded-full ${
                        techStatusColors[tech.technician.status] || techStatusColors.offline
                      }`}
                    >
                      {tech.technician.status || 'offline'}
                    </span>
                  </div>

                  {/* Work Orders */}
                  <div className="p-2 space-y-2 max-h-[500px] overflow-y-auto">
                    {tech.work_orders.length === 0 ? (
                      <p className="text-sm text-gray-500 text-center py-4">
                        No scheduled work orders
                      </p>
                    ) : (
                      tech.work_orders.map((wo) => (
                        <Link
                          key={wo.id}
                          to={`/work-orders/${wo.id}`}
                          className={`block p-3 rounded-md border-l-4 ${
                            statusColors[wo.status] || 'border-l-gray-300 bg-gray-50'
                          } hover:shadow-md transition-shadow`}
                        >
                          <div className="flex items-center justify-between mb-1">
                            <span className="text-xs font-mono text-gray-500">
                              {wo.reference}
                            </span>
                            {wo.time_start && (
                              <span className="text-xs text-gray-500 flex items-center">
                                <Clock className="h-3 w-3 mr-1" />
                                {wo.time_start}
                              </span>
                            )}
                          </div>
                          <div className="text-sm font-medium text-gray-900 truncate">
                            {wo.title}
                          </div>
                          <div className="text-xs text-gray-500 mt-1 flex items-center">
                            <MapPin className="h-3 w-3 mr-1 flex-shrink-0" />
                            <span className="truncate">
                              {wo.customer_name} - {wo.address?.city}
                            </span>
                          </div>
                        </Link>
                      ))
                    )}
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default DispatchPage;
