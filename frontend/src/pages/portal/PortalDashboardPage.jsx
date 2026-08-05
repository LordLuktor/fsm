import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useCustomerAuth } from '../../context/CustomerAuthContext';
import { useBranding } from '../../context/BrandingContext';
import { portalWorkOrders } from '../../api/portalClient';
import {
  ClipboardList,
  CheckCircle,
  Clock,
  Truck,
  Wrench,
  Play,
  PlusCircle,
  FileText,
  ArrowRight,
  Loader2,
  AlertTriangle,
} from 'lucide-react';

const progressSteps = [
  { key: 'new', label: 'Submitted', icon: Clock },
  { key: 'assigned', label: 'Assigned', icon: Wrench },
  { key: 'en_route', label: 'On the Way', icon: Truck },
  { key: 'in_progress', label: 'In Progress', icon: Play },
  { key: 'completed', label: 'Completed', icon: CheckCircle },
];

const statusToStep = {
  new: 0,
  assigned: 1,
  en_route: 2,
  in_progress: 3,
  completed: 4,
  on_hold: 3,
  cancelled: -1,
};

const statusBadge = {
  new: 'bg-blue-100 text-blue-800',
  assigned: 'bg-purple-100 text-purple-800',
  en_route: 'bg-yellow-100 text-yellow-800',
  in_progress: 'bg-orange-100 text-orange-800',
  on_hold: 'bg-gray-100 text-gray-800',
  completed: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
};

export default function PortalDashboardPage() {
  const { user } = useCustomerAuth();
  const { branding } = useBranding();
  const location = useLocation();

  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const basePath = location.pathname.split('/').slice(0, 3).join('/');
  const primaryColor = branding?.primary_color || '#2563eb';

  useEffect(() => {
    loadOrders();
  }, []);

  const loadOrders = async () => {
    try {
      const { data } = await portalWorkOrders.list();
      setOrders(Array.isArray(data) ? data : data.items || []);
      setError(null);
    } catch (err) {
      setError('Unable to load your work orders. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const activeOrders = orders.filter((o) =>
    ['new', 'assigned', 'en_route', 'in_progress', 'on_hold'].includes(o.status)
  );
  const completedOrders = orders.filter((o) => o.status === 'completed');
  const currentOrder = activeOrders[0] || null;
  const recentOrders = orders.slice(0, 5);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Welcome */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">
          Welcome back, {user?.first_name || user?.email || 'there'}
        </h1>
        <p className="text-gray-500 mt-1">
          Here is an overview of your service activity.
        </p>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
          <AlertTriangle className="h-5 w-5 text-red-500 shrink-0" />
          <p className="text-sm text-red-700">{error}</p>
        </div>
      )}

      {/* Quick Stats */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
          <div className="flex items-center gap-4">
            <div
              className="w-12 h-12 rounded-xl flex items-center justify-center"
              style={{ backgroundColor: primaryColor + '1a' }}
            >
              <ClipboardList className="h-6 w-6" style={{ color: primaryColor }} />
            </div>
            <div>
              <p className="text-sm font-medium text-gray-500">Active Orders</p>
              <p className="text-2xl font-bold text-gray-900">{activeOrders.length}</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-xl flex items-center justify-center bg-green-50">
              <CheckCircle className="h-6 w-6 text-green-600" />
            </div>
            <div>
              <p className="text-sm font-medium text-gray-500">Completed</p>
              <p className="text-2xl font-bold text-gray-900">{completedOrders.length}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Current / Active Work Order */}
      {currentOrder && (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">Current Work Order</h2>
            <Link
              to={`${basePath}/work-orders/${currentOrder.id}`}
              className="text-sm font-medium flex items-center gap-1 hover:underline"
              style={{ color: primaryColor }}
            >
              View details <ArrowRight className="h-4 w-4" />
            </Link>
          </div>

          <div className="p-6 space-y-5">
            {/* Order headline */}
            <div className="flex items-start justify-between gap-4">
              <div className="min-w-0">
                <span className="text-sm font-mono text-gray-500">{currentOrder.reference}</span>
                <h3 className="text-base font-semibold text-gray-900 mt-0.5 truncate">
                  {currentOrder.title}
                </h3>
              </div>
              <span
                className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium shrink-0 ${
                  statusBadge[currentOrder.status] || 'bg-gray-100 text-gray-800'
                }`}
              >
                {currentOrder.status_label || currentOrder.status}
              </span>
            </div>

            {/* Status progress tracker */}
            {currentOrder.status !== 'cancelled' && (
              <div className="flex items-center justify-between">
                {progressSteps.map((step, i) => {
                  const currentStep = statusToStep[currentOrder.status] ?? 0;
                  const isComplete = i < currentStep;
                  const isCurrent = i === currentStep;
                  return (
                    <div key={step.key} className="flex items-center">
                      <div className="flex flex-col items-center">
                        <div
                          className={`w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center border-2 transition-colors ${
                            isComplete
                              ? 'border-green-500 bg-green-50 text-green-500'
                              : isCurrent
                              ? 'border-2 text-white'
                              : 'border-gray-200 bg-white text-gray-300'
                          }`}
                          style={
                            isCurrent
                              ? { borderColor: primaryColor, backgroundColor: primaryColor }
                              : undefined
                          }
                        >
                          {isComplete ? (
                            <CheckCircle className="h-5 w-5" />
                          ) : (
                            <step.icon className="h-4 w-4 sm:h-5 sm:w-5" />
                          )}
                        </div>
                        <span
                          className={`text-[10px] sm:text-xs mt-1 font-medium text-center leading-tight ${
                            isComplete || isCurrent ? 'text-gray-700' : 'text-gray-400'
                          }`}
                        >
                          {step.label}
                        </span>
                      </div>
                      {i < progressSteps.length - 1 && (
                        <div
                          className={`w-6 sm:w-10 lg:w-14 h-0.5 mx-0.5 ${
                            i < currentStep ? 'bg-green-500' : 'bg-gray-200'
                          }`}
                        />
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Recent Work Orders */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200">
        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 className="text-lg font-semibold text-gray-900">Recent Work Orders</h2>
          <Link
            to={`${basePath}/work-orders`}
            className="text-sm font-medium hover:underline"
            style={{ color: primaryColor }}
          >
            View all
          </Link>
        </div>

        {recentOrders.length === 0 ? (
          <div className="p-8 text-center">
            <ClipboardList className="h-12 w-12 mx-auto text-gray-300 mb-3" />
            <p className="text-gray-500">No work orders yet.</p>
            <Link
              to={`${basePath}/request-service`}
              className="inline-flex items-center gap-2 mt-4 text-sm font-medium hover:underline"
              style={{ color: primaryColor }}
            >
              <PlusCircle className="h-4 w-4" /> Request your first service
            </Link>
          </div>
        ) : (
          <div className="divide-y divide-gray-100">
            {recentOrders.map((order) => (
              <Link
                key={order.id}
                to={`${basePath}/work-orders/${order.id}`}
                className="block px-6 py-4 hover:bg-gray-50 transition-colors"
              >
                <div className="flex items-center justify-between gap-4">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="text-sm font-mono text-gray-500">
                        {order.reference}
                      </span>
                      <span
                        className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                          statusBadge[order.status] || 'bg-gray-100 text-gray-800'
                        }`}
                      >
                        {order.status_label || order.status}
                      </span>
                    </div>
                    <p className="text-sm font-medium text-gray-900 truncate mt-0.5">
                      {order.title}
                    </p>
                  </div>
                  <div className="text-right shrink-0">
                    <p className="text-sm text-gray-500">
                      {order.scheduled_date
                        ? new Date(order.scheduled_date + 'T00:00:00').toLocaleDateString()
                        : order.created_at
                        ? new Date(order.created_at).toLocaleDateString()
                        : ''}
                    </p>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <Link
          to={`${basePath}/request-service`}
          className="flex items-center gap-3 bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow group"
        >
          <div
            className="w-10 h-10 rounded-lg flex items-center justify-center text-white shrink-0"
            style={{ backgroundColor: primaryColor }}
          >
            <PlusCircle className="h-5 w-5" />
          </div>
          <div>
            <p className="text-sm font-semibold text-gray-900 group-hover:underline">
              Request Service
            </p>
            <p className="text-xs text-gray-500">Submit a new work order</p>
          </div>
        </Link>

        <Link
          to={`${basePath}/work-orders`}
          className="flex items-center gap-3 bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow group"
        >
          <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-gray-100 text-gray-600 shrink-0">
            <ClipboardList className="h-5 w-5" />
          </div>
          <div>
            <p className="text-sm font-semibold text-gray-900 group-hover:underline">
              View All Orders
            </p>
            <p className="text-xs text-gray-500">See your full order history</p>
          </div>
        </Link>

        <Link
          to={`${basePath}/invoices`}
          className="flex items-center gap-3 bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow group"
        >
          <div className="w-10 h-10 rounded-lg flex items-center justify-center bg-gray-100 text-gray-600 shrink-0">
            <FileText className="h-5 w-5" />
          </div>
          <div>
            <p className="text-sm font-semibold text-gray-900 group-hover:underline">
              View Invoices
            </p>
            <p className="text-xs text-gray-500">Billing and payment history</p>
          </div>
        </Link>
      </div>
    </div>
  );
}
