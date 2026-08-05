import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { workOrders, workOrderTypes } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  ArrowLeft,
  Save,
  AlertTriangle,
  User,
  MapPin,
  Calendar,
  Clock,
  FileText,
  Wrench,
  ChevronDown,
  Check,
  Loader2,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';

const PRIORITY_OPTIONS = [
  { value: 'low', label: 'Low', color: 'bg-gray-100 text-gray-700 border-gray-200' },
  { value: 'normal', label: 'Normal', color: 'bg-blue-50 text-blue-700 border-blue-200' },
  { value: 'high', label: 'High', color: 'bg-orange-50 text-orange-700 border-orange-200' },
  { value: 'urgent', label: 'Urgent', color: 'bg-red-50 text-red-700 border-red-200' },
];

const DURATION_PRESETS = [30, 60, 90, 120, 180, 240];

function FormSection({ icon: Icon, title, children, className = '' }) {
  return (
    <div className={`bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden ${className}`}>
      <div className="px-6 py-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-100">
        <h3 className="text-sm font-semibold text-gray-900 flex items-center gap-2">
          <Icon className="h-4 w-4 text-primary-600" />
          {title}
        </h3>
      </div>
      <div className="p-6">{children}</div>
    </div>
  );
}

function FormField({ label, error, required, children, className = '' }) {
  return (
    <div className={className}>
      <label className="block text-sm font-medium text-gray-700 mb-1.5">
        {label}
        {required && <span className="text-red-500 ml-0.5">*</span>}
      </label>
      {children}
      {error && (
        <p className="mt-1.5 text-sm text-red-600 flex items-center gap-1">
          <AlertTriangle className="h-3.5 w-3.5" />
          {error}
        </p>
      )}
    </div>
  );
}

function PrioritySelector({ value, onChange }) {
  return (
    <div className="grid grid-cols-4 gap-2">
      {PRIORITY_OPTIONS.map((priority) => (
        <button
          key={priority.value}
          type="button"
          onClick={() => onChange(priority.value)}
          className={`relative px-3 py-2.5 rounded-lg border-2 text-sm font-medium transition-all duration-200 ${
            value === priority.value
              ? `${priority.color} border-current ring-2 ring-offset-1 ring-current/20`
              : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50'
          }`}
        >
          {value === priority.value && (
            <Check className="absolute top-1 right-1 h-3 w-3" />
          )}
          {priority.label}
        </button>
      ))}
    </div>
  );
}

function MultiTypeSelector({ selectedIds, availableTypes, onChange }) {
  return (
    <div className="flex flex-wrap gap-2">
      {availableTypes.length === 0 ? (
        <p className="text-sm text-gray-400 italic">Loading types...</p>
      ) : (
        availableTypes.map((type) => {
          const isActive = selectedIds.includes(type.id);
          return (
            <button
              key={type.id}
              type="button"
              onClick={() => {
                if (isActive) {
                  onChange(selectedIds.filter((id) => id !== type.id));
                } else {
                  onChange([...selectedIds, type.id]);
                }
              }}
              className={`relative px-3 py-2 rounded-lg border-2 text-sm font-medium transition-all duration-200 ${
                isActive
                  ? 'ring-2 ring-offset-1'
                  : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50'
              }`}
              style={isActive ? {
                backgroundColor: `${type.color}15`,
                borderColor: type.color,
                color: type.color,
                '--tw-ring-color': `${type.color}40`,
              } : {}}
            >
              {isActive && (
                <Check className="absolute top-0.5 right-0.5 h-3 w-3" />
              )}
              {type.name}
              <span className="ml-1 text-[10px] opacity-60">{type.default_duration}m</span>
            </button>
          );
        })
      )}
    </div>
  );
}

function DurationSelector({ value, onChange }) {
  const isCustom = !DURATION_PRESETS.includes(Number(value));

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-2">
        {DURATION_PRESETS.map((minutes) => (
          <button
            key={minutes}
            type="button"
            onClick={() => onChange(minutes)}
            className={`px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-200 ${
              Number(value) === minutes
                ? 'bg-primary-600 text-white shadow-sm'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
            }`}
          >
            {minutes < 60 ? `${minutes}m` : `${minutes / 60}h`}
          </button>
        ))}
        <button
          type="button"
          onClick={() => onChange('')}
          className={`px-3 py-1.5 rounded-full text-sm font-medium transition-all duration-200 ${
            isCustom
              ? 'bg-primary-600 text-white shadow-sm'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
          }`}
        >
          Custom
        </button>
      </div>
      {isCustom && (
        <div className="flex items-center gap-2">
          <input
            type="number"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder="Minutes"
            min="1"
            className="w-24 px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all"
          />
          <span className="text-sm text-gray-500">minutes</span>
        </div>
      )}
    </div>
  );
}

export default function WorkOrderFormPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasCapability } = useAuth();
  const isEditing = Boolean(id);

  const [loading, setLoading] = useState(isEditing);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState({});
  const [availableTypes, setAvailableTypes] = useState([]);
  const durationManuallySet = useRef(false);

  const [formData, setFormData] = useState({
    title: '',
    description: '',
    work_order_types: [],
    priority: 'normal',
    estimated_duration: 60,
    customer: {
      name: '',
      phone: '',
      email: '',
    },
    address: {
      street: '',
      city: '',
      state: '',
      zip: '',
    },
    scheduled_date: '',
    scheduled_time_start: '',
    scheduled_time_end: '',
  });

  useEffect(() => {
    // Fetch available work order types from API
    workOrderTypes.list()
      .then((res) => setAvailableTypes(res.data))
      .catch((err) => console.error('Failed to load work order types:', err));

    if (isEditing) {
      loadWorkOrder();
    }
  }, [id]);

  const loadWorkOrder = async () => {
    try {
      const { data } = await workOrders.get(id);
      durationManuallySet.current = true; // Don't override saved duration
      setFormData({
        title: data.title || '',
        description: data.description || '',
        work_order_types: (data.types || []).map((t) => t.id),
        priority: data.priority || 'normal',
        estimated_duration: data.estimated_duration || 60,
        customer: {
          name: data.customer?.name || '',
          phone: data.customer?.phone || '',
          email: data.customer?.email || '',
        },
        address: {
          street: data.address?.street || '',
          city: data.address?.city || '',
          state: data.address?.state || '',
          zip: data.address?.zip || '',
        },
        scheduled_date: data.scheduled_date || '',
        scheduled_time_start: data.scheduled_time_start || '',
        scheduled_time_end: data.scheduled_time_end || '',
      });
    } catch (err) {
      console.error('Failed to load work order:', err);
    } finally {
      setLoading(false);
    }
  };

  const updateField = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors((prev) => ({ ...prev, [field]: null }));
    }
  };

  const handleTypesChange = (newTypeIds) => {
    updateField('work_order_types', newTypeIds);
    // Auto-populate duration from the longest type's default, unless user manually set it
    if (!durationManuallySet.current && newTypeIds.length > 0) {
      const maxDuration = Math.max(
        ...newTypeIds.map((id) => {
          const type = availableTypes.find((t) => t.id === id);
          return type?.default_duration || 60;
        })
      );
      updateField('estimated_duration', maxDuration);
    }
  };

  const handleDurationChange = (value) => {
    durationManuallySet.current = true;
    updateField('estimated_duration', value);
  };

  const updateNestedField = (parent, field, value) => {
    setFormData((prev) => ({
      ...prev,
      [parent]: { ...prev[parent], [field]: value },
    }));
    const errorKey = `${parent}.${field}`;
    if (errors[errorKey]) {
      setErrors((prev) => ({ ...prev, [errorKey]: null }));
    }
  };

  const validate = () => {
    const newErrors = {};

    if (!formData.title.trim()) {
      newErrors.title = 'Title is required';
    }

    if (!formData.customer.name.trim()) {
      newErrors['customer.name'] = 'Customer name is required';
    }

    if (!formData.address.street.trim()) {
      newErrors['address.street'] = 'Street address is required';
    }

    if (!formData.address.city.trim()) {
      newErrors['address.city'] = 'City is required';
    }

    if (formData.customer.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.customer.email)) {
      newErrors['customer.email'] = 'Invalid email address';
    }

    if (formData.scheduled_time_start && formData.scheduled_time_end) {
      if (formData.scheduled_time_start >= formData.scheduled_time_end) {
        newErrors.scheduled_time_end = 'End time must be after start time';
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!validate()) {
      return;
    }

    setSubmitting(true);

    try {
      // Transform form data to match API field names
      const payload = {
        title: formData.title,
        description: formData.description,
        priority: formData.priority,
        estimated_duration: formData.estimated_duration,
        work_order_types: formData.work_order_types,
        customer_name: formData.customer.name,
        customer_phone: formData.customer.phone,
        customer_email: formData.customer.email,
        address_street: formData.address.street,
        address_city: formData.address.city,
        address_state: formData.address.state,
        address_zip: formData.address.zip,
        scheduled_date: formData.scheduled_date,
        scheduled_time_start: formData.scheduled_time_start,
        scheduled_time_end: formData.scheduled_time_end,
      };

      let response;
      if (isEditing) {
        response = await workOrders.update(id, payload);
      } else {
        response = await workOrders.create(payload);
      }

      const workOrderId = response.data.id || id;
      navigate(`/work-orders/${workOrderId}`);
    } catch (err) {
      console.error('Failed to save work order:', err);
      const message = err.response?.data?.message || 'Failed to save work order';
      setErrors({ submit: message });
    } finally {
      setSubmitting(false);
    }
  };

  if (!hasCapability('fsm_create_work_orders') && !isEditing) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">You don't have permission to create work orders.</p>
      </div>
    );
  }

  if (!hasCapability('fsm_edit_work_orders') && isEditing) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">You don't have permission to edit work orders.</p>
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
    <div className="max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex items-center gap-4 mb-8">
        <button
          onClick={() => navigate(-1)}
          className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft className="h-5 w-5 text-gray-600" />
        </button>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {isEditing ? 'Edit Work Order' : 'Create Work Order'}
          </h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {isEditing
              ? 'Update the work order details below'
              : 'Fill in the details to create a new work order'}
          </p>
        </div>
      </div>

      {/* Error banner */}
      {errors.submit && (
        <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3">
          <AlertTriangle className="h-5 w-5 text-red-600 flex-shrink-0" />
          <p className="text-sm text-red-700">{errors.submit}</p>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Basic Info */}
        <FormSection icon={FileText} title="Basic Information">
          <div className="space-y-5">
            <FormField label="Title" required error={errors.title}>
              <input
                type="text"
                value={formData.title}
                onChange={(e) => updateField('title', e.target.value)}
                placeholder="Brief description of the work needed"
                className={`w-full px-4 py-3 border-2 rounded-lg outline-none transition-all ${
                  errors.title
                    ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
            </FormField>

            <FormField label="Description">
              <textarea
                value={formData.description}
                onChange={(e) => updateField('description', e.target.value)}
                placeholder="Detailed description of the work order..."
                rows={4}
                className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all resize-none"
              />
            </FormField>

            <FormField label="Type(s)">
              <MultiTypeSelector
                selectedIds={formData.work_order_types}
                availableTypes={availableTypes}
                onChange={handleTypesChange}
              />
            </FormField>

            <FormField label="Priority" required>
              <PrioritySelector
                value={formData.priority}
                onChange={(value) => updateField('priority', value)}
              />
            </FormField>

            <FormField label="Estimated Duration">
              <DurationSelector
                value={formData.estimated_duration}
                onChange={handleDurationChange}
              />
            </FormField>
          </div>
        </FormSection>

        {/* Customer Info */}
        <FormSection icon={User} title="Customer Information">
          <div className="space-y-5">
            <FormField label="Customer Name" required error={errors['customer.name']}>
              <input
                type="text"
                value={formData.customer.name}
                onChange={(e) => updateNestedField('customer', 'name', e.target.value)}
                placeholder="Full name or company name"
                className={`w-full px-4 py-3 border-2 rounded-lg outline-none transition-all ${
                  errors['customer.name']
                    ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
            </FormField>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <FormField label="Phone Number">
                <input
                  type="tel"
                  value={formData.customer.phone}
                  onChange={(e) => updateNestedField('customer', 'phone', e.target.value)}
                  placeholder="(555) 123-4567"
                  className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                />
              </FormField>

              <FormField label="Email" error={errors['customer.email']}>
                <input
                  type="email"
                  value={formData.customer.email}
                  onChange={(e) => updateNestedField('customer', 'email', e.target.value)}
                  placeholder="customer@example.com"
                  className={`w-full px-4 py-3 border-2 rounded-lg outline-none transition-all ${
                    errors['customer.email']
                      ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
                      : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                  }`}
                />
              </FormField>
            </div>
          </div>
        </FormSection>

        {/* Location */}
        <FormSection icon={MapPin} title="Service Location">
          <div className="space-y-5">
            <FormField label="Street Address" required error={errors['address.street']}>
              <input
                type="text"
                value={formData.address.street}
                onChange={(e) => updateNestedField('address', 'street', e.target.value)}
                placeholder="123 Main Street"
                className={`w-full px-4 py-3 border-2 rounded-lg outline-none transition-all ${
                  errors['address.street']
                    ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
            </FormField>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <FormField label="City" required error={errors['address.city']} className="col-span-2">
                <input
                  type="text"
                  value={formData.address.city}
                  onChange={(e) => updateNestedField('address', 'city', e.target.value)}
                  placeholder="City"
                  className={`w-full px-4 py-3 border-2 rounded-lg outline-none transition-all ${
                    errors['address.city']
                      ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
                      : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                  }`}
                />
              </FormField>

              <FormField label="State">
                <input
                  type="text"
                  value={formData.address.state}
                  onChange={(e) => updateNestedField('address', 'state', e.target.value)}
                  placeholder="State"
                  className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                />
              </FormField>

              <FormField label="ZIP Code">
                <input
                  type="text"
                  value={formData.address.zip}
                  onChange={(e) => updateNestedField('address', 'zip', e.target.value)}
                  placeholder="12345"
                  className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                />
              </FormField>
            </div>
          </div>
        </FormSection>

        {/* Scheduling */}
        <FormSection icon={Calendar} title="Scheduling">
          <div className="space-y-5">
            <FormField label="Scheduled Date">
              <input
                type="date"
                value={formData.scheduled_date}
                onChange={(e) => updateField('scheduled_date', e.target.value)}
                className="w-full md:w-auto px-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
              />
            </FormField>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
              <FormField label="Start Time">
                <div className="relative">
                  <Clock className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input
                    type="time"
                    value={formData.scheduled_time_start}
                    onChange={(e) => updateField('scheduled_time_start', e.target.value)}
                    className="w-full pl-11 pr-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                  />
                </div>
              </FormField>

              <FormField label="End Time" error={errors.scheduled_time_end}>
                <div className="relative">
                  <Clock className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input
                    type="time"
                    value={formData.scheduled_time_end}
                    onChange={(e) => updateField('scheduled_time_end', e.target.value)}
                    className={`w-full pl-11 pr-4 py-3 border-2 rounded-lg outline-none transition-all ${
                      errors.scheduled_time_end
                        ? 'border-red-300 focus:border-red-500 focus:ring-2 focus:ring-red-500/20'
                        : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                    }`}
                  />
                </div>
              </FormField>
            </div>

            <p className="text-sm text-gray-500 flex items-center gap-2">
              <Clock className="h-4 w-4" />
              Leave blank to create an unscheduled work order
            </p>
          </div>
        </FormSection>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 pt-4">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-6 py-3 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 focus:ring-4 focus:ring-primary-500/30 disabled:opacity-60 disabled:cursor-not-allowed transition-all"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Saving...
              </>
            ) : (
              <>
                <Save className="h-4 w-4" />
                {isEditing ? 'Update Work Order' : 'Create Work Order'}
              </>
            )}
          </button>
        </div>
      </form>
    </div>
  );
}
