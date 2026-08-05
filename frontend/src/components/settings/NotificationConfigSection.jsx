import { useState, useEffect } from 'react';
import { settings as settingsApi } from '../../api/client';
import { Mail, Loader2, Check, AlertCircle, Send } from 'lucide-react';

function ToggleSetting({ label, description, checked, onChange }) {
  return (
    <label className="flex items-center justify-between py-3 cursor-pointer">
      <div>
        <p className="font-medium text-gray-900 text-sm">{label}</p>
        {description && <p className="text-xs text-gray-500">{description}</p>}
      </div>
      <button
        type="button"
        onClick={() => onChange(!checked)}
        className={`relative w-11 h-6 rounded-full transition-colors ${
          checked ? 'bg-primary-600' : 'bg-gray-200'
        }`}
      >
        <span
          className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${
            checked ? 'left-6' : 'left-1'
          }`}
        />
      </button>
    </label>
  );
}

export default function NotificationConfigSection() {
  const [config, setConfig] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadConfig();
  }, []);

  const loadConfig = async () => {
    try {
      const { data } = await settingsApi.getNotificationConfig();
      setConfig(data);
    } catch (err) {
      console.error('Failed to load notification config:', err);
      setError('Failed to load settings');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    setSaved(false);

    try {
      await settingsApi.updateNotificationConfig(config);
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to save settings');
    } finally {
      setSaving(false);
    }
  };

  const update = (key, value) => {
    setConfig((prev) => ({ ...prev, [key]: value }));
    setSaved(false);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
      </div>
    );
  }

  if (!config) {
    return (
      <p className="text-sm text-red-500 py-4">Failed to load notification settings.</p>
    );
  }

  const statusToggles = [
    { key: 'send_on_assigned', label: 'Technician Assigned', desc: 'Customer notified when a tech is assigned' },
    { key: 'send_on_en_route', label: 'Technician En Route', desc: 'Customer notified when tech leaves for the job' },
    { key: 'send_on_in_progress', label: 'Work Started', desc: 'Customer notified when work begins' },
    { key: 'send_on_completed', label: 'Work Completed', desc: 'Customer notified when the job is done' },
    { key: 'send_on_on_hold', label: 'On Hold', desc: 'Customer notified if the job is paused' },
    { key: 'send_on_cancelled', label: 'Cancelled', desc: 'Customer notified if the job is cancelled' },
  ];

  return (
    <div className="space-y-6">
      {error && (
        <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          <AlertCircle className="h-4 w-4 shrink-0" />
          {error}
        </div>
      )}

      {/* Master toggle */}
      <div className="p-4 bg-gray-50 rounded-lg border border-gray-200">
        <ToggleSetting
          label="Customer Email Notifications"
          description="Send automated email updates to customers on work order status changes"
          checked={config.enabled}
          onChange={(v) => update('enabled', v)}
        />
      </div>

      {config.enabled && (
        <>
          {/* Status triggers */}
          <div>
            <h4 className="text-sm font-semibold text-gray-700 mb-2">Send Email When Status Changes To:</h4>
            <div className="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden bg-white">
              {statusToggles.map((item) => (
                <div key={item.key} className="px-4">
                  <ToggleSetting
                    label={item.label}
                    description={item.desc}
                    checked={config[item.key] ?? true}
                    onChange={(v) => update(item.key, v)}
                  />
                </div>
              ))}
            </div>
          </div>

          {/* Email sender config */}
          <div>
            <h4 className="text-sm font-semibold text-gray-700 mb-3">Email Sender Settings</h4>
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">From Name</label>
                  <input
                    type="text"
                    value={config.from_name || ''}
                    onChange={(e) => update('from_name', e.target.value)}
                    placeholder="Your Company Name"
                    className="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 mb-1">From Email</label>
                  <input
                    type="email"
                    value={config.from_email || ''}
                    onChange={(e) => update('from_email', e.target.value)}
                    placeholder="noreply@example.com"
                    className="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Reply-To Email (optional)</label>
                <input
                  type="email"
                  value={config.reply_to || ''}
                  onChange={(e) => update('reply_to', e.target.value)}
                  placeholder="support@example.com"
                  className="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
                />
                <p className="text-xs text-gray-400 mt-1">When customers reply to notification emails, replies go here.</p>
              </div>
            </div>
          </div>
        </>
      )}

      {/* Save button */}
      <div className="flex items-center justify-between pt-4 border-t border-gray-100">
        <div>
          {saved && (
            <span className="text-sm text-green-600 flex items-center gap-1">
              <Check className="h-4 w-4" /> Saved
            </span>
          )}
        </div>
        <button
          onClick={handleSave}
          disabled={saving}
          className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 transition-colors"
        >
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Mail className="h-4 w-4" />}
          Save Notification Settings
        </button>
      </div>
    </div>
  );
}
