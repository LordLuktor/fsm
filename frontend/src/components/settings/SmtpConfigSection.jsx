import { useState, useEffect } from 'react';
import { settings as settingsApi } from '../../api/client';
import {
  Save,
  Send,
  Loader2,
  Check,
  AlertTriangle,
  Eye,
  EyeOff,
  Lock,
} from 'lucide-react';

const ENCRYPTION_OPTIONS = [
  { value: 'ssl', label: 'SSL (port 465)' },
  { value: 'tls', label: 'TLS (port 587)' },
  { value: '', label: 'None (port 25)' },
];

export default function SmtpConfigSection() {
  const [config, setConfig] = useState({
    host: '',
    port: 465,
    encryption: 'ssl',
    username: '',
    password: '',
    from_name: '',
    from_email: '',
  });
  const [passwordSet, setPasswordSet] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [testing, setTesting] = useState(false);
  const [testEmail, setTestEmail] = useState('');
  const [testResult, setTestResult] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    loadConfig();
  }, []);

  const loadConfig = async () => {
    try {
      const { data } = await settingsApi.getSmtpConfig();
      setConfig({
        host: data.host || '',
        port: data.port || 465,
        encryption: data.encryption || 'ssl',
        username: data.username || '',
        password: '', // Never returned from API
        from_name: data.from_name || '',
        from_email: data.from_email || '',
      });
      setPasswordSet(data.password_set || false);
    } catch (err) {
      setError('Failed to load SMTP settings');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    setSaved(false);
    setError('');

    try {
      const payload = { ...config };
      // Only send password if user typed a new one
      if (!payload.password && passwordSet) {
        delete payload.password;
      }

      const { data } = await settingsApi.updateSmtpConfig(payload);
      setPasswordSet(data.settings?.password_set || false);
      setConfig((prev) => ({ ...prev, password: '' }));
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to save settings');
    } finally {
      setSaving(false);
    }
  };

  const handleTest = async () => {
    if (!testEmail) return;
    setTesting(true);
    setTestResult(null);

    try {
      const { data } = await settingsApi.testSmtp(testEmail);
      setTestResult({ success: true, message: data.message });
    } catch (err) {
      setTestResult({
        success: false,
        message: err.response?.data?.message || 'Test failed',
      });
    } finally {
      setTesting(false);
    }
  };

  const updateField = (field, value) => {
    setConfig((prev) => ({ ...prev, [field]: value }));
    setSaved(false);

    // Auto-adjust port when encryption changes
    if (field === 'encryption') {
      const ports = { ssl: 465, tls: 587, '': 25 };
      setConfig((prev) => ({ ...prev, [field]: value, port: ports[value] || prev.port }));
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-6 w-6 animate-spin text-gray-400" />
      </div>
    );
  }

  const isConfigured = config.host && config.username;

  return (
    <div className="space-y-6">
      <p className="text-sm text-gray-500">
        Configure your company's email server to send notifications from your own domain.
        Customer notifications (work order updates, appointment reminders) will be sent from this account.
      </p>

      {error && (
        <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          <AlertTriangle className="h-4 w-4 flex-shrink-0" />
          {error}
        </div>
      )}

      {/* Server Settings */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="sm:col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">SMTP Server</label>
          <input
            type="text"
            value={config.host}
            onChange={(e) => updateField('host', e.target.value)}
            placeholder="smtp.yourcompany.com"
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Port</label>
          <input
            type="number"
            value={config.port}
            onChange={(e) => updateField('port', parseInt(e.target.value) || 0)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
          />
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
        <div className="flex gap-2">
          {ENCRYPTION_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => updateField('encryption', opt.value)}
              className={`px-4 py-2 rounded-lg text-sm font-medium border transition-all ${
                config.encryption === opt.value
                  ? 'border-primary-500 bg-primary-50 text-primary-700'
                  : 'border-gray-200 text-gray-600 hover:border-gray-300'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      {/* Authentication */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Username / Email</label>
          <input
            type="text"
            value={config.username}
            onChange={(e) => updateField('username', e.target.value)}
            placeholder="you@yourcompany.com"
            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Password
            {passwordSet && !config.password && (
              <span className="ml-2 inline-flex items-center gap-1 text-xs text-green-600 font-normal">
                <Lock className="h-3 w-3" /> Saved
              </span>
            )}
          </label>
          <div className="relative">
            <input
              type={showPassword ? 'text' : 'password'}
              value={config.password}
              onChange={(e) => updateField('password', e.target.value)}
              placeholder={passwordSet ? 'Leave blank to keep current' : 'Enter password'}
              className="w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
            >
              {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
            </button>
          </div>
        </div>
      </div>

      {/* Sender Identity */}
      <div className="pt-4 border-t border-gray-200">
        <h4 className="text-sm font-medium text-gray-900 mb-3">Sender Identity</h4>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">From Name</label>
            <input
              type="text"
              value={config.from_name}
              onChange={(e) => updateField('from_name', e.target.value)}
              placeholder="Your Company Name"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">From Email</label>
            <input
              type="email"
              value={config.from_email}
              onChange={(e) => updateField('from_email', e.target.value)}
              placeholder="noreply@yourcompany.com"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
            />
          </div>
        </div>
      </div>

      {/* Save Button */}
      <div className="flex items-center gap-3">
        <button
          onClick={handleSave}
          disabled={saving}
          className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-50"
        >
          {saving ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : saved ? (
            <Check className="h-4 w-4" />
          ) : (
            <Save className="h-4 w-4" />
          )}
          {saved ? 'Saved' : 'Save Settings'}
        </button>
      </div>

      {/* Test Email */}
      {isConfigured && (
        <div className="pt-4 border-t border-gray-200">
          <h4 className="text-sm font-medium text-gray-900 mb-3">Send Test Email</h4>
          <div className="flex gap-2">
            <input
              type="email"
              value={testEmail}
              onChange={(e) => setTestEmail(e.target.value)}
              placeholder="test@example.com"
              className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none text-sm"
            />
            <button
              onClick={handleTest}
              disabled={testing || !testEmail}
              className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-50"
            >
              {testing ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
              Send Test
            </button>
          </div>
          {testResult && (
            <p className={`mt-2 text-sm ${testResult.success ? 'text-green-600' : 'text-red-600'}`}>
              {testResult.message}
            </p>
          )}
        </div>
      )}
    </div>
  );
}
