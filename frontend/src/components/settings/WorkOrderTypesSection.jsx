import { useState, useEffect } from 'react';
import { workOrderTypes } from '../../api/client';
import {
  Plus,
  Pencil,
  Trash2,
  Check,
  X,
  Clock,
  Loader2,
  AlertTriangle,
} from 'lucide-react';

const COLOR_PRESETS = [
  '#ef4444', '#f97316', '#f59e0b', '#10b981',
  '#06b6d4', '#3b82f6', '#8b5cf6', '#ec4899',
  '#6b7280', '#78716c',
];

const ICON_OPTIONS = [
  { value: 'wrench', label: 'Wrench' },
  { value: 'package', label: 'Package' },
  { value: 'tool', label: 'Tool' },
  { value: 'search', label: 'Search' },
  { value: 'alert-triangle', label: 'Alert' },
  { value: 'message-circle', label: 'Message' },
  { value: 'zap', label: 'Zap' },
  { value: 'shield', label: 'Shield' },
  { value: 'settings', label: 'Settings' },
  { value: 'clipboard', label: 'Clipboard' },
];

function TypeRow({ type, onEdit, onDelete, deleting }) {
  return (
    <div className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg group">
      <div
        className="w-4 h-4 rounded-full flex-shrink-0 ring-2 ring-offset-1"
        style={{ backgroundColor: type.color, ringColor: type.color + '60' }}
      />
      <div className="flex-1 min-w-0">
        <p className="font-medium text-gray-900">{type.name}</p>
        <p className="text-xs text-gray-500">
          Default: {type.default_duration}min
        </p>
      </div>
      <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
        <button
          onClick={() => onEdit(type)}
          className="p-1.5 hover:bg-gray-200 rounded transition-colors"
          title="Edit"
        >
          <Pencil className="h-4 w-4 text-gray-500" />
        </button>
        <button
          onClick={() => onDelete(type)}
          disabled={deleting}
          className="p-1.5 hover:bg-red-100 rounded transition-colors disabled:opacity-50"
          title="Delete"
        >
          <Trash2 className="h-4 w-4 text-red-500" />
        </button>
      </div>
    </div>
  );
}

function TypeForm({ initial, onSave, onCancel, saving }) {
  const [form, setForm] = useState({
    name: initial?.name || '',
    default_duration: initial?.default_duration || 60,
    color: initial?.color || '#3b82f6',
    icon: initial?.icon || 'wrench',
  });
  const [error, setError] = useState('');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!form.name.trim()) {
      setError('Name is required');
      return;
    }
    setError('');
    onSave(form);
  };

  return (
    <form onSubmit={handleSubmit} className="p-4 bg-white border-2 border-primary-200 rounded-lg space-y-4">
      {error && (
        <div className="flex items-center gap-2 text-sm text-red-600">
          <AlertTriangle className="h-4 w-4" />
          {error}
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
          <input
            type="text"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            placeholder="e.g. Repair, Installation..."
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
            autoFocus
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            <Clock className="inline h-3.5 w-3.5 mr-1" />
            Default Duration (minutes)
          </label>
          <input
            type="number"
            value={form.default_duration}
            onChange={(e) => setForm({ ...form, default_duration: parseInt(e.target.value) || 0 })}
            min="5"
            step="5"
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
          />
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">Color</label>
        <div className="flex flex-wrap gap-2">
          {COLOR_PRESETS.map((c) => (
            <button
              key={c}
              type="button"
              onClick={() => setForm({ ...form, color: c })}
              className={`w-8 h-8 rounded-full transition-all ${
                form.color === c ? 'ring-2 ring-offset-2 scale-110' : 'hover:scale-105'
              }`}
              style={{ backgroundColor: c, ringColor: c }}
            />
          ))}
          <input
            type="color"
            value={form.color}
            onChange={(e) => setForm({ ...form, color: e.target.value })}
            className="w-8 h-8 rounded-full cursor-pointer border-0 p-0"
            title="Custom color"
          />
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-2">Icon</label>
        <div className="flex flex-wrap gap-2">
          {ICON_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => setForm({ ...form, icon: opt.value })}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition-all ${
                form.icon === opt.value
                  ? 'border-primary-500 bg-primary-50 text-primary-700'
                  : 'border-gray-200 text-gray-600 hover:border-gray-300'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      {/* Preview */}
      <div className="flex items-center gap-2 pt-2 border-t border-gray-100">
        <span className="text-xs text-gray-500">Preview:</span>
        <span
          className="text-xs px-2 py-1 rounded font-medium border"
          style={{
            backgroundColor: `${form.color}15`,
            color: form.color,
            borderColor: `${form.color}40`,
          }}
        >
          {form.name || 'Type Name'}
        </span>
        <span className="text-xs text-gray-400">({form.default_duration}min)</span>
      </div>

      <div className="flex justify-end gap-2 pt-2">
        <button
          type="button"
          onClick={onCancel}
          className="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          Cancel
        </button>
        <button
          type="submit"
          disabled={saving}
          className="px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-50 inline-flex items-center gap-2"
        >
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
          {initial ? 'Update' : 'Create'}
        </button>
      </div>
    </form>
  );
}

export default function WorkOrderTypesSection() {
  const [types, setTypes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState(null); // null | 'new' | type object
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(null); // type id being deleted

  useEffect(() => {
    loadTypes();
  }, []);

  const loadTypes = async () => {
    try {
      const { data } = await workOrderTypes.list();
      setTypes(data);
    } catch (err) {
      setError('Failed to load work order types');
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = async (form) => {
    setSaving(true);
    setError('');
    try {
      const { data } = await workOrderTypes.create(form);
      setTypes((prev) => [...prev, data]);
      setEditing(null);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to create type');
    } finally {
      setSaving(false);
    }
  };

  const handleUpdate = async (form) => {
    setSaving(true);
    setError('');
    try {
      const { data } = await workOrderTypes.update(editing.id, form);
      setTypes((prev) => prev.map((t) => (t.id === data.id ? data : t)));
      setEditing(null);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update type');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (type) => {
    if (!confirm(`Delete "${type.name}"? This cannot be undone.`)) return;
    setDeleting(type.id);
    setError('');
    try {
      await workOrderTypes.delete(type.id);
      setTypes((prev) => prev.filter((t) => t.id !== type.id));
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to delete type');
    } finally {
      setDeleting(null);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-gray-500">
          Configure the types of work orders your team handles. Each type has a default estimated duration that auto-fills when creating work orders.
        </p>
        {!editing && (
          <button
            onClick={() => setEditing('new')}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-primary-600 hover:bg-primary-50 rounded-lg transition-colors whitespace-nowrap"
          >
            <Plus className="h-4 w-4" />
            Add Type
          </button>
        )}
      </div>

      {error && (
        <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          <AlertTriangle className="h-4 w-4 flex-shrink-0" />
          {error}
          <button onClick={() => setError('')} className="ml-auto">
            <X className="h-4 w-4" />
          </button>
        </div>
      )}

      {/* Existing types */}
      <div className="space-y-2">
        {types.map((type) =>
          editing?.id === type.id ? (
            <TypeForm
              key={type.id}
              initial={type}
              onSave={handleUpdate}
              onCancel={() => setEditing(null)}
              saving={saving}
            />
          ) : (
            <TypeRow
              key={type.id}
              type={type}
              onEdit={(t) => setEditing(t)}
              onDelete={handleDelete}
              deleting={deleting === type.id}
            />
          )
        )}
      </div>

      {/* Add new form */}
      {editing === 'new' && (
        <TypeForm
          onSave={handleCreate}
          onCancel={() => setEditing(null)}
          saving={saving}
        />
      )}

      {types.length === 0 && !editing && (
        <div className="text-center py-8 text-gray-500">
          <p>No work order types configured yet.</p>
          <button
            onClick={() => setEditing('new')}
            className="mt-2 text-primary-600 hover:text-primary-700 font-medium text-sm"
          >
            Create your first type
          </button>
        </div>
      )}
    </div>
  );
}
