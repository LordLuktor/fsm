import { useState, useEffect } from 'react';
import { settings as settingsApi } from '../../api/client';
import {
  Globe, Loader2, Check, AlertCircle, Copy, ExternalLink,
  Palette, Image as ImageIcon, MessageSquare, Key,
} from 'lucide-react';

export default function PortalConfigSection() {
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
      const { data } = await settingsApi.getCompanySettings();
      setConfig(data);
    } catch (err) {
      setError('Failed to load portal settings');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    setSaved(false);

    try {
      await settingsApi.updateCompanySettings(config);
      setSaved(true);
      setTimeout(() => setSaved(false), 3000);
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to save');
    } finally {
      setSaving(false);
    }
  };

  const update = (key, value) => {
    setConfig((prev) => ({ ...prev, [key]: value }));
    setSaved(false);
  };

  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="h-5 w-5 animate-spin text-gray-400" />
      </div>
    );
  }

  if (!config) return <p className="text-red-500 text-sm">Failed to load settings.</p>;

  const portalUrl = config.portal_domain
    ? `https://${config.portal_domain}`
    : `https://fsm.steinmetz.ltd/portal/${config.portal_org_code || 'your-company'}`;

  return (
    <div className="space-y-6">
      {error && (
        <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          <AlertCircle className="h-4 w-4 shrink-0" /> {error}
        </div>
      )}

      {/* Enable toggle */}
      <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
        <div>
          <p className="font-medium text-gray-900">Enable Customer Portal</p>
          <p className="text-sm text-gray-500">Allow your customers to log in, view work orders, and request service</p>
        </div>
        <button
          type="button"
          onClick={() => update('portal_enabled', !config.portal_enabled)}
          className={`relative w-12 h-6 rounded-full transition-colors ${
            config.portal_enabled ? 'bg-primary-600' : 'bg-gray-200'
          }`}
        >
          <span className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${
            config.portal_enabled ? 'left-7' : 'left-1'
          }`} />
        </button>
      </div>

      {config.portal_enabled && (
        <>
          {/* Organization Code */}
          {config.portal_org_code && (
            <div>
              <label className="block text-xs font-semibold text-gray-600 mb-1 flex items-center gap-1">
                <Key className="h-3 w-3" /> Organization Code
              </label>
              <div className="flex items-center gap-2">
                <code className="px-3 py-2 bg-gray-100 rounded-lg text-sm font-mono flex-1">
                  {config.portal_org_code}
                </code>
                <button
                  onClick={() => copyToClipboard(config.portal_org_code)}
                  className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                  title="Copy code"
                >
                  <Copy className="h-4 w-4" />
                </button>
              </div>
              <p className="text-xs text-gray-400 mt-1">Customers use this code during registration to link to your company.</p>
            </div>
          )}

          {/* Portal URL */}
          <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <label className="block text-xs font-semibold text-blue-700 mb-1">Your Portal URL</label>
            <div className="flex items-center gap-2">
              <code className="text-sm font-mono text-blue-800 flex-1 truncate">{portalUrl}</code>
              <a href={portalUrl} target="_blank" rel="noopener noreferrer" className="text-blue-500 hover:text-blue-700">
                <ExternalLink className="h-4 w-4" />
              </a>
            </div>
          </div>

          {/* Custom Domain */}
          <div>
            <label className="block text-xs font-semibold text-gray-600 mb-1 flex items-center gap-1">
              <Globe className="h-3 w-3" /> Custom Domain (optional)
            </label>
            <input
              type="text"
              value={config.portal_domain || ''}
              onChange={(e) => update('portal_domain', e.target.value)}
              placeholder="portal.yourcompany.com"
              className="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
            />
            <div className="mt-2 p-3 bg-gray-50 rounded-lg text-xs text-gray-500 space-y-1">
              <p className="font-medium text-gray-700">DNS Setup Instructions:</p>
              <p>1. Go to your domain's DNS settings</p>
              <p>2. Create a <strong>CNAME</strong> record:</p>
              <p className="font-mono bg-white px-2 py-1 rounded border border-gray-200">
                {config.portal_domain || 'portal.yourcompany.com'} → fsm.steinmetz.ltd
              </p>
              <p>3. Wait for DNS propagation (up to 24 hours)</p>
              <p>4. SSL certificate will be auto-provisioned on first visit</p>
            </div>
          </div>

          {/* Logo */}
          <div>
            <label className="block text-xs font-semibold text-gray-600 mb-1 flex items-center gap-1">
              <ImageIcon className="h-3 w-3" /> Logo URL
            </label>
            <input
              type="url"
              value={config.portal_logo_url || ''}
              onChange={(e) => update('portal_logo_url', e.target.value)}
              placeholder="https://yourcompany.com/logo.png"
              className="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
            />
            {config.portal_logo_url && (
              <div className="mt-2 p-3 bg-gray-50 rounded-lg flex items-center gap-3">
                <img src={config.portal_logo_url} alt="Preview" className="h-10 w-10 rounded-lg object-cover border border-gray-200" />
                <span className="text-xs text-gray-500">Logo preview</span>
              </div>
            )}
          </div>

          {/* Primary Color */}
          <div>
            <label className="block text-xs font-semibold text-gray-600 mb-1 flex items-center gap-1">
              <Palette className="h-3 w-3" /> Brand Color
            </label>
            <div className="flex items-center gap-3">
              <input
                type="color"
                value={config.portal_primary_color || '#2563eb'}
                onChange={(e) => update('portal_primary_color', e.target.value)}
                className="w-12 h-10 rounded-lg cursor-pointer border-2 border-gray-200"
              />
              <input
                type="text"
                value={config.portal_primary_color || '#2563eb'}
                onChange={(e) => update('portal_primary_color', e.target.value)}
                className="w-28 px-3 py-2 border-2 border-gray-200 rounded-lg text-sm font-mono focus:border-primary-500 outline-none"
              />
              <div
                className="h-10 flex-1 rounded-lg"
                style={{ backgroundColor: config.portal_primary_color || '#2563eb' }}
              />
            </div>
          </div>

          {/* Welcome Message */}
          <div>
            <label className="block text-xs font-semibold text-gray-600 mb-1 flex items-center gap-1">
              <MessageSquare className="h-3 w-3" /> Welcome Message
            </label>
            <textarea
              value={config.portal_welcome_message || ''}
              onChange={(e) => update('portal_welcome_message', e.target.value)}
              placeholder="Welcome to our customer portal. Log in to view your service history and request new appointments."
              rows={3}
              className="w-full px-3 py-2 border-2 border-gray-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none resize-none"
            />
          </div>
        </>
      )}

      {/* Save */}
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
          {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Globe className="h-4 w-4" />}
          Save Portal Settings
        </button>
      </div>
    </div>
  );
}
