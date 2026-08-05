import { useState, useEffect, useCallback } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useBranding } from '../../context/BrandingContext';
import { portalWorkOrders } from '../../api/portalClient';
import {
  ClipboardList,
  Search,
  MapPin,
  Calendar,
  ChevronLeft,
  ChevronRight,
  Loader2,
  AlertTriangle,
} from 'lucide-react';

const TABS = [
  { key: 'all', label: 'All' },
  { key: 'active', label: 'Active' },
  { key: 'completed', label: 'Completed' },
  { key: 'cancelled', label: 'Cancelled' },
];

const statusBadge = {
  new: 'bg-blue-100 text-blue-800',
  assigned: 'bg-purple-100 text-purple-800',
  en_route: 'bg-yellow-100 text-yellow-800',
  in_progress: 'bg-orange-100 text-orange-800',
  on_hold: 'bg-gray-100 text-gray-800',
  completed: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
};

const PER_PAGE = 10;

export default function PortalWorkOrdersPage() {
  const { branding } = useBranding();
  const location = useLocation();

  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('all');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  const basePath = location.pathname.split('/').slice(0, 3).join('/');
  const primaryColor = branding?.primary_color || '#2563eb';

  const loadOrders = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page,
        per_page: PER_PAGE,
      };

      if (activeTab === 'active') {
        params.status = 'new,assigned,en_route,in_progress,on_hold';
      } else if (activeTab === 'completed') {
        params.status = 'completed';
      } else if (activeTab === 'cancelled') {
        params.status = 'cancelled';
      }

      if (search.trim()) {
        params.search = search.trim();
      }

      const { data } = await portalWorkOrders.list(params);

      if (Array.isArray(data)) {
        setOrders(data);
        setTotalPages(1);
      } else {
        setOrders(data.items || []);
        setTotalPages(data.total_pages || 1);
      }
    } catch (err) {
      setError('Unable to load work orders. Please try again.');
      setOrders([]);
    } finally {
      setLoading(false);
    }
  }, [activeTab, search, page]);

  useEffect(() => {
    loadOrders();
  }, [loadOrders]);

  const handleTabChange = (tab) => {
    setActiveTab(tab);
    setPage(1);
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setPage(1);
    loadOrders();
  };

  const formatAddress = (address) => {
    if (!address) return null;
    const parts = [address.city, address.state].filter(Boolean);
    return parts.length > 0 ? parts.join(', ') : address.street || null;
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Work Orders</h1>
        <p className="text-gray-500 mt-1">View and track all your service requests.</p>
      </div>

      {/* Search + Filter Tabs */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {/* Tabs */}
        <div className="border-b border-gray-200 px-4 sm:px-6">
          <nav className="-mb-px flex gap-4 sm:gap-6 overflow-x-auto" aria-label="Filter tabs">
            {TABS.map((tab) => (
              <button
                key={tab.key}
                onClick={() => handleTabChange(tab.key)}
                className={`whitespace-nowrap py-3 border-b-2 text-sm font-medium transition-colors ${
                  activeTab === tab.key
                    ? 'border-current'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
                style={activeTab === tab.key ? { color: primaryColor } : undefined}
              >
                {tab.label}
              </button>
            ))}
          </nav>
        </div>

        {/* Search */}
        <div className="px-4 sm:px-6 py-3 border-b border-gray-100">
          <form onSubmit={handleSearchSubmit} className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by reference or title..."
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:border-transparent focus:outline-none"
              style={{ '--tw-ring-color': primaryColor + '40' }}
            />
          </form>
        </div>

        {/* Error */}
        {error && (
          <div className="mx-4 sm:mx-6 mt-4 bg-red-50 border border-red-200 rounded-lg p-3 flex items-center gap-2">
            <AlertTriangle className="h-4 w-4 text-red-500 shrink-0" />
            <p className="text-sm text-red-700">{error}</p>
          </div>
        )}

        {/* Loading */}
        {loading ? (
          <div className="flex items-center justify-center py-16">
            <Loader2 className="h-7 w-7 animate-spin text-gray-400" />
          </div>
        ) : orders.length === 0 ? (
          <div className="py-16 text-center">
            <ClipboardList className="h-12 w-12 mx-auto text-gray-300 mb-3" />
            <p className="text-gray-500">
              {search.trim()
                ? 'No work orders match your search.'
                : activeTab !== 'all'
                ? `No ${activeTab} work orders found.`
                : 'You have no work orders yet.'}
            </p>
          </div>
        ) : (
          <>
            {/* Order Cards */}
            <div className="divide-y divide-gray-100">
              {orders.map((order) => (
                <Link
                  key={order.id}
                  to={`${basePath}/work-orders/${order.id}`}
                  className="block px-4 sm:px-6 py-4 hover:bg-gray-50 transition-colors"
                >
                  <div className="flex items-start justify-between gap-4">
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
                      <p className="text-sm font-semibold text-gray-900 truncate mt-1">
                        {order.title}
                      </p>
                      <div className="flex items-center gap-4 mt-1.5 text-xs text-gray-500">
                        {order.scheduled_date && (
                          <span className="flex items-center gap-1">
                            <Calendar className="h-3.5 w-3.5" />
                            {new Date(order.scheduled_date + 'T00:00:00').toLocaleDateString()}
                          </span>
                        )}
                        {formatAddress(order.address) && (
                          <span className="flex items-center gap-1 truncate">
                            <MapPin className="h-3.5 w-3.5 shrink-0" />
                            {formatAddress(order.address)}
                          </span>
                        )}
                      </div>
                    </div>
                    <ChevronRight className="h-5 w-5 text-gray-400 shrink-0 mt-1" />
                  </div>
                </Link>
              ))}
            </div>

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="px-4 sm:px-6 py-4 border-t border-gray-100 flex items-center justify-between">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  className="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-gray-900 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  <ChevronLeft className="h-4 w-4" />
                  Previous
                </button>
                <span className="text-sm text-gray-500">
                  Page {page} of {totalPages}
                </span>
                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page >= totalPages}
                  className="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-gray-900 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Next
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
