import { useState } from 'react';
import { inventory } from '../../api/client';
import { Plus, Loader2, Package } from 'lucide-react';
import Modal from '../common/Modal';

const CATEGORIES = [
  'Parts',
  'Tools',
  'Supplies',
  'Equipment',
  'Safety',
  'Consumables',
  'Other',
];

const UNITS = [
  'each',
  'box',
  'pack',
  'roll',
  'ft',
  'm',
  'kg',
  'lb',
  'gal',
  'L',
];

export default function AddInventoryModal({
  isOpen,
  onClose,
  onSuccess,
}) {
  const [formData, setFormData] = useState({
    sku: '',
    name: '',
    description: '',
    category: '',
    quantity: 0,
    unit: 'each',
    unit_cost: 0,
    minimum_quantity: 0,
    location_type: 'warehouse',
    location_id: 0,
  });
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

    if (!formData.sku.trim()) {
      newErrors.sku = 'SKU is required';
    }

    if (!formData.name.trim()) {
      newErrors.name = 'Name is required';
    }

    if (formData.quantity < 0) {
      newErrors.quantity = 'Quantity cannot be negative';
    }

    if (formData.unit_cost < 0) {
      newErrors.unit_cost = 'Cost cannot be negative';
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
      await inventory.create(formData);
      onSuccess?.();
      onClose();
      // Reset form
      setFormData({
        sku: '',
        name: '',
        description: '',
        category: '',
        quantity: 0,
        unit: 'each',
        unit_cost: 0,
        minimum_quantity: 0,
        location_type: 'warehouse',
        location_id: 0,
      });
    } catch (err) {
      console.error('Failed to add inventory:', err);
      setError(err.response?.data?.message || 'Failed to add inventory item');
    } finally {
      setSubmitting(false);
    }
  };

  const generateSKU = () => {
    const prefix = formData.category
      ? formData.category.substring(0, 3).toUpperCase()
      : 'ITM';
    const random = Math.random().toString(36).substring(2, 8).toUpperCase();
    updateField('sku', `${prefix}-${random}`);
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Add Inventory Item" size="lg">
      <form onSubmit={handleSubmit}>
        {/* Error */}
        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        <div className="space-y-5">
          {/* SKU and Name */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                SKU <span className="text-red-500">*</span>
              </label>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={formData.sku}
                  onChange={(e) => updateField('sku', e.target.value.toUpperCase())}
                  placeholder="SKU-001"
                  className={`flex-1 px-4 py-2.5 border-2 rounded-lg outline-none transition-all font-mono ${
                    errors.sku
                      ? 'border-red-300 focus:border-red-500'
                      : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                  }`}
                />
                <button
                  type="button"
                  onClick={generateSKU}
                  className="px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium transition-colors"
                  title="Generate SKU"
                >
                  Auto
                </button>
              </div>
              {errors.sku && (
                <p className="mt-1 text-sm text-red-600">{errors.sku}</p>
              )}
            </div>

            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Name <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.name}
                onChange={(e) => updateField('name', e.target.value)}
                placeholder="Item name"
                className={`w-full px-4 py-2.5 border-2 rounded-lg outline-none transition-all ${
                  errors.name
                    ? 'border-red-300 focus:border-red-500'
                    : 'border-gray-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20'
                }`}
              />
              {errors.name && (
                <p className="mt-1 text-sm text-red-600">{errors.name}</p>
              )}
            </div>
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Description
            </label>
            <textarea
              value={formData.description}
              onChange={(e) => updateField('description', e.target.value)}
              placeholder="Item description..."
              rows={2}
              className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all resize-none"
            />
          </div>

          {/* Category */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Category
            </label>
            <div className="flex flex-wrap gap-2">
              {CATEGORIES.map((cat) => (
                <button
                  key={cat}
                  type="button"
                  onClick={() => updateField('category', formData.category === cat ? '' : cat)}
                  className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
                    formData.category === cat
                      ? 'bg-primary-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  {cat}
                </button>
              ))}
            </div>
          </div>

          {/* Quantity and Unit */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Initial Quantity
              </label>
              <input
                type="number"
                value={formData.quantity}
                onChange={(e) => updateField('quantity', parseInt(e.target.value) || 0)}
                min="0"
                className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Unit
              </label>
              <select
                value={formData.unit}
                onChange={(e) => updateField('unit', e.target.value)}
                className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all bg-white"
              >
                {UNITS.map((unit) => (
                  <option key={unit} value={unit}>
                    {unit}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Unit Cost ($)
              </label>
              <input
                type="number"
                value={formData.unit_cost}
                onChange={(e) => updateField('unit_cost', parseFloat(e.target.value) || 0)}
                min="0"
                step="0.01"
                className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Min Quantity
              </label>
              <input
                type="number"
                value={formData.minimum_quantity}
                onChange={(e) => updateField('minimum_quantity', parseInt(e.target.value) || 0)}
                min="0"
                className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
              />
            </div>
          </div>

          {/* Location */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Location
            </label>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => updateField('location_type', 'warehouse')}
                className={`flex-1 px-4 py-3 rounded-lg border-2 font-medium transition-all ${
                  formData.location_type === 'warehouse'
                    ? 'border-primary-500 bg-primary-50 text-primary-700'
                    : 'border-gray-200 text-gray-700 hover:border-gray-300'
                }`}
              >
                <Package className="h-5 w-5 mx-auto mb-1" />
                Warehouse
              </button>
              <button
                type="button"
                onClick={() => updateField('location_type', 'vehicle')}
                className={`flex-1 px-4 py-3 rounded-lg border-2 font-medium transition-all ${
                  formData.location_type === 'vehicle'
                    ? 'border-primary-500 bg-primary-50 text-primary-700'
                    : 'border-gray-200 text-gray-700 hover:border-gray-300'
                }`}
              >
                🚐
                <span className="block text-sm mt-1">Vehicle</span>
              </button>
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={onClose}
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
                Add Item
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}
