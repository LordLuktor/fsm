import { useState, useEffect } from 'react';
import { parseScheduleDate } from '../utils/dates';
import { useParams } from 'react-router-dom';
import {
  CheckCircle, Clock, Truck, Wrench, MapPin, Phone,
  AlertCircle, Loader2, Play, Pause, XCircle,
} from 'lucide-react';
import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'https://fsm-api.steinmetz.ltd/wp-json';

const statusConfig = {
  new: { icon: Clock, label: 'Submitted', color: 'blue', step: 0 },
  assigned: { icon: Wrench, label: 'Technician Assigned', color: 'purple', step: 1 },
  en_route: { icon: Truck, label: 'Technician On the Way', color: 'yellow', step: 2 },
  in_progress: { icon: Play, label: 'Work In Progress', color: 'orange', step: 3 },
  on_hold: { icon: Pause, label: 'On Hold', color: 'gray', step: 3 },
  completed: { icon: CheckCircle, label: 'Completed', color: 'green', step: 4 },
  cancelled: { icon: XCircle, label: 'Cancelled', color: 'red', step: -1 },
};

const progressSteps = [
  { label: 'Submitted', icon: Clock },
  { label: 'Assigned', icon: Wrench },
  { label: 'On the Way', icon: Truck },
  { label: 'In Progress', icon: Play },
  { label: 'Completed', icon: CheckCircle },
];

export default function CustomerPortalPage() {
  const { token } = useParams();
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadWorkOrder();
    // Auto-refresh every 30 seconds
    const interval = setInterval(loadWorkOrder, 30000);
    return () => clearInterval(interval);
  }, [token]);

  const loadWorkOrder = async () => {
    try {
      const { data } = await axios.get(`${API_URL}/fsm/v1/portal/${token}`);
      setOrder(data);
      setError(null);
    } catch (err) {
      if (err.response?.status === 404) {
        setError('This work order link is invalid or has expired.');
      } else {
        setError('Unable to load work order status. Please try again later.');
      }
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="h-8 w-8 animate-spin text-primary-600 mx-auto mb-3" />
          <p className="text-gray-500">Loading your work order...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="max-w-md text-center">
          <AlertCircle className="h-12 w-12 text-red-400 mx-auto mb-4" />
          <h1 className="text-xl font-bold text-gray-900 mb-2">Work Order Not Found</h1>
          <p className="text-gray-600">{error}</p>
        </div>
      </div>
    );
  }

  const config = statusConfig[order.status] || statusConfig.new;
  const currentStep = config.step;

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200">
        <div className="max-w-2xl mx-auto px-4 py-6 text-center">
          <div className="w-10 h-10 bg-primary-600 rounded-xl flex items-center justify-center mx-auto mb-3">
            <Wrench className="w-6 h-6 text-white" />
          </div>
          <h1 className="text-xl font-bold text-gray-900">Work Order Status</h1>
          <p className="text-sm text-gray-500 font-mono mt-1">{order.reference}</p>
        </div>
      </div>

      <div className="max-w-2xl mx-auto px-4 py-8 space-y-6">
        {/* Status Card */}
        <div className={`bg-white rounded-2xl shadow-sm border-2 p-6 ${
          config.color === 'green' ? 'border-green-200' :
          config.color === 'orange' ? 'border-orange-200' :
          config.color === 'yellow' ? 'border-yellow-200' :
          config.color === 'red' ? 'border-red-200' :
          'border-gray-200'
        }`}>
          <div className="flex items-center gap-4">
            <div className={`w-14 h-14 rounded-2xl flex items-center justify-center ${
              config.color === 'green' ? 'bg-green-100' :
              config.color === 'orange' ? 'bg-orange-100' :
              config.color === 'yellow' ? 'bg-yellow-100' :
              config.color === 'red' ? 'bg-red-100' :
              config.color === 'purple' ? 'bg-purple-100' :
              'bg-blue-100'
            }`}>
              <config.icon className={`h-7 w-7 ${
                config.color === 'green' ? 'text-green-600' :
                config.color === 'orange' ? 'text-orange-600' :
                config.color === 'yellow' ? 'text-yellow-600' :
                config.color === 'red' ? 'text-red-600' :
                config.color === 'purple' ? 'text-purple-600' :
                'text-blue-600'
              }`} />
            </div>
            <div>
              <h2 className="text-lg font-bold text-gray-900">{config.label}</h2>
              <p className="text-sm text-gray-500">{order.title}</p>
            </div>
          </div>
        </div>

        {/* Progress Steps */}
        {order.status !== 'cancelled' && (
          <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between">
              {progressSteps.map((step, i) => (
                <div key={step.label} className="flex items-center">
                  <div className="flex flex-col items-center">
                    <div className={`w-10 h-10 rounded-full flex items-center justify-center border-2 ${
                      i <= currentStep
                        ? i === currentStep
                          ? 'border-primary-600 bg-primary-50 text-primary-600'
                          : 'border-green-500 bg-green-50 text-green-500'
                        : 'border-gray-200 bg-white text-gray-300'
                    }`}>
                      {i < currentStep ? (
                        <CheckCircle className="h-5 w-5" />
                      ) : (
                        <step.icon className="h-5 w-5" />
                      )}
                    </div>
                    <span className={`text-[10px] mt-1 font-medium ${
                      i <= currentStep ? 'text-gray-700' : 'text-gray-400'
                    }`}>
                      {step.label}
                    </span>
                  </div>
                  {i < progressSteps.length - 1 && (
                    <div className={`w-8 sm:w-12 h-0.5 mx-0.5 ${
                      i < currentStep ? 'bg-green-500' : 'bg-gray-200'
                    }`} />
                  )}
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Details */}
        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 divide-y divide-gray-100">
          {order.scheduled_date && (
            <div className="p-5 flex items-center gap-4">
              <Clock className="h-5 w-5 text-gray-400 shrink-0" />
              <div>
                <p className="text-sm text-gray-500">Scheduled</p>
                <p className="font-medium text-gray-900">
                  {parseScheduleDate(order.scheduled_date).toLocaleDateString('en-US', {
                    weekday: 'long', month: 'long', day: 'numeric',
                  })}
                  {order.scheduled_time_start && ` at ${order.scheduled_time_start}`}
                </p>
              </div>
            </div>
          )}

          {order.address?.street && (
            <div className="p-5 flex items-center gap-4">
              <MapPin className="h-5 w-5 text-gray-400 shrink-0" />
              <div>
                <p className="text-sm text-gray-500">Service Location</p>
                <p className="font-medium text-gray-900">
                  {order.address.street}
                  {order.address.city && `, ${order.address.city}`}
                  {order.address.state && ` ${order.address.state}`}
                  {order.address.zip && ` ${order.address.zip}`}
                </p>
              </div>
            </div>
          )}

          {order.technician_name && (
            <div className="p-5 flex items-center gap-4">
              <Wrench className="h-5 w-5 text-gray-400 shrink-0" />
              <div>
                <p className="text-sm text-gray-500">Your Technician</p>
                <p className="font-medium text-gray-900">{order.technician_name}</p>
              </div>
            </div>
          )}

          {order.company_phone && (
            <div className="p-5">
              <a
                href={`tel:${order.company_phone}`}
                className="flex items-center gap-4 text-primary-600 hover:text-primary-700"
              >
                <Phone className="h-5 w-5 shrink-0" />
                <div>
                  <p className="text-sm text-gray-500">Need Help?</p>
                  <p className="font-medium">{order.company_phone}</p>
                </div>
              </a>
            </div>
          )}
        </div>

        {/* Auto-refresh notice */}
        <p className="text-center text-xs text-gray-400">
          This page updates automatically every 30 seconds.
        </p>
      </div>

      {/* Footer */}
      <div className="border-t border-gray-200 py-6 text-center text-xs text-gray-400">
        Powered by FSM — Field Service Management
      </div>
    </div>
  );
}
