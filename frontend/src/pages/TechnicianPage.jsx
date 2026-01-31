import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { workOrders, gps, ptt } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  MapPin,
  Phone,
  Navigation,
  Clock,
  CheckCircle,
  Play,
  Package,
  Radio,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';

function TechnicianPage() {
  const { user, isTechnician } = useAuth();
  const [todayOrders, setTodayOrders] = useState([]);
  const [currentOrder, setCurrentOrder] = useState(null);
  const [pttInfo, setPttInfo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [locationStatus, setLocationStatus] = useState('idle');

  useEffect(() => {
    if (isTechnician) {
      loadTechnicianData();
    }
  }, [isTechnician]);

  const loadTechnicianData = async () => {
    try {
      const [ordersRes, pttRes] = await Promise.all([
        workOrders.getToday(),
        ptt.getConnectionInfo(),
      ]);

      setTodayOrders(ordersRes.data);
      setPttInfo(pttRes.data);

      // Find current in-progress order
      const inProgress = ordersRes.data.find((o) =>
        ['en_route', 'in_progress'].includes(o.status)
      );
      setCurrentOrder(inProgress || null);
    } catch (err) {
      console.error('Failed to load technician data:', err);
    } finally {
      setLoading(false);
    }
  };

  const updateLocation = useCallback(async () => {
    if (!navigator.geolocation) {
      console.error('Geolocation not supported');
      return;
    }

    setLocationStatus('updating');

    navigator.geolocation.getCurrentPosition(
      async (position) => {
        try {
          await gps.updateLocation({
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            altitude: position.coords.altitude,
            heading: position.coords.heading,
            speed: position.coords.speed,
            work_order_id: currentOrder?.id,
          });
          setLocationStatus('success');
          setTimeout(() => setLocationStatus('idle'), 2000);
        } catch (err) {
          console.error('Failed to update location:', err);
          setLocationStatus('error');
        }
      },
      (error) => {
        console.error('Geolocation error:', error);
        setLocationStatus('error');
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  }, [currentOrder]);

  const handleCheckin = async (orderId) => {
    if (!navigator.geolocation) {
      alert('Geolocation not supported');
      return;
    }

    navigator.geolocation.getCurrentPosition(
      async (position) => {
        try {
          await gps.checkin(orderId, position.coords.latitude, position.coords.longitude);
          alert('Checked in successfully!');
          loadTechnicianData();
        } catch (err) {
          console.error('Check-in failed:', err);
          alert(err.response?.data?.message || 'Check-in failed');
        }
      },
      (error) => {
        alert('Could not get your location');
      },
      { enableHighAccuracy: true }
    );
  };

  const statusColors = {
    new: 'border-l-blue-500 bg-blue-50',
    assigned: 'border-l-purple-500 bg-purple-50',
    en_route: 'border-l-yellow-500 bg-yellow-50',
    in_progress: 'border-l-orange-500 bg-orange-50',
    on_hold: 'border-l-gray-500 bg-gray-50',
    completed: 'border-l-green-500 bg-green-50',
  };

  if (!isTechnician) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">This page is for technicians only.</p>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="medium" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">My Dashboard</h1>
        <p className="text-gray-500">
          Welcome, {user?.first_name || user?.display_name}
        </p>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <button
          onClick={updateLocation}
          disabled={locationStatus === 'updating'}
          className={`p-4 rounded-lg border-2 flex flex-col items-center justify-center transition-colors ${
            locationStatus === 'success'
              ? 'border-green-500 bg-green-50'
              : locationStatus === 'error'
              ? 'border-red-500 bg-red-50'
              : 'border-gray-200 bg-white hover:border-primary-500'
          }`}
        >
          <Navigation
            className={`h-6 w-6 mb-2 ${
              locationStatus === 'updating' ? 'animate-pulse text-primary-500' : 'text-gray-600'
            }`}
          />
          <span className="text-sm font-medium text-gray-900">
            {locationStatus === 'updating'
              ? 'Updating...'
              : locationStatus === 'success'
              ? 'Updated!'
              : 'Update Location'}
          </span>
        </button>

        <Link
          to="/inventory"
          className="p-4 rounded-lg border-2 border-gray-200 bg-white hover:border-primary-500 flex flex-col items-center justify-center"
        >
          <Package className="h-6 w-6 mb-2 text-gray-600" />
          <span className="text-sm font-medium text-gray-900">My Inventory</span>
        </Link>

        {pttInfo && (
          <a
            href={pttInfo.instructions?.native_client}
            className="p-4 rounded-lg border-2 border-gray-200 bg-white hover:border-primary-500 flex flex-col items-center justify-center"
          >
            <Radio className="h-6 w-6 mb-2 text-gray-600" />
            <span className="text-sm font-medium text-gray-900">Open PTT</span>
          </a>
        )}

        {currentOrder && (
          <button
            onClick={() => handleCheckin(currentOrder.id)}
            className="p-4 rounded-lg border-2 border-primary-500 bg-primary-50 flex flex-col items-center justify-center"
          >
            <MapPin className="h-6 w-6 mb-2 text-primary-600" />
            <span className="text-sm font-medium text-primary-700">Check In</span>
          </button>
        )}
      </div>

      {/* Current Work Order */}
      {currentOrder && (
        <div className="bg-white rounded-lg shadow border-l-4 border-orange-500">
          <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <div>
              <span className="text-xs font-mono text-gray-500">{currentOrder.reference}</span>
              <h2 className="text-lg font-medium text-gray-900">Current Job</h2>
            </div>
            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-orange-100 text-orange-800">
              {currentOrder.status_label}
            </span>
          </div>
          <div className="p-6">
            <h3 className="font-medium text-gray-900 mb-2">{currentOrder.title}</h3>
            <div className="space-y-2 text-sm text-gray-600">
              <p className="flex items-center">
                <MapPin className="h-4 w-4 mr-2 text-gray-400" />
                {currentOrder.address?.street}, {currentOrder.address?.city}
              </p>
              {currentOrder.customer?.phone && (
                <a
                  href={`tel:${currentOrder.customer.phone}`}
                  className="flex items-center text-primary-600"
                >
                  <Phone className="h-4 w-4 mr-2" />
                  {currentOrder.customer.phone}
                </a>
              )}
            </div>
            <div className="mt-4 flex gap-2">
              <Link
                to={`/work-orders/${currentOrder.id}`}
                className="btn btn-primary flex-1 text-center"
              >
                View Details
              </Link>
              {currentOrder.address?.latitude && (
                <a
                  href={`https://maps.google.com/maps?daddr=${currentOrder.address.latitude},${currentOrder.address.longitude}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn btn-secondary"
                >
                  <Navigation className="h-4 w-4" />
                </a>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Today's Work Orders */}
      <div className="bg-white rounded-lg shadow">
        <div className="px-6 py-4 border-b border-gray-200">
          <h2 className="text-lg font-medium text-gray-900 flex items-center">
            <Clock className="h-5 w-5 mr-2" />
            Today's Schedule ({todayOrders.length})
          </h2>
        </div>

        {todayOrders.length === 0 ? (
          <div className="p-8 text-center text-gray-500">
            <CheckCircle className="h-12 w-12 mx-auto text-green-300 mb-3" />
            <p>No work orders scheduled for today</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-200">
            {todayOrders.map((order) => (
              <Link
                key={order.id}
                to={`/work-orders/${order.id}`}
                className={`block p-4 hover:bg-gray-50 border-l-4 ${
                  statusColors[order.status] || 'border-l-gray-300'
                }`}
              >
                <div className="flex items-center justify-between">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-mono text-gray-500">
                        {order.reference}
                      </span>
                      <span className="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">
                        {order.status_label}
                      </span>
                    </div>
                    <p className="mt-1 font-medium text-gray-900 truncate">
                      {order.title}
                    </p>
                    <p className="mt-1 text-sm text-gray-500 truncate">
                      {order.address?.street}, {order.address?.city}
                    </p>
                  </div>
                  <div className="ml-4 text-right">
                    <p className="text-sm font-medium text-gray-900">
                      {order.scheduled_time_start || 'TBD'}
                    </p>
                    <p className="text-xs text-gray-500">
                      {order.estimated_duration || 60} min
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

export default TechnicianPage;
