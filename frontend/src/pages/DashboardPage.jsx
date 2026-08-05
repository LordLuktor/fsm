import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { workOrders } from '../api/client';
import {
  ClipboardList,
  Clock,
  CheckCircle,
  TrendingUp,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';
import PriorityBadge from '../components/common/PriorityBadge';

function DashboardPage() {
  const { user, isTechnician, isDispatcher } = useAuth();
  const [todayOrders, setTodayOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({
    total: 0,
    pending: 0,
    inProgress: 0,
    completed: 0,
  });

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = async () => {
    try {
      const { data } = await workOrders.getToday();
      setTodayOrders(data);

      // Calculate stats
      const newStats = {
        total: data.length,
        pending: data.filter((wo) => ['new', 'assigned'].includes(wo.status)).length,
        inProgress: data.filter((wo) => ['en_route', 'in_progress'].includes(wo.status)).length,
        completed: data.filter((wo) => wo.status === 'completed').length,
      };
      setStats(newStats);
    } catch (err) {
      console.error('Failed to load dashboard:', err);
    } finally {
      setLoading(false);
    }
  };

  const statusColors = {
    new: 'bg-blue-100 text-blue-800',
    assigned: 'bg-purple-100 text-purple-800',
    en_route: 'bg-yellow-100 text-yellow-800',
    in_progress: 'bg-orange-100 text-orange-800',
    on_hold: 'bg-gray-100 text-gray-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="medium" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Welcome */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">
          Welcome back, {user?.first_name || user?.display_name}
        </h1>
        <p className="text-gray-500">
          Here's what's happening today
        </p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 bg-blue-100 rounded-lg">
              <ClipboardList className="h-6 w-6 text-blue-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-500">Today's Orders</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.total}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 bg-yellow-100 rounded-lg">
              <Clock className="h-6 w-6 text-yellow-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-500">Pending</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.pending}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 bg-orange-100 rounded-lg">
              <TrendingUp className="h-6 w-6 text-orange-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-500">Active</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.inProgress}</p>
            </div>
          </div>
        </div>

        <div className="bg-white rounded-lg shadow p-6">
          <div className="flex items-center">
            <div className="p-3 bg-green-100 rounded-lg">
              <CheckCircle className="h-6 w-6 text-green-600" />
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-500">Completed</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.completed}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Today's Work Orders */}
      <div className="bg-white rounded-lg shadow">
        <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
          <h2 className="text-lg font-medium text-gray-900">Today's Work Orders</h2>
          <Link
            to="/work-orders"
            className="text-sm text-primary-600 hover:text-primary-700 font-medium"
          >
            View all
          </Link>
        </div>

        {todayOrders.length === 0 ? (
          <div className="p-6 text-center text-gray-500">
            <ClipboardList className="h-12 w-12 mx-auto text-gray-300 mb-3" />
            <p>No work orders scheduled for today</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-200">
            {todayOrders.slice(0, 5).map((order) => (
              <Link
                key={order.id}
                to={`/work-orders/${order.id}`}
                className={`block px-6 py-4 hover:bg-gray-50 transition-colors ${
                  order.priority === 'urgent' ? 'border-l-4 border-l-red-500 bg-red-50/30' :
                  order.priority === 'high' ? 'border-l-4 border-l-orange-400' : ''
                }`}
              >
                <div className="flex items-center justify-between">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-sm font-mono text-gray-500">
                        {order.reference}
                      </span>
                      <span
                        className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                          statusColors[order.status]
                        }`}
                      >
                        {order.status_label}
                      </span>
                      <PriorityBadge priority={order.priority} size="xs" />
                      {order.types?.length > 0 && order.types.map((t) => (
                        <span
                          key={t.id}
                          className="text-[10px] px-1.5 py-0.5 rounded font-medium border"
                          style={{
                            backgroundColor: `${t.color}15`,
                            color: t.color,
                            borderColor: `${t.color}40`,
                          }}
                        >
                          {t.name}
                        </span>
                      ))}
                    </div>
                    <p className="mt-1 text-sm font-medium text-gray-900 truncate">
                      {order.title}
                    </p>
                    <p className="mt-1 text-sm text-gray-500 truncate">
                      {order.customer?.name} - {order.address?.city}
                    </p>
                  </div>
                  <div className="ml-4 text-right">
                    <p className="text-sm text-gray-500">
                      {order.scheduled_time_start || 'Unscheduled'}
                    </p>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

export default DashboardPage;
