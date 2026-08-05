import { useState, useEffect, useCallback } from 'react';
import { team, vehicles } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  Users,
  Truck,
  Plus,
  Pencil,
  UserX,
  Check,
  X,
  Loader2,
  Search,
  RefreshCw,
  Phone,
  Mail,
  Shield,
  ChevronDown,
  AlertTriangle,
} from 'lucide-react';
import Modal from '../components/common/Modal';
import LoadingSpinner from '../components/common/LoadingSpinner';

// ─── Constants ────────────────────────────────────────

const ROLE_CONFIG = {
  fsm_administrator: {
    label: 'Administrator',
    color: 'bg-purple-100 text-purple-800',
    dotColor: 'bg-purple-500',
  },
  fsm_dispatcher: {
    label: 'Dispatcher',
    color: 'bg-blue-100 text-blue-800',
    dotColor: 'bg-blue-500',
  },
  fsm_technician: {
    label: 'Technician',
    color: 'bg-green-100 text-green-800',
    dotColor: 'bg-green-500',
  },
};

const STATUS_CONFIG = {
  active: { label: 'Active', color: 'bg-green-100 text-green-800' },
  inactive: { label: 'Inactive', color: 'bg-gray-100 text-gray-800' },
  deactivated: { label: 'Deactivated', color: 'bg-red-100 text-red-800' },
};

const VEHICLE_STATUS_CONFIG = {
  active: { label: 'Active', color: 'bg-green-100 text-green-800' },
  maintenance: { label: 'Maintenance', color: 'bg-yellow-100 text-yellow-800' },
  retired: { label: 'Retired', color: 'bg-gray-100 text-gray-800' },
};

const EMPTY_MEMBER_FORM = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  role: 'fsm_technician',
};

const EMPTY_VEHICLE_FORM = {
  make: '',
  model: '',
  year: new Date().getFullYear(),
  plate_number: '',
};

// ─── Helper: Role Badge ──────────────────────────────

function RoleBadge({ role }) {
  const config = ROLE_CONFIG[role] || ROLE_CONFIG.fsm_technician;
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium ${config.color}`}
    >
      <span className={`w-1.5 h-1.5 rounded-full ${config.dotColor}`} />
      {config.label}
    </span>
  );
}

// ─── Helper: Status Badge ────────────────────────────

function StatusBadge({ status, configMap }) {
  const config = configMap[status] || { label: status, color: 'bg-gray-100 text-gray-800' };
  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.color}`}
    >
      {config.label}
    </span>
  );
}

// ─── Add Team Member Modal ───────────────────────────

function AddTeamMemberModal({ isOpen, onClose, onSuccess, canAddAdmin }) {
  const [formData, setFormData] = useState({ ...EMPTY_MEMBER_FORM });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [errors, setErrors] = useState({});

  const updateField = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors((prev) => ({ ...prev, [field]: null }));
    }
  };

  const validate = () => {
    const newErrors = {};

    if (!formData.first_name.trim()) {
      newErrors.first_name = 'First name is required';
    }
    if (!formData.last_name.trim()) {
      newErrors.last_name = 'Last name is required';
    }
    if (!formData.email.trim()) {
      newErrors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Invalid email address';
    }
    if (formData.phone && !/^[\d\s\-+().]*$/.test(formData.phone)) {
      newErrors.phone = 'Invalid phone number';
    }
    if (!canAddAdmin && formData.role === 'fsm_administrator') {
      newErrors.role = 'Only administrators can add other administrators';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate()) return;

    setSubmitting(true);
    setError(null);

    try {
      await team.add(formData);
      onSuccess?.();
      onClose();
      setFormData({ ...EMPTY_MEMBER_FORM });
      setErrors({});
    } catch (err) {
      console.error('Failed to add team member:', err);
      setError(err.response?.data?.message || 'Failed to add team member');
    } finally {
      setSubmitting(false);
    }
  };

  const handleClose = () => {
    setFormData({ ...EMPTY_MEMBER_FORM });
    setErrors({});
    setError(null);
    onClose();
  };

  const availableRoles = canAddAdmin
    ? Object.entries(ROLE_CONFIG)
    : Object.entries(ROLE_CONFIG).filter(([key]) => key !== 'fsm_administrator');

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title="Add Team Member" size="md">
      <form onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        <div className="space-y-4">
          {/* Name fields */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                First Name <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.first_name}
                onChange={(e) => updateField('first_name', e.target.value)}
                placeholder="John"
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.first_name
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.first_name && (
                <p className="mt-1 text-sm text-red-600">{errors.first_name}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Last Name <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.last_name}
                onChange={(e) => updateField('last_name', e.target.value)}
                placeholder="Smith"
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.last_name
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.last_name && (
                <p className="mt-1 text-sm text-red-600">{errors.last_name}</p>
              )}
            </div>
          </div>

          {/* Email */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Email <span className="text-red-500">*</span>
            </label>
            <input
              type="email"
              value={formData.email}
              onChange={(e) => updateField('email', e.target.value)}
              placeholder="john.smith@company.com"
              className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                errors.email
                  ? 'border-red-300 focus:border-red-500'
                  : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
              }`}
            />
            {errors.email && (
              <p className="mt-1 text-sm text-red-600">{errors.email}</p>
            )}
          </div>

          {/* Phone */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Phone
            </label>
            <input
              type="tel"
              value={formData.phone}
              onChange={(e) => updateField('phone', e.target.value)}
              placeholder="(555) 123-4567"
              className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                errors.phone
                  ? 'border-red-300 focus:border-red-500'
                  : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
              }`}
            />
            {errors.phone && (
              <p className="mt-1 text-sm text-red-600">{errors.phone}</p>
            )}
          </div>

          {/* Role */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Role <span className="text-red-500">*</span>
            </label>
            <div className="flex flex-wrap gap-2">
              {availableRoles.map(([roleKey, roleConfig]) => (
                <button
                  key={roleKey}
                  type="button"
                  onClick={() => updateField('role', roleKey)}
                  className={`flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 text-sm font-medium transition-all ${
                    formData.role === roleKey
                      ? 'border-primary-500 bg-primary-50 text-primary-700'
                      : 'border-gray-200 text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <span className={`w-2 h-2 rounded-full ${roleConfig.dotColor}`} />
                  {roleConfig.label}
                </button>
              ))}
            </div>
            {errors.role && (
              <p className="mt-1 text-sm text-red-600">{errors.role}</p>
            )}
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={handleClose}
            className="px-4 py-2 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Adding...
              </>
            ) : (
              <>
                <Plus className="h-4 w-4" />
                Add Team Member
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

// ─── Edit Role Dropdown ──────────────────────────────

function EditRoleDropdown({ member, onRoleChange, canEditToAdmin, saving }) {
  const [open, setOpen] = useState(false);

  const availableRoles = canEditToAdmin
    ? Object.entries(ROLE_CONFIG)
    : Object.entries(ROLE_CONFIG).filter(([key]) => key !== 'fsm_administrator');

  const handleSelect = async (roleKey) => {
    if (roleKey === member.role) {
      setOpen(false);
      return;
    }
    setOpen(false);
    await onRoleChange(member.id, roleKey);
  };

  return (
    <div className="relative">
      <button
        onClick={() => setOpen(!open)}
        disabled={saving}
        className="inline-flex items-center gap-1 p-1.5 text-gray-600 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors disabled:opacity-50"
        title="Change role"
      >
        {saving ? (
          <Loader2 className="h-4 w-4 animate-spin" />
        ) : (
          <>
            <Pencil className="h-3.5 w-3.5" />
            <ChevronDown className="h-3 w-3" />
          </>
        )}
      </button>

      {open && (
        <>
          <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
          <div className="absolute right-0 mt-1 w-48 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-20">
            {availableRoles.map(([roleKey, roleConfig]) => (
              <button
                key={roleKey}
                onClick={() => handleSelect(roleKey)}
                className={`w-full flex items-center gap-2 px-4 py-2 text-sm transition-colors ${
                  member.role === roleKey
                    ? 'bg-primary-50 text-primary-700 font-medium'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}
              >
                <span className={`w-2 h-2 rounded-full ${roleConfig.dotColor}`} />
                {roleConfig.label}
                {member.role === roleKey && <Check className="h-4 w-4 ml-auto" />}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

// ─── Add Vehicle Modal ───────────────────────────────

function AddVehicleModal({ isOpen, onClose, onSuccess }) {
  const [formData, setFormData] = useState({ ...EMPTY_VEHICLE_FORM });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [errors, setErrors] = useState({});

  const updateField = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors((prev) => ({ ...prev, [field]: null }));
    }
  };

  const validate = () => {
    const newErrors = {};

    if (!formData.make.trim()) {
      newErrors.make = 'Make is required';
    }
    if (!formData.model.trim()) {
      newErrors.model = 'Model is required';
    }
    if (!formData.year || formData.year < 1900 || formData.year > new Date().getFullYear() + 2) {
      newErrors.year = 'Enter a valid year';
    }
    if (!formData.plate_number.trim()) {
      newErrors.plate_number = 'Plate number is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate()) return;

    setSubmitting(true);
    setError(null);

    try {
      await vehicles.create({ ...formData, plate: formData.plate_number });
      onSuccess?.();
      onClose();
      setFormData({ ...EMPTY_VEHICLE_FORM });
      setErrors({});
    } catch (err) {
      console.error('Failed to add vehicle:', err);
      setError(err.response?.data?.message || 'Failed to add vehicle');
    } finally {
      setSubmitting(false);
    }
  };

  const handleClose = () => {
    setFormData({ ...EMPTY_VEHICLE_FORM });
    setErrors({});
    setError(null);
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title="Add Vehicle" size="md">
      <form onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        <div className="space-y-4">
          {/* Make and Model */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Make <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.make}
                onChange={(e) => updateField('make', e.target.value)}
                placeholder="Ford"
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.make
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.make && (
                <p className="mt-1 text-sm text-red-600">{errors.make}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Model <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.model}
                onChange={(e) => updateField('model', e.target.value)}
                placeholder="Transit"
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.model
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.model && (
                <p className="mt-1 text-sm text-red-600">{errors.model}</p>
              )}
            </div>
          </div>

          {/* Year and Plate */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Year <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                value={formData.year}
                onChange={(e) => updateField('year', parseInt(e.target.value, 10) || '')}
                min="1900"
                max={new Date().getFullYear() + 2}
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.year
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.year && (
                <p className="mt-1 text-sm text-red-600">{errors.year}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Plate Number <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.plate_number}
                onChange={(e) => updateField('plate_number', e.target.value.toUpperCase())}
                placeholder="ABC-1234"
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all font-mono ${
                  errors.plate_number
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.plate_number && (
                <p className="mt-1 text-sm text-red-600">{errors.plate_number}</p>
              )}
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={handleClose}
            className="px-4 py-2 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Adding...
              </>
            ) : (
              <>
                <Plus className="h-4 w-4" />
                Add Vehicle
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

// ─── Edit Vehicle Modal ──────────────────────────────

function EditVehicleModal({ isOpen, onClose, vehicle, onSuccess }) {
  const [formData, setFormData] = useState({
    make: '',
    model: '',
    year: new Date().getFullYear(),
    plate_number: '',
    status: 'active',
  });
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    if (vehicle) {
      setFormData({
        make: vehicle.make || '',
        model: vehicle.model || '',
        year: vehicle.year || new Date().getFullYear(),
        plate_number: vehicle.plate_number || vehicle.plate || '',
        status: vehicle.status || 'active',
      });
    }
  }, [vehicle]);

  const updateField = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors((prev) => ({ ...prev, [field]: null }));
    }
  };

  const validate = () => {
    const newErrors = {};

    if (!formData.make.trim()) {
      newErrors.make = 'Make is required';
    }
    if (!formData.model.trim()) {
      newErrors.model = 'Model is required';
    }
    if (!formData.year || formData.year < 1900 || formData.year > new Date().getFullYear() + 2) {
      newErrors.year = 'Enter a valid year';
    }
    if (!formData.plate_number.trim()) {
      newErrors.plate_number = 'Plate number is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validate() || !vehicle) return;

    setSubmitting(true);
    setError(null);

    try {
      await vehicles.update(vehicle.id, { ...formData, plate: formData.plate_number });
      onSuccess?.();
      onClose();
    } catch (err) {
      console.error('Failed to update vehicle:', err);
      setError(err.response?.data?.message || 'Failed to update vehicle');
    } finally {
      setSubmitting(false);
    }
  };

  const handleClose = () => {
    setErrors({});
    setError(null);
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title="Edit Vehicle" size="md">
      <form onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        <div className="space-y-4">
          {/* Make and Model */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Make <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.make}
                onChange={(e) => updateField('make', e.target.value)}
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.make
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.make && (
                <p className="mt-1 text-sm text-red-600">{errors.make}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Model <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.model}
                onChange={(e) => updateField('model', e.target.value)}
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.model
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.model && (
                <p className="mt-1 text-sm text-red-600">{errors.model}</p>
              )}
            </div>
          </div>

          {/* Year and Plate */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Year <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                value={formData.year}
                onChange={(e) => updateField('year', parseInt(e.target.value, 10) || '')}
                min="1900"
                max={new Date().getFullYear() + 2}
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.year
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.year && (
                <p className="mt-1 text-sm text-red-600">{errors.year}</p>
              )}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Plate Number <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.plate_number}
                onChange={(e) => updateField('plate_number', e.target.value.toUpperCase())}
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all font-mono ${
                  errors.plate_number
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.plate_number && (
                <p className="mt-1 text-sm text-red-600">{errors.plate_number}</p>
              )}
            </div>
          </div>

          {/* Status */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Status
            </label>
            <div className="flex gap-2">
              {Object.entries(VEHICLE_STATUS_CONFIG).map(([key, config]) => (
                <button
                  key={key}
                  type="button"
                  onClick={() => updateField('status', key)}
                  className={`flex-1 px-4 py-2.5 rounded-lg border-2 text-sm font-medium transition-all ${
                    formData.status === key
                      ? 'border-primary-500 bg-primary-50 text-primary-700'
                      : 'border-gray-200 text-gray-700 hover:border-gray-300'
                  }`}
                >
                  {config.label}
                </button>
              ))}
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={handleClose}
            className="px-4 py-2 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Saving...
              </>
            ) : (
              <>
                <Check className="h-4 w-4" />
                Save Changes
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

// ─── Deactivate Confirmation Modal ───────────────────

function DeactivateConfirmModal({ isOpen, onClose, member, onConfirm }) {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const handleConfirm = async () => {
    if (!member) return;
    setSubmitting(true);
    setError(null);

    try {
      await team.deactivate(member.id);
      onConfirm?.();
      onClose();
    } catch (err) {
      console.error('Failed to deactivate team member:', err);
      setError(err.response?.data?.message || 'Failed to deactivate team member');
    } finally {
      setSubmitting(false);
    }
  };

  const handleClose = () => {
    setError(null);
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title="Deactivate Team Member" size="sm">
      <div className="space-y-4">
        <div className="flex items-start gap-3">
          <div className="p-2 bg-red-100 rounded-lg flex-shrink-0">
            <AlertTriangle className="h-5 w-5 text-red-600" />
          </div>
          <div>
            <p className="text-sm text-gray-700">
              Are you sure you want to deactivate{' '}
              <span className="font-semibold">
                {member?.display_name || `${member?.first_name} ${member?.last_name}`}
              </span>
              ? They will lose access to the system immediately.
            </p>
            <p className="text-sm text-gray-500 mt-2">
              This action can be reversed by an administrator.
            </p>
          </div>
        </div>

        {error && (
          <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={handleClose}
            className="px-4 py-2 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleConfirm}
            disabled={submitting}
            className="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Deactivating...
              </>
            ) : (
              <>
                <UserX className="h-4 w-4" />
                Deactivate
              </>
            )}
          </button>
        </div>
      </div>
    </Modal>
  );
}

// ─── Main Team Page ──────────────────────────────────

function TeamPage() {
  const { isAdmin, isDispatcher } = useAuth();

  // Tab state
  const [activeTab, setActiveTab] = useState('members');

  // Team Members state
  const [members, setMembers] = useState([]);
  const [membersLoading, setMembersLoading] = useState(true);
  const [memberSearch, setMemberSearch] = useState('');
  const [memberRoleFilter, setMemberRoleFilter] = useState('all');
  const [addMemberOpen, setAddMemberOpen] = useState(false);
  const [savingRoleFor, setSavingRoleFor] = useState(null);
  const [deactivateMember, setDeactivateMember] = useState(null);
  const [memberSuccess, setMemberSuccess] = useState(null);

  // Vehicles state
  const [vehicleList, setVehicleList] = useState([]);
  const [vehiclesLoading, setVehiclesLoading] = useState(true);
  const [vehicleSearch, setVehicleSearch] = useState('');
  const [addVehicleOpen, setAddVehicleOpen] = useState(false);
  const [editVehicle, setEditVehicle] = useState(null);
  const [vehicleSuccess, setVehicleSuccess] = useState(null);

  // ─── Data loaders ────────────────────────────────

  const loadMembers = useCallback(async () => {
    setMembersLoading(true);
    try {
      const { data } = await team.list();
      setMembers(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('Failed to load team members:', err);
    } finally {
      setMembersLoading(false);
    }
  }, []);

  const loadVehicles = useCallback(async () => {
    setVehiclesLoading(true);
    try {
      const { data } = await vehicles.list();
      setVehicleList(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('Failed to load vehicles:', err);
    } finally {
      setVehiclesLoading(false);
    }
  }, []);

  useEffect(() => {
    loadMembers();
    loadVehicles();
  }, [loadMembers, loadVehicles]);

  // ─── Handlers ────────────────────────────────────

  const handleRoleChange = async (memberId, newRole) => {
    setSavingRoleFor(memberId);
    try {
      await team.update(memberId, { role: newRole });
      setMembers((prev) =>
        prev.map((m) => (m.id === memberId ? { ...m, role: newRole } : m))
      );
      showMemberSuccess('Role updated successfully');
    } catch (err) {
      console.error('Failed to update role:', err);
    } finally {
      setSavingRoleFor(null);
    }
  };

  const handleAddMemberSuccess = () => {
    loadMembers();
    showMemberSuccess('Team member added. Login credentials have been emailed.');
  };

  const handleDeactivateSuccess = () => {
    loadMembers();
    showMemberSuccess('Team member deactivated');
  };

  const handleAddVehicleSuccess = () => {
    loadVehicles();
    showVehicleSuccess('Vehicle added successfully');
  };

  const handleEditVehicleSuccess = () => {
    loadVehicles();
    showVehicleSuccess('Vehicle updated successfully');
  };

  const showMemberSuccess = (msg) => {
    setMemberSuccess(msg);
    setTimeout(() => setMemberSuccess(null), 4000);
  };

  const showVehicleSuccess = (msg) => {
    setVehicleSuccess(msg);
    setTimeout(() => setVehicleSuccess(null), 4000);
  };

  // ─── Filtering ───────────────────────────────────

  const filteredMembers = members.filter((member) => {
    const name = (member.display_name || `${member.first_name || ''} ${member.last_name || ''}`).toLowerCase();
    const email = (member.email || '').toLowerCase();
    const phone = (member.phone || '').toLowerCase();
    const matchesSearch =
      !memberSearch ||
      name.includes(memberSearch.toLowerCase()) ||
      email.includes(memberSearch.toLowerCase()) ||
      phone.includes(memberSearch.toLowerCase());

    const matchesRole =
      memberRoleFilter === 'all' || member.role === memberRoleFilter;

    return matchesSearch && matchesRole;
  });

  const filteredVehicles = vehicleList.filter((vehicle) => {
    if (!vehicleSearch) return true;
    const query = vehicleSearch.toLowerCase();
    const name = (vehicle.name || vehicle.title || '').toLowerCase();
    const plate = (vehicle.plate_number || vehicle.plate || '').toLowerCase();
    const details = `${vehicle.year || ''} ${vehicle.make || ''} ${vehicle.model || ''}`.toLowerCase();
    const tech = (vehicle.assigned_technician?.name || vehicle.assigned_technicians?.map((t) => t.name).join(' ') || '').toLowerCase();
    return (
      name.includes(query) ||
      plate.includes(query) ||
      details.includes(query) ||
      tech.includes(query)
    );
  });

  // ─── Permission checks ──────────────────────────

  const canAddMembers = isAdmin || isDispatcher;
  const canAddAdmin = isAdmin;
  const canDeactivate = isAdmin;

  if (!isAdmin && !isDispatcher) {
    return (
      <div className="text-center py-12">
        <Shield className="h-12 w-12 mx-auto text-gray-300 mb-3" />
        <p className="text-gray-500">You don't have permission to manage the team.</p>
      </div>
    );
  }

  // ─── Summary stats ──────────────────────────────

  const memberStats = {
    total: members.length,
    admins: members.filter((m) => m.role === 'fsm_administrator').length,
    dispatchers: members.filter((m) => m.role === 'fsm_dispatcher').length,
    technicians: members.filter((m) => m.role === 'fsm_technician').length,
  };

  const vehicleStats = {
    total: vehicleList.length,
    active: vehicleList.filter((v) => v.status === 'active').length,
    maintenance: vehicleList.filter((v) => v.status === 'maintenance').length,
  };

  // ─── Tab definitions ────────────────────────────

  const tabs = [
    { id: 'members', label: 'Team Members', icon: Users, count: memberStats.total },
    { id: 'vehicles', label: 'Vehicles', icon: Truck, count: vehicleStats.total },
  ];

  // ─── Render ──────────────────────────────────────

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
          <Users className="h-6 w-6 mr-2" />
          Team Management
        </h1>

        <div className="flex items-center gap-2">
          <button
            onClick={() => {
              if (activeTab === 'members') loadMembers();
              else loadVehicles();
            }}
            disabled={activeTab === 'members' ? membersLoading : vehiclesLoading}
            className="p-2 hover:bg-gray-100 rounded-md transition-colors"
            title="Refresh"
          >
            <RefreshCw
              className={`h-5 w-5 text-gray-600 ${
                (activeTab === 'members' ? membersLoading : vehiclesLoading)
                  ? 'animate-spin'
                  : ''
              }`}
            />
          </button>

          {/* Add button depends on active tab */}
          {activeTab === 'members' && canAddMembers && (
            <button
              onClick={() => setAddMemberOpen(true)}
              className="btn btn-primary inline-flex items-center"
            >
              <Plus className="h-4 w-4 mr-2" />
              Add Team Member
            </button>
          )}
          {activeTab === 'vehicles' && (isAdmin || isDispatcher) && (
            <button
              onClick={() => setAddVehicleOpen(true)}
              className="btn btn-primary inline-flex items-center"
            >
              <Plus className="h-4 w-4 mr-2" />
              Add Vehicle
            </button>
          )}
        </div>
      </div>

      {/* Summary Stats */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-white rounded-xl shadow p-4">
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-gray-100 rounded-lg">
              <Users className="h-5 w-5 text-gray-600" />
            </div>
            <div>
              <p className="text-2xl font-semibold text-gray-900">{memberStats.total}</p>
              <p className="text-sm text-gray-500">Team Members</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl shadow p-4">
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-green-100 rounded-lg">
              <Users className="h-5 w-5 text-green-600" />
            </div>
            <div>
              <p className="text-2xl font-semibold text-gray-900">{memberStats.technicians}</p>
              <p className="text-sm text-gray-500">Technicians</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl shadow p-4">
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-green-100 rounded-lg">
              <Truck className="h-5 w-5 text-green-600" />
            </div>
            <div>
              <p className="text-2xl font-semibold text-gray-900">{vehicleStats.active}</p>
              <p className="text-sm text-gray-500">Active Vehicles</p>
            </div>
          </div>
        </div>
        <div className="bg-white rounded-xl shadow p-4">
          <div className="flex items-center gap-3">
            <div className="p-2.5 bg-yellow-100 rounded-lg">
              <Truck className="h-5 w-5 text-yellow-600" />
            </div>
            <div>
              <p className="text-2xl font-semibold text-gray-900">{vehicleStats.maintenance}</p>
              <p className="text-sm text-gray-500">In Maintenance</p>
            </div>
          </div>
        </div>
      </div>

      {/* Tab Switcher */}
      <div className="bg-white rounded-xl shadow">
        <div className="border-b border-gray-200">
          <nav className="flex -mb-px">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center gap-2 px-6 py-4 text-sm font-medium border-b-2 transition-colors ${
                  activeTab === tab.id
                    ? 'border-primary-500 text-primary-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                <tab.icon className="h-4 w-4" />
                {tab.label}
                <span
                  className={`ml-1 px-2 py-0.5 rounded-full text-xs font-medium ${
                    activeTab === tab.id
                      ? 'bg-primary-100 text-primary-700'
                      : 'bg-gray-100 text-gray-600'
                  }`}
                >
                  {tab.count}
                </span>
              </button>
            ))}
          </nav>
        </div>

        {/* Tab Content */}
        <div className="p-4 sm:p-6">
          {/* ═══════ TEAM MEMBERS TAB ═══════ */}
          {activeTab === 'members' && (
            <div className="space-y-4">
              {/* Success message */}
              {memberSuccess && (
                <div className="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                  <Check className="h-4 w-4 flex-shrink-0" />
                  {memberSuccess}
                </div>
              )}

              {/* Search and filter */}
              <div className="flex flex-col sm:flex-row gap-3">
                <div className="flex-1 relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <input
                    type="text"
                    placeholder="Search by name, email, or phone..."
                    value={memberSearch}
                    onChange={(e) => setMemberSearch(e.target.value)}
                    className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                  />
                </div>
                <div className="flex gap-2 flex-wrap">
                  <button
                    onClick={() => setMemberRoleFilter('all')}
                    className={`btn ${memberRoleFilter === 'all' ? 'btn-primary' : 'btn-secondary'}`}
                  >
                    All
                  </button>
                  {Object.entries(ROLE_CONFIG).map(([key, config]) => (
                    <button
                      key={key}
                      onClick={() => setMemberRoleFilter(key)}
                      className={`btn inline-flex items-center gap-1.5 ${
                        memberRoleFilter === key ? 'btn-primary' : 'btn-secondary'
                      }`}
                    >
                      <span className={`w-1.5 h-1.5 rounded-full ${config.dotColor}`} />
                      {config.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Members Table (Desktop) */}
              {membersLoading ? (
                <div className="flex items-center justify-center h-64">
                  <LoadingSpinner size="medium" />
                </div>
              ) : filteredMembers.length === 0 ? (
                <div className="text-center py-12">
                  <Users className="h-12 w-12 mx-auto text-gray-300 mb-3" />
                  <p className="text-gray-500">
                    {memberSearch || memberRoleFilter !== 'all'
                      ? 'No team members match your filters'
                      : 'No team members yet'}
                  </p>
                </div>
              ) : (
                <>
                  {/* Desktop table */}
                  <div className="hidden md:block overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Name
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Contact
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Vehicle
                          </th>
                          <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {filteredMembers.map((member) => (
                          <tr key={member.id} className="hover:bg-gray-50 transition-colors">
                            {/* Name */}
                            <td className="px-6 py-4">
                              <div className="flex items-center">
                                <div className="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 text-sm font-semibold flex-shrink-0">
                                  {(member.first_name || member.display_name || '?').charAt(0).toUpperCase()}
                                  {(member.last_name || '').charAt(0).toUpperCase()}
                                </div>
                                <div className="ml-3">
                                  <div className="text-sm font-medium text-gray-900">
                                    {member.display_name || `${member.first_name || ''} ${member.last_name || ''}`.trim()}
                                  </div>
                                </div>
                              </div>
                            </td>

                            {/* Contact */}
                            <td className="px-6 py-4">
                              <div className="text-sm text-gray-900 flex items-center gap-1.5">
                                <Mail className="h-3.5 w-3.5 text-gray-400 flex-shrink-0" />
                                {member.email}
                              </div>
                              {member.phone && (
                                <div className="text-sm text-gray-500 flex items-center gap-1.5 mt-0.5">
                                  <Phone className="h-3.5 w-3.5 text-gray-400 flex-shrink-0" />
                                  {member.phone}
                                </div>
                              )}
                            </td>

                            {/* Role */}
                            <td className="px-6 py-4 whitespace-nowrap">
                              <RoleBadge role={member.role} />
                            </td>

                            {/* Status */}
                            <td className="px-6 py-4 whitespace-nowrap">
                              <StatusBadge
                                status={member.status || 'active'}
                                configMap={STATUS_CONFIG}
                              />
                            </td>

                            {/* Vehicle (show for technicians) */}
                            <td className="px-6 py-4 whitespace-nowrap">
                              {member.role === 'fsm_technician' ? (
                                member.vehicle ? (
                                  <div className="flex items-center gap-1.5 text-sm text-gray-700">
                                    <Truck className="h-3.5 w-3.5 text-gray-400" />
                                    <span>
                                      {member.vehicle.name ||
                                        `${member.vehicle.year || ''} ${member.vehicle.make || ''} ${member.vehicle.model || ''}`.trim()}
                                    </span>
                                  </div>
                                ) : (
                                  <span className="text-sm text-gray-400 italic">Unassigned</span>
                                )
                              ) : (
                                <span className="text-sm text-gray-300">&mdash;</span>
                              )}
                            </td>

                            {/* Actions */}
                            <td className="px-6 py-4 whitespace-nowrap text-right">
                              <div className="flex items-center justify-end gap-1">
                                {(isAdmin || isDispatcher) && (
                                  <EditRoleDropdown
                                    member={member}
                                    onRoleChange={handleRoleChange}
                                    canEditToAdmin={canAddAdmin}
                                    saving={savingRoleFor === member.id}
                                  />
                                )}
                                {canDeactivate && member.status !== 'deactivated' && (
                                  <button
                                    onClick={() => setDeactivateMember(member)}
                                    className="p-1.5 text-gray-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                    title="Deactivate"
                                  >
                                    <UserX className="h-4 w-4" />
                                  </button>
                                )}
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Mobile cards */}
                  <div className="md:hidden space-y-3">
                    {filteredMembers.map((member) => (
                      <div
                        key={member.id}
                        className="bg-white border border-gray-200 rounded-lg p-4 space-y-3"
                      >
                        <div className="flex items-start justify-between">
                          <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 text-sm font-semibold flex-shrink-0">
                              {(member.first_name || member.display_name || '?').charAt(0).toUpperCase()}
                              {(member.last_name || '').charAt(0).toUpperCase()}
                            </div>
                            <div>
                              <div className="font-medium text-gray-900">
                                {member.display_name || `${member.first_name || ''} ${member.last_name || ''}`.trim()}
                              </div>
                              <RoleBadge role={member.role} />
                            </div>
                          </div>
                          <StatusBadge
                            status={member.status || 'active'}
                            configMap={STATUS_CONFIG}
                          />
                        </div>

                        <div className="space-y-1.5 text-sm">
                          <div className="flex items-center gap-2 text-gray-600">
                            <Mail className="h-3.5 w-3.5 text-gray-400" />
                            {member.email}
                          </div>
                          {member.phone && (
                            <div className="flex items-center gap-2 text-gray-600">
                              <Phone className="h-3.5 w-3.5 text-gray-400" />
                              {member.phone}
                            </div>
                          )}
                          {member.role === 'fsm_technician' && (
                            <div className="flex items-center gap-2 text-gray-600">
                              <Truck className="h-3.5 w-3.5 text-gray-400" />
                              {member.vehicle
                                ? member.vehicle.name ||
                                  `${member.vehicle.year || ''} ${member.vehicle.make || ''} ${member.vehicle.model || ''}`.trim()
                                : 'No vehicle assigned'}
                            </div>
                          )}
                        </div>

                        <div className="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                          {(isAdmin || isDispatcher) && (
                            <EditRoleDropdown
                              member={member}
                              onRoleChange={handleRoleChange}
                              canEditToAdmin={canAddAdmin}
                              saving={savingRoleFor === member.id}
                            />
                          )}
                          {canDeactivate && member.status !== 'deactivated' && (
                            <button
                              onClick={() => setDeactivateMember(member)}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            >
                              <UserX className="h-3.5 w-3.5" />
                              Deactivate
                            </button>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                </>
              )}
            </div>
          )}

          {/* ═══════ VEHICLES TAB ═══════ */}
          {activeTab === 'vehicles' && (
            <div className="space-y-4">
              {/* Success message */}
              {vehicleSuccess && (
                <div className="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                  <Check className="h-4 w-4 flex-shrink-0" />
                  {vehicleSuccess}
                </div>
              )}

              {/* Search */}
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="text"
                  placeholder="Search by name, plate, make/model, or technician..."
                  value={vehicleSearch}
                  onChange={(e) => setVehicleSearch(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                />
              </div>

              {/* Vehicles Table (Desktop) */}
              {vehiclesLoading ? (
                <div className="flex items-center justify-center h-64">
                  <LoadingSpinner size="medium" />
                </div>
              ) : filteredVehicles.length === 0 ? (
                <div className="text-center py-12">
                  <Truck className="h-12 w-12 mx-auto text-gray-300 mb-3" />
                  <p className="text-gray-500">
                    {vehicleSearch ? 'No vehicles match your search' : 'No vehicles yet'}
                  </p>
                </div>
              ) : (
                <>
                  {/* Desktop table */}
                  <div className="hidden md:block overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                      <thead className="bg-gray-50">
                        <tr>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Vehicle
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Plate
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Make / Model / Year
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                          </th>
                          <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Assigned To
                          </th>
                          <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                          </th>
                        </tr>
                      </thead>
                      <tbody className="bg-white divide-y divide-gray-200">
                        {filteredVehicles.map((vehicle) => {
                          const assignedTechs = vehicle.assigned_technicians || (vehicle.assigned_technician ? [vehicle.assigned_technician] : []);
                          return (
                            <tr key={vehicle.id} className="hover:bg-gray-50 transition-colors">
                              {/* Vehicle Name */}
                              <td className="px-6 py-4">
                                <div className="flex items-center gap-3">
                                  <div className="p-2 bg-gray-100 rounded-lg flex-shrink-0">
                                    <Truck className="h-5 w-5 text-gray-600" />
                                  </div>
                                  <span className="text-sm font-medium text-gray-900">
                                    {vehicle.name || vehicle.title || `${vehicle.make} ${vehicle.model}`}
                                  </span>
                                </div>
                              </td>

                              {/* Plate */}
                              <td className="px-6 py-4 whitespace-nowrap">
                                <span className="text-sm font-mono text-gray-700">
                                  {vehicle.plate_number || vehicle.plate || '-'}
                                </span>
                              </td>

                              {/* Make/Model/Year */}
                              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                {vehicle.year} {vehicle.make} {vehicle.model}
                              </td>

                              {/* Status */}
                              <td className="px-6 py-4 whitespace-nowrap">
                                <StatusBadge
                                  status={vehicle.status || 'active'}
                                  configMap={VEHICLE_STATUS_CONFIG}
                                />
                              </td>

                              {/* Assigned Technician(s) */}
                              <td className="px-6 py-4">
                                {assignedTechs.length > 0 ? (
                                  <div className="space-y-1">
                                    {assignedTechs.map((tech, idx) => (
                                      <div
                                        key={tech.id || idx}
                                        className="flex items-center gap-1.5 text-sm text-gray-700"
                                      >
                                        <div className="w-5 h-5 rounded-full bg-green-100 flex items-center justify-center text-green-700 text-[10px] font-semibold flex-shrink-0">
                                          {(tech.name || '?').charAt(0).toUpperCase()}
                                        </div>
                                        {tech.name}
                                      </div>
                                    ))}
                                  </div>
                                ) : (
                                  <span className="text-sm text-gray-400 italic">Unassigned</span>
                                )}
                              </td>

                              {/* Actions */}
                              <td className="px-6 py-4 whitespace-nowrap text-right">
                                <button
                                  onClick={() => setEditVehicle(vehicle)}
                                  className="p-1.5 text-gray-600 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                                  title="Edit vehicle"
                                >
                                  <Pencil className="h-4 w-4" />
                                </button>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>

                  {/* Mobile cards */}
                  <div className="md:hidden space-y-3">
                    {filteredVehicles.map((vehicle) => {
                      const assignedTechs = vehicle.assigned_technicians || (vehicle.assigned_technician ? [vehicle.assigned_technician] : []);
                      return (
                        <div
                          key={vehicle.id}
                          className="bg-white border border-gray-200 rounded-lg p-4 space-y-3"
                        >
                          <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                              <div className="p-2.5 bg-gray-100 rounded-lg">
                                <Truck className="h-5 w-5 text-gray-600" />
                              </div>
                              <div>
                                <div className="font-medium text-gray-900">
                                  {vehicle.name || vehicle.title || `${vehicle.make} ${vehicle.model}`}
                                </div>
                                <div className="text-sm text-gray-500">
                                  {vehicle.year} {vehicle.make} {vehicle.model}
                                </div>
                              </div>
                            </div>
                            <StatusBadge
                              status={vehicle.status || 'active'}
                              configMap={VEHICLE_STATUS_CONFIG}
                            />
                          </div>

                          <div className="space-y-1.5 text-sm">
                            <div className="flex items-center justify-between">
                              <span className="text-gray-500">Plate</span>
                              <span className="font-mono text-gray-700">
                                {vehicle.plate_number || vehicle.plate || '-'}
                              </span>
                            </div>
                            <div className="flex items-center justify-between">
                              <span className="text-gray-500">Assigned</span>
                              <span className="text-gray-700">
                                {assignedTechs.length > 0
                                  ? assignedTechs.map((t) => t.name).join(', ')
                                  : 'Unassigned'}
                              </span>
                            </div>
                          </div>

                          <div className="flex items-center justify-end pt-2 border-t border-gray-100">
                            <button
                              onClick={() => setEditVehicle(vehicle)}
                              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                            >
                              <Pencil className="h-3.5 w-3.5" />
                              Edit
                            </button>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Modals */}
      <AddTeamMemberModal
        isOpen={addMemberOpen}
        onClose={() => setAddMemberOpen(false)}
        onSuccess={handleAddMemberSuccess}
        canAddAdmin={canAddAdmin}
      />

      <DeactivateConfirmModal
        isOpen={!!deactivateMember}
        onClose={() => setDeactivateMember(null)}
        member={deactivateMember}
        onConfirm={handleDeactivateSuccess}
      />

      <AddVehicleModal
        isOpen={addVehicleOpen}
        onClose={() => setAddVehicleOpen(false)}
        onSuccess={handleAddVehicleSuccess}
      />

      <EditVehicleModal
        isOpen={!!editVehicle}
        onClose={() => setEditVehicle(null)}
        vehicle={editVehicle}
        onSuccess={handleEditVehicleSuccess}
      />
    </div>
  );
}

export default TeamPage;
