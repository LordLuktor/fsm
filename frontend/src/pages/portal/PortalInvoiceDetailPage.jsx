import { useState, useEffect } from 'react';
import { useParams, Link, useLocation } from 'react-router-dom';
import { useCustomerAuth } from '../../context/CustomerAuthContext';
import { useBranding } from '../../context/BrandingContext';
import { portalInvoices } from '../../api/portalClient';
import {
  ArrowLeft,
  Printer,
  Loader2,
  AlertCircle,
  FileText,
} from 'lucide-react';

export default function PortalInvoiceDetailPage() {
  const { id } = useParams();
  const { user } = useCustomerAuth();
  const { branding } = useBranding();
  const location = useLocation();

  const [invoice, setInvoice] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const basePath = location.pathname.split('/').slice(0, 3).join('/');
  const primaryColor = branding?.primary_color || '#2563eb';
  const companyName = branding?.company_name || 'Company';

  useEffect(() => {
    loadInvoice();
  }, [id]);

  const loadInvoice = async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await portalInvoices.get(id);
      setInvoice(data);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to load invoice.');
    } finally {
      setLoading(false);
    }
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
      month: 'long',
      day: 'numeric',
    });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-16">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="max-w-3xl mx-auto space-y-6">
        <Link
          to={`${basePath}/invoices`}
          className="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Invoices
        </Link>
        <div className="flex items-center gap-2 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      </div>
    );
  }

  if (!invoice) return null;

  const laborRate = invoice.labor_hours > 0
    ? invoice.labor_cost / invoice.labor_hours
    : 0;

  const customerName = user
    ? `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.email
    : '';

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      {/* Navigation - hidden when printing */}
      <div className="flex items-center justify-between print:hidden">
        <Link
          to={`${basePath}/invoices`}
          className="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to Invoices
        </Link>
        <button
          onClick={() => window.print()}
          className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors"
          style={{ backgroundColor: primaryColor }}
          onMouseEnter={(e) => { e.target.style.filter = 'brightness(0.9)'; }}
          onMouseLeave={(e) => { e.target.style.filter = ''; }}
        >
          <Printer className="h-4 w-4" />
          Print Invoice
        </button>
      </div>

      {/* Invoice Card */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm print:shadow-none print:border-0 print:rounded-none">
        {/* Invoice Header */}
        <div className="p-8 border-b border-gray-200 print:border-gray-300">
          <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
            {/* Company Info */}
            <div className="flex items-center gap-3">
              {branding?.logo_url ? (
                <img
                  src={branding.logo_url}
                  alt={companyName}
                  className="h-12 w-12 rounded-xl object-cover"
                />
              ) : (
                <div
                  className="w-12 h-12 rounded-xl flex items-center justify-center"
                  style={{ backgroundColor: primaryColor }}
                >
                  <FileText className="w-6 h-6 text-white" />
                </div>
              )}
              <div>
                <h2 className="text-lg font-bold text-gray-900">{companyName}</h2>
                {branding?.phone && (
                  <p className="text-sm text-gray-500">{branding.phone}</p>
                )}
              </div>
            </div>

            {/* Invoice Meta */}
            <div className="text-left sm:text-right">
              <h1 className="text-2xl font-bold text-gray-900 uppercase tracking-wide">
                Invoice
              </h1>
              <p className="text-sm text-gray-500 font-mono mt-1">
                {invoice.invoice_number}
              </p>
              <p className="text-sm text-gray-500 mt-1">
                {formatDate(invoice.date)}
              </p>
            </div>
          </div>
        </div>

        {/* Bill To + Reference */}
        <div className="px-8 py-6 border-b border-gray-200 print:border-gray-300">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
              <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">
                Bill To
              </p>
              <p className="text-sm font-medium text-gray-900">{customerName}</p>
              {user?.email && (
                <p className="text-sm text-gray-500">{user.email}</p>
              )}
            </div>
            {invoice.reference && (
              <div className="sm:text-right">
                <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">
                  Reference
                </p>
                <p className="text-sm font-mono text-gray-700">{invoice.reference}</p>
              </div>
            )}
          </div>
        </div>

        {/* Line Items */}
        <div className="px-8 py-6 border-b border-gray-200 print:border-gray-300">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200">
                <th className="text-left text-xs font-semibold text-gray-400 uppercase tracking-wider pb-3">
                  Description
                </th>
                <th className="text-center text-xs font-semibold text-gray-400 uppercase tracking-wider pb-3">
                  Qty
                </th>
                <th className="text-right text-xs font-semibold text-gray-400 uppercase tracking-wider pb-3">
                  Rate
                </th>
                <th className="text-right text-xs font-semibold text-gray-400 uppercase tracking-wider pb-3">
                  Amount
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {/* Labor line item */}
              {invoice.labor_hours > 0 && (
                <tr>
                  <td className="py-3 text-sm text-gray-900">Labor</td>
                  <td className="py-3 text-sm text-gray-600 text-center">
                    {parseFloat(invoice.labor_hours).toFixed(1)} hrs
                  </td>
                  <td className="py-3 text-sm text-gray-600 text-right">
                    {formatCurrency(laborRate)}/hr
                  </td>
                  <td className="py-3 text-sm font-medium text-gray-900 text-right">
                    {formatCurrency(invoice.labor_cost)}
                  </td>
                </tr>
              )}

              {/* Parts line items */}
              {invoice.parts && invoice.parts.length > 0 && (
                invoice.parts.map((part, idx) => (
                  <tr key={idx}>
                    <td className="py-3 text-sm text-gray-900">
                      {part.name || 'Part'}
                      {part.part_number && (
                        <span className="text-gray-400 font-mono text-xs ml-2">
                          #{part.part_number}
                        </span>
                      )}
                    </td>
                    <td className="py-3 text-sm text-gray-600 text-center">
                      {part.quantity || 1}
                    </td>
                    <td className="py-3 text-sm text-gray-600 text-right">
                      {formatCurrency(part.unit_price || part.price || 0)}
                    </td>
                    <td className="py-3 text-sm font-medium text-gray-900 text-right">
                      {formatCurrency(part.total || (part.quantity || 1) * (part.unit_price || part.price || 0))}
                    </td>
                  </tr>
                ))
              )}

              {/* Fallback if no labor and no parts */}
              {(!invoice.labor_hours || invoice.labor_hours <= 0) &&
               (!invoice.parts || invoice.parts.length === 0) && (
                <tr>
                  <td className="py-3 text-sm text-gray-900">{invoice.title || 'Service'}</td>
                  <td className="py-3 text-sm text-gray-600 text-center">1</td>
                  <td className="py-3 text-sm text-gray-600 text-right">
                    {formatCurrency(invoice.subtotal)}
                  </td>
                  <td className="py-3 text-sm font-medium text-gray-900 text-right">
                    {formatCurrency(invoice.subtotal)}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {/* Totals */}
        <div className="px-8 py-6">
          <div className="flex justify-end">
            <div className="w-64 space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-gray-500">Subtotal</span>
                <span className="text-gray-900 font-medium">
                  {formatCurrency(invoice.subtotal)}
                </span>
              </div>
              {parseFloat(invoice.tax) > 0 && (
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Tax</span>
                  <span className="text-gray-900 font-medium">
                    {formatCurrency(invoice.tax)}
                  </span>
                </div>
              )}
              <div className="flex justify-between text-base font-bold pt-2 border-t border-gray-200">
                <span className="text-gray-900">Total</span>
                <span style={{ color: primaryColor }}>
                  {formatCurrency(invoice.total)}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Print Footer - only visible when printing */}
      <div className="hidden print:block text-center text-xs text-gray-400 pt-8 border-t border-gray-200">
        <p>{companyName} &mdash; Thank you for your business</p>
      </div>
    </div>
  );
}
