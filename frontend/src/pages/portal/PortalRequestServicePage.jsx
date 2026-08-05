import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useCustomerAuth } from '../../context/CustomerAuthContext';
import { useBranding } from '../../context/BrandingContext';
import { portalService } from '../../api/portalClient';
import {
  Send,
  CheckCircle,
  AlertTriangle,
  Loader2,
  ArrowRight,
  ClipboardList,
} from 'lucide-react';

const URGENCY_OPTIONS = [
  {
    value: 'normal',
    label: 'Normal',
    description: 'Standard response time',
  },
  {
    value: 'high',
    label: 'High',
    description: 'Prioritized scheduling',
  },
  {
    value: 'urgent',
    label: 'Urgent',
    description: 'Requires immediate attention',
  },
];

export default function PortalRequestServicePage() {
  const { user } = useCustomerAuth();
  const { branding } = useBranding();
  const location = useLocation();

  const basePath = location.pathname.split('/').slice(0, 3).join('/');
  const primaryColor = branding?.primary_color || '#2563eb';

  const [form, setForm] = useState({
    title: '',
    description: '',
    preferred_date: '',
    urgency: 'normal',
    street: user?.address?.street || '',
    city: user?.address?.city || '',
    state: user?.address?.state || '',
    zip: user?.address?.zip || '',
  });

  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      const payload = {
        title: form.title.trim(),
        description: form.description.trim(),
        preferred_date: form.preferred_date || undefined,
        priority: form.urgency,
        address: {
          street: form.street.trim(),
          city: form.city.trim(),
          state: form.state.trim(),
          zip: form.zip.trim(),
        },
      };

      if (branding?.organization_id) {
        payload.organization_id = branding.organization_id;
      }

      const { data } = await portalService.request(payload);
      setSuccess(data);
    } catch (err) {
      const msg =
        err.response?.data?.message ||
        'Unable to submit your request. Please try again.';
      setError(msg);
    } finally {
      setSubmitting(false);
    }
  };

  // Success state
  if (success) {
    return (
      <div className="max-w-lg mx-auto text-center py-8">
        <div
          className="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-5"
          style={{ backgroundColor: primaryColor + '1a' }}
        >
          <CheckCircle className="h-8 w-8" style={{ color: primaryColor }} />
        </div>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">Service Request Submitted</h1>
        <p className="text-gray-500 mb-2">
          Your request has been received and is being reviewed.
        </p>
        {success.reference && (
          <p className="text-sm text-gray-600 mb-6">
            Reference: <span className="font-mono font-semibold">{success.reference}</span>
          </p>
        )}
        <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
          {success.id && (
            <Link
              to={`${basePath}/work-orders/${success.id}`}
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition-opacity"
              style={{ backgroundColor: primaryColor }}
            >
              <ClipboardList className="h-4 w-4" />
              View Work Order
            </Link>
          )}
          <Link
            to={`${basePath}/dashboard`}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition-colors"
          >
            Back to Dashboard
            <ArrowRight className="h-4 w-4" />
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Request Service</h1>
        <p className="text-gray-500 mt-1">
          Describe the issue and we will get a technician scheduled for you.
        </p>
      </div>

      {error && (
        <div className="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
          <AlertTriangle className="h-5 w-5 text-red-500 shrink-0" />
          <p className="text-sm text-red-700">{error}</p>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Issue Details */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-5">
          <h2 className="text-sm font-semibold text-gray-700">Issue Details</h2>

          <div>
            <label htmlFor="title" className="block text-sm font-medium text-gray-700 mb-1.5">
              What is the issue? <span className="text-red-500">*</span>
            </label>
            <input
              id="title"
              name="title"
              type="text"
              required
              maxLength={200}
              value={form.title}
              onChange={handleChange}
              placeholder="e.g. AC not cooling, leaking faucet, furnace making noise"
              className="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:border-transparent focus:outline-none"
              style={{ '--tw-ring-color': primaryColor + '40' }}
            />
          </div>

          <div>
            <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1.5">
              Additional details
            </label>
            <textarea
              id="description"
              name="description"
              rows={4}
              maxLength={2000}
              value={form.description}
              onChange={handleChange}
              placeholder="Describe the problem in more detail. When did it start? Any relevant information that would help the technician..."
              className="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:border-transparent focus:outline-none resize-y"
              style={{ '--tw-ring-color': primaryColor + '40' }}
            />
          </div>

          <div>
            <label htmlFor="preferred_date" className="block text-sm font-medium text-gray-700 mb-1.5">
              Preferred date
            </label>
            <input
              id="preferred_date"
              name="preferred_date"
              type="date"
              value={form.preferred_date}
              onChange={handleChange}
              min={new Date().toISOString().split('T')[0]}
              className="w-full sm:w-auto px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:border-transparent focus:outline-none"
              style={{ '--tw-ring-color': primaryColor + '40' }}
            />
          </div>

          {/* Urgency */}
          <fieldset>
            <legend className="block text-sm font-medium text-gray-700 mb-2">
              Urgency <span className="text-red-500">*</span>
            </legend>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              {URGENCY_OPTIONS.map((opt) => {
                const isSelected = form.urgency === opt.value;
                return (
                  <label
                    key={opt.value}
                    className={`relative flex items-start gap-3 p-3 rounded-lg border-2 cursor-pointer transition-colors ${
                      isSelected ? 'border-current bg-opacity-5' : 'border-gray-200 hover:border-gray-300'
                    }`}
                    style={isSelected ? { borderColor: primaryColor, backgroundColor: primaryColor + '08' } : undefined}
                  >
                    <input
                      type="radio"
                      name="urgency"
                      value={opt.value}
                      checked={isSelected}
                      onChange={handleChange}
                      className="sr-only"
                    />
                    <div>
                      <p
                        className={`text-sm font-semibold ${
                          isSelected ? '' : 'text-gray-900'
                        }`}
                        style={isSelected ? { color: primaryColor } : undefined}
                      >
                        {opt.label}
                      </p>
                      <p className="text-xs text-gray-500 mt-0.5">{opt.description}</p>
                    </div>
                  </label>
                );
              })}
            </div>
          </fieldset>
        </div>

        {/* Address */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-5">
          <h2 className="text-sm font-semibold text-gray-700">Service Address</h2>

          <div>
            <label htmlFor="street" className="block text-sm font-medium text-gray-700 mb-1.5">
              Street address <span className="text-red-500">*</span>
            </label>
            <input
              id="street"
              name="street"
              type="text"
              required
              maxLength={200}
              value={form.street}
              onChange={handleChange}
              placeholder="123 Main St"
              className="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:border-transparent focus:outline-none"
              style={{ '--tw-ring-color': primaryColor + '40' }}
            />
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div className="col-span-2 sm:col-span-2">
              <label htmlFor="city" className="block text-sm font-medium text-gray-700 mb-1.5">
                City <span className="text-red-500">*</span>
              </label>
              <input
                id="city"
                name="city"
                type="text"
                required
                maxLength={100}
                value={form.city}
                onChange={handleChange}
                placeholder="City"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:border-transparent focus:outline-none"
                style={{ '--tw-ring-color': primaryColor + '40' }}
              />
            </div>
            <div>
              <label htmlFor="state" className="block text-sm font-medium text-gray-700 mb-1.5">
                State <span className="text-red-500">*</span>
              </label>
              <input
                id="state"
                name="state"
                type="text"
                required
                maxLength={2}
                value={form.state}
                onChange={handleChange}
                placeholder="PA"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:border-transparent focus:outline-none uppercase"
                style={{ '--tw-ring-color': primaryColor + '40' }}
              />
            </div>
            <div>
              <label htmlFor="zip" className="block text-sm font-medium text-gray-700 mb-1.5">
                ZIP <span className="text-red-500">*</span>
              </label>
              <input
                id="zip"
                name="zip"
                type="text"
                required
                maxLength={10}
                pattern="[0-9]{5}(-[0-9]{4})?"
                value={form.zip}
                onChange={handleChange}
                placeholder="15213"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:border-transparent focus:outline-none"
                style={{ '--tw-ring-color': primaryColor + '40' }}
              />
            </div>
          </div>
        </div>

        {/* Submit */}
        <div className="flex items-center justify-end gap-3">
          <Link
            to={`${basePath}/dashboard`}
            className="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition-colors"
          >
            Cancel
          </Link>
          <button
            type="submit"
            disabled={submitting}
            className="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg text-white text-sm font-medium hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
            style={{ backgroundColor: primaryColor }}
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Submitting...
              </>
            ) : (
              <>
                <Send className="h-4 w-4" />
                Submit Request
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}
