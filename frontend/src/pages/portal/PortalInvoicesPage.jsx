import { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useCustomerAuth } from '../../context/CustomerAuthContext';
import { useBranding } from '../../context/BrandingContext';
import { portalInvoices } from '../../api/portalClient';
import {
  FileText,
  ChevronRight,
  Loader2,
  AlertCircle,
  Search,
  Receipt,
} from 'lucide-react';

export default function PortalInvoicesPage() {
  const { user } = useCustomerAuth();
  const { branding } = useBranding();
  const location = useLocation();

  const [invoices, setInvoices] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [search, setSearch] = useState('');

  const basePath = location.pathname.split('/').slice(0, 3).join('/');
  const primaryColor = branding?.primary_color || '#2563eb';

  useEffect(() => {
    loadInvoices();
  }, [page]);

  const loadInvoices = async () => {
    setLoading(true);
    setError(null);
    try {
      const params = {
        page,
        per_page: 20,
        ...(search && { search }),
      };
      const response = await portalInvoices.list(params);
      setInvoices(response.data);
      setTotalPages(parseInt(response.headers['x-wp-totalpages'] || '1', 10));
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load invoices.');
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (e) => {
    e.preventDefault();
    setPage(1);
    loadInvoices();
  };

  const formatCurrency = (amount) => {
    const num = parseFloat(amount);
    if (isNaN(num)) return '$0.00';
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(num);
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <Receipt className="h-6 w-6" />
          Invoices
        </h1>
        <p className="text-gray-500 mt-1">
          View and manage your invoices
        </p>
      </div>

      {/* Search */}
      <form onSubmit={handleSearch} className="flex gap-2">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search invoices..."
            className="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:border-transparent"
            style={{ '--tw-ring-color': primaryColor }}
            onFocus={(e) => {
              e.target.style.boxShadow = `0 0 0 2px ${primaryColor}33`;
              e.target.style.borderColor = primaryColor;
            }}
            onBlur={(e) => {
              e.target.style.boxShadow = '';
              e.target.style.borderColor = '';
            }}
          />
        </div>
        <button
          type="submit"
          className="px-4 py-2.5 text-white text-sm font-medium rounded-lg transition-colors"
          style={{ backgroundColor: primaryColor }}
          onMouseEnter={(e) => { e.target.style.filter = 'brightness(0.9)'; }}
          onMouseLeave={(e) => { e.target.style.filter = ''; }}
        >
          Search
        </button>
      </form>

      {/* Error State */}
      {error && (
        <div className="flex items-center gap-2 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {/* Loading State */}
      {loading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </div>
      ) : invoices.length === 0 ? (
        /* Empty State */
        <div className="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <FileText className="h-12 w-12 text-gray-300 mx-auto mb-4" />
          <h3 className="text-lg font-semibold text-gray-900 mb-1">No invoices yet</h3>
          <p className="text-gray-500 text-sm">
            Invoices will appear here once work is completed and billed.
          </p>
        </div>
      ) : (
        <>
          {/* Invoice List */}
          <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            {/* Table Header */}
            <div className="hidden sm:grid sm:grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
              <div className="col-span-3">Invoice</div>
              <div className="col-span-4">Description</div>
              <div className="col-span-2">Date</div>
              <div className="col-span-2 text-right">Amount</div>
              <div className="col-span-1" />
            </div>

            {/* Invoice Rows */}
            <div className="divide-y divide-gray-100">
              {invoices.map((invoice) => (
                <Link
                  key={invoice.id}
                  to={`${basePath}/invoices/${invoice.id}`}
                  className="block px-6 py-4 hover:bg-gray-50 transition-colors"
                >
                  {/* Mobile Layout */}
                  <div className="sm:hidden space-y-2">
                    <div className="flex items-center justify-between">
                      <span className="font-mono text-sm font-semibold text-gray-900">
                        {invoice.invoice_number}
                      </span>
                      <span className="text-sm font-semibold text-gray-900">
                        {formatCurrency(invoice.total)}
                      </span>
                    </div>
                    <p className="text-sm text-gray-600 truncate">{invoice.title}</p>
                    <p className="text-xs text-gray-400">{formatDate(invoice.date)}</p>
                  </div>

                  {/* Desktop Layout */}
                  <div className="hidden sm:grid sm:grid-cols-12 gap-4 items-center">
                    <div className="col-span-3">
                      <span className="font-mono text-sm font-semibold text-gray-900">
                        {invoice.invoice_number}
                      </span>
                    </div>
                    <div className="col-span-4">
                      <p className="text-sm text-gray-700 truncate">{invoice.title}</p>
                    </div>
                    <div className="col-span-2">
                      <span className="text-sm text-gray-500">{formatDate(invoice.date)}</span>
                    </div>
                    <div className="col-span-2 text-right">
                      <span className="text-sm font-semibold text-gray-900">
                        {formatCurrency(invoice.total)}
                      </span>
                    </div>
                    <div className="col-span-1 flex justify-end">
                      <ChevronRight className="h-4 w-4 text-gray-400" />
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="flex items-center justify-between">
              <p className="text-sm text-gray-500">
                Page {page} of {totalPages}
              </p>
              <div className="flex gap-2">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  className="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Previous
                </button>
                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page >= totalPages}
                  className="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
