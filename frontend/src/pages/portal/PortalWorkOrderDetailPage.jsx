import { useState, useEffect } from 'react';
import { Link, useParams, useLocation } from 'react-router-dom';
import { useBranding } from '../../context/BrandingContext';
import { portalWorkOrders } from '../../api/portalClient';
import {
  ArrowLeft,
  CheckCircle,
  Clock,
  Truck,
  Wrench,
  Play,
  MapPin,
  Calendar,
  User,
  FileText,
  AlertTriangle,
  XCircle,
  Loader2,
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
  new: { bg: 'bg-blue-100', text: 'text-blue-800', border: 'border-blue-200' },
  assigned: { bg: 'bg-purple-100', text: 'text-purple-800', border: 'border-purple-200' },
  en_route: { bg: 'bg-yellow-100', text: 'text-yellow-800', border: 'border-yellow-200' },
  in_progress: { bg: 'bg-orange-100', text: 'text-orange-800', border: 'border-orange-200' },
  on_hold: { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-200' },
  completed: { bg: 'bg-green-100', text: 'text-green-800', border: 'border-green-200' },
  cancelled: { bg: 'bg-red-100', text: 'text-red-800', border: 'border-red-200' },
};

export default function PortalWorkOrderDetailPage() {
  const { id } = useParams();
  const { branding } = useBranding();
  const location = useLocation();

  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const basePath = location.pathname.split('/').slice(0, 3).join('/');
  const primaryColor = branding?.primary_color || '#2563eb';

  useEffect(() => {
    loadOrder();
  }, [id]);

  const loadOrder = async () => {
    setLoading(true);
    try {
      const { data } = await portalWorkOrders.get(id);
      setOrder(data);
      setError(null);
    } catch (err) {
      if (err.response?.status === 404) {
        setError('This work order was not found.');
      } else {
        setError('Unable to load work order details. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  if (error || !order) {
    return (
      <div className="text-center py-16">
        <AlertTriangle className="h-12 w-12 mx-auto text-gray-400 mb-4" />
        <h2 className="text-lg font-semibold text-gray-900 mb-1">Work Order Not Found</h2>
        <p className="text-gray-500 mb-6">{error || 'This work order could not be loaded.'}</p>
        <Link
          to={`${basePath}/work-orders`}
          className="inline-flex items-center gap-2 text-sm font-medium hover:underline"
          style={{ color: primaryColor }}
        >
          <ArrowLeft className="h-4 w-4" /> Back to Work Orders
        </Link>
      </div>
    );
  }

  const currentStep = statusToStep[order.status] ?? 0;
  const badge = statusBadge[order.status] || statusBadge.new;
  const isCancelled = order.status === 'cancelled';

  const formatFullAddress = () => {
    const a = order.address;
    if (!a) return null;
    const parts = [a.street, [a.city, a.state].filter(Boolean).join(', '), a.zip].filter(Boolean);
    return parts.join(', ');
  };

  return (
    <div className="space-y-6">
      {/* Back link + header */}
      <div className="flex items-center gap-3">
        <Link
          to={`${basePath}/work-orders`}
          className="p-2 hover:bg-gray-200 rounded-lg transition-colors"
          title="Back to Work Orders"
        >
          <ArrowLeft className="h-5 w-5 text-gray-600" />
        </Link>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-sm font-mono text-gray-500">{order.reference}</span>
            <span
              className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${badge.bg} ${badge.text}`}
            >
              {order.status_label || order.status}
            </span>
          </div>
          <h1 className="text-xl sm:text-2xl font-bold text-gray-900 mt-0.5 truncate">
            {order.title}
          </h1>
        </div>
      </div>

      {/* Status Progress Tracker */}
      {!isCancelled && (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-6">
          <h2 className="text-sm font-semibold text-gray-700 mb-4">Status</h2>
          <div className="flex items-center justify-between">
            {progressSteps.map((step, i) => {
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
                      className={`w-6 sm:w-10 lg:w-16 h-0.5 mx-0.5 ${
                        i < currentStep ? 'bg-green-500' : 'bg-gray-200'
                      }`}
                    />
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Cancelled banner */}
      {isCancelled && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
          <XCircle className="h-5 w-5 text-red-500 shrink-0" />
          <p className="text-sm text-red-700 font-medium">This work order has been cancelled.</p>
        </div>
      )}

      {/* Details grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main details */}
        <div className="lg:col-span-2 space-y-6">
          {/* Description */}
          {order.description && (
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-6">
              <h2 className="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <FileText className="h-4 w-4" /> Description
              </h2>
              <p className="text-gray-600 text-sm whitespace-pre-wrap leading-relaxed">
                {order.description}
              </p>
            </div>
          )}

          {/* Status Timeline / History */}
          {order.status_history && order.status_history.length > 0 && (
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-6">
              <h2 className="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                <Clock className="h-4 w-4" /> Timeline
              </h2>
              <div className="relative">
                <div className="absolute left-3.5 top-2 bottom-2 w-px bg-gray-200" />
                <div className="space-y-4">
                  {order.status_history.map((entry, i) => (
                    <div key={i} className="flex gap-4 relative">
                      <div
                        className="w-7 h-7 rounded-full border-2 border-white shadow flex items-center justify-center shrink-0 z-10"
                        style={{ backgroundColor: i === 0 ? primaryColor : '#d1d5db' }}
                      >
                        <CheckCircle className="h-3.5 w-3.5 text-white" />
                      </div>
                      <div className="pt-0.5 min-w-0">
                        <p className="text-sm font-medium text-gray-900">
                          {entry.status_label || entry.status}
                        </p>
                        <p className="text-xs text-gray-500 mt-0.5">
                          {entry.timestamp
                            ? new Date(entry.timestamp).toLocaleString()
                            : entry.date || ''}
                          {entry.note && (
                            <span className="block text-gray-500 mt-0.5">{entry.note}</span>
                          )}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Schedule */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h2 className="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
              <Calendar className="h-4 w-4" /> Schedule
            </h2>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-500">Date</span>
                <span className="font-medium text-gray-900">
                  {order.scheduled_date
                    ? new Date(order.scheduled_date + 'T00:00:00').toLocaleDateString('en-US', {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                      })
                    : 'Not scheduled'}
                </span>
              </div>
              {order.scheduled_time_start && (
                <div className="flex justify-between">
                  <span className="text-gray-500">Time</span>
                  <span className="font-medium text-gray-900">
                    {order.scheduled_time_start}
                    {order.scheduled_time_end && ` - ${order.scheduled_time_end}`}
                  </span>
                </div>
              )}
              {order.priority && (
                <div className="flex justify-between">
                  <span className="text-gray-500">Priority</span>
                  <span className="font-medium text-gray-900 capitalize">{order.priority}</span>
                </div>
              )}
            </div>
          </div>

          {/* Technician */}
          {order.technician_name && (
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
              <h2 className="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <User className="h-4 w-4" /> Your Technician
              </h2>
              <p className="text-sm font-medium text-gray-900">{order.technician_name}</p>
            </div>
          )}

          {/* Location */}
          {order.address && formatFullAddress() && (
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
              <h2 className="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                <MapPin className="h-4 w-4" /> Service Location
              </h2>
              <p className="text-sm text-gray-600 leading-relaxed">{formatFullAddress()}</p>
              {order.address.latitude && order.address.longitude && (
                <a
                  href={`https://maps.google.com/?q=${order.address.latitude},${order.address.longitude}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1 mt-2 text-xs font-medium hover:underline"
                  style={{ color: primaryColor }}
                >
                  <MapPin className="h-3.5 w-3.5" /> Open in Maps
                </a>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
