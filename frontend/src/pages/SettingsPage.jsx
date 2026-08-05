import { useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { useSettings } from '../hooks/useSettings';
import { useNotifications } from '../hooks/useNotifications';
import { useGPSTracking } from '../hooks/useGPSTracking';
import { account } from '../api/client';
import {
  Settings,
  User,
  Bell,
  MapPin,
  Shield,
  LogOut,
  Check,
  ChevronRight,
  Smartphone,
  AlertTriangle,
  BellOff,
  LocateOff,
  Mail,
  ClipboardList,
  Server,
} from 'lucide-react';
import ChangePasswordModal from '../components/settings/ChangePasswordModal';
import ActiveSessionsModal from '../components/settings/ActiveSessionsModal';
import NotificationConfigSection from '../components/settings/NotificationConfigSection';
import PortalConfigSection from '../components/settings/PortalConfigSection';
import WorkOrderTypesSection from '../components/settings/WorkOrderTypesSection';
import SmtpConfigSection from '../components/settings/SmtpConfigSection';

function SettingSection({ icon: Icon, title, description, children }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div className="px-6 py-4 border-b border-gray-100 bg-gray-50">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
            <Icon className="h-5 w-5 text-primary-600" />
          </div>
          <div>
            <h3 className="font-semibold text-gray-900">{title}</h3>
            {description && (
              <p className="text-sm text-gray-500">{description}</p>
            )}
          </div>
        </div>
      </div>
      <div className="p-6">{children}</div>
    </div>
  );
}

function ToggleSetting({ label, description, checked, onChange, warning }) {
  return (
    <label className="flex items-center justify-between py-3 cursor-pointer">
      <div>
        <p className="font-medium text-gray-900">{label}</p>
        {description && (
          <p className="text-sm text-gray-500">{description}</p>
        )}
        {warning && (
          <p className="text-xs text-yellow-600 mt-1 flex items-center gap-1">
            <AlertTriangle className="h-3 w-3" />
            {warning}
          </p>
        )}
      </div>
      <button
        type="button"
        onClick={() => onChange(!checked)}
        className={`relative w-12 h-6 rounded-full transition-colors ${
          checked ? 'bg-primary-600' : 'bg-gray-200'
        }`}
      >
        <span
          className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${
            checked ? 'left-7' : 'left-1'
          }`}
        />
      </button>
    </label>
  );
}

export default function SettingsPage() {
  const { user, logout, isTechnician, isDispatcher, isAdmin } = useAuth();
  const { notifications: notifSettings, location: locSettings, updateNotifications, updateLocation } = useSettings();
  const notifHook = useNotifications({ enabled: false });
  const gpsHook = useGPSTracking({ enabled: false });

  const [saved, setSaved] = useState(false);
  const [showPasswordModal, setShowPasswordModal] = useState(false);
  const [showSessionsModal, setShowSessionsModal] = useState(false);
  const [profile, setProfile] = useState({
    firstName: user?.first_name || '',
    lastName: user?.last_name || '',
    phone: user?.phone || '',
  });

  const handleLogout = () => {
    if (confirm('Are you sure you want to log out?')) {
      logout();
    }
  };

  const handleSaveProfile = async () => {
    try {
      await account.updateProfile({
        first_name: profile.firstName,
        last_name: profile.lastName,
        phone: profile.phone,
      });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    } catch (err) {
      console.error('Failed to save profile:', err);
      alert(err.response?.data?.message || 'Failed to save profile');
    }
  };

  const handlePushToggle = async (val) => {
    if (val && notifHook.permission !== 'granted') {
      const granted = await notifHook.requestPermission();
      if (!granted) return;
    }
    updateNotifications({ push: val });
  };

  const handleAutoLocationToggle = async (val) => {
    if (val) {
      const granted = await gpsHook.requestPermission();
      if (!granted) return;
    }
    updateLocation({ autoUpdate: val });
  };

  const getRoleBadge = () => {
    if (isAdmin) return { label: 'Administrator', color: 'bg-purple-100 text-purple-700' };
    if (isDispatcher) return { label: 'Dispatcher', color: 'bg-blue-100 text-blue-700' };
    if (isTechnician) return { label: 'Technician', color: 'bg-green-100 text-green-700' };
    return { label: 'User', color: 'bg-gray-100 text-gray-700' };
  };

  const role = getRoleBadge();

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
          <Settings className="h-6 w-6 mr-2" />
          Settings
        </h1>
        <p className="text-gray-500 mt-1">
          Manage your account and preferences
        </p>
      </div>

      {/* Profile */}
      <SettingSection
        icon={User}
        title="Profile"
        description="Your account information"
      >
        <div className="flex items-center gap-6 mb-6">
          <div className="w-20 h-20 rounded-full bg-primary-100 flex items-center justify-center text-2xl font-bold text-primary-600">
            {(user?.display_name || 'U').charAt(0).toUpperCase()}
          </div>
          <div>
            <h4 className="text-xl font-semibold text-gray-900">
              {user?.display_name}
            </h4>
            <p className="text-gray-500">{user?.email}</p>
            <span className={`inline-block mt-2 px-3 py-1 rounded-full text-sm font-medium ${role.color}`}>
              {role.label}
            </span>
          </div>
        </div>

        <div className="space-y-4 pt-4 border-t border-gray-100">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                First Name
              </label>
              <input
                type="text"
                value={profile.firstName}
                onChange={(e) => setProfile({ ...profile, firstName: e.target.value })}
                className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Last Name
              </label>
              <input
                type="text"
                value={profile.lastName}
                onChange={(e) => setProfile({ ...profile, lastName: e.target.value })}
                className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Phone Number
            </label>
            <input
              type="tel"
              value={profile.phone}
              onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
              placeholder="(555) 123-4567"
              className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
            />
          </div>

          <button
            onClick={handleSaveProfile}
            className="btn btn-primary inline-flex items-center"
          >
            <Check className="h-4 w-4 mr-2" />
            {saved ? 'Saved!' : 'Save Changes'}
          </button>
        </div>
      </SettingSection>

      {/* Notifications */}
      <SettingSection
        icon={Bell}
        title="Notifications"
        description="Choose how you want to be notified"
      >
        {notifHook.permission === 'denied' && (
          <div className="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center gap-2 text-sm text-yellow-700">
            <BellOff className="h-4 w-4 shrink-0" />
            Push notifications are blocked. Enable them in your browser settings.
          </div>
        )}

        <div className="divide-y divide-gray-100">
          <ToggleSetting
            label="Push Notifications"
            description="Receive notifications on your device"
            checked={notifSettings.push}
            onChange={handlePushToggle}
            warning={notifHook.permission === 'denied' ? 'Blocked by browser' : undefined}
          />
          <ToggleSetting
            label="Email Notifications"
            description="Receive important updates via email"
            checked={notifSettings.email}
            onChange={(v) => updateNotifications({ email: v })}
          />
          <ToggleSetting
            label="New Work Orders"
            description="Get notified when you're assigned a new job"
            checked={notifSettings.newWorkOrders}
            onChange={(v) => updateNotifications({ newWorkOrders: v })}
          />
          <ToggleSetting
            label="Status Changes"
            description="Updates when work order status changes"
            checked={notifSettings.statusChanges}
            onChange={(v) => updateNotifications({ statusChanges: v })}
          />
          <ToggleSetting
            label="Reminders"
            description="Upcoming job reminders"
            checked={notifSettings.reminders}
            onChange={(v) => updateNotifications({ reminders: v })}
          />
        </div>
      </SettingSection>

      {/* Location (for technicians) */}
      {isTechnician && (
        <SettingSection
          icon={MapPin}
          title="Location Settings"
          description="Control how your location is shared"
        >
          {gpsHook.status === 'denied' && (
            <div className="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg flex items-center gap-2 text-sm text-yellow-700">
              <LocateOff className="h-4 w-4 shrink-0" />
              Location access is blocked. Enable it in your browser settings.
            </div>
          )}

          <div className="divide-y divide-gray-100">
            <ToggleSetting
              label="Auto Update Location"
              description="Automatically share your location while working"
              checked={locSettings.autoUpdate}
              onChange={handleAutoLocationToggle}
              warning={gpsHook.status === 'denied' ? 'Blocked by browser' : undefined}
            />
            <ToggleSetting
              label="High Accuracy Mode"
              description="Use GPS for more precise location (uses more battery)"
              checked={locSettings.highAccuracy}
              onChange={(v) => updateLocation({ highAccuracy: v })}
            />
            <div className="py-3">
              <label className="block font-medium text-gray-900 mb-2">
                Update Interval
              </label>
              <select
                value={locSettings.updateInterval}
                onChange={(e) => updateLocation({ updateInterval: Number(e.target.value) })}
                className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none bg-white"
              >
                <option value={15}>Every 15 seconds</option>
                <option value={30}>Every 30 seconds</option>
                <option value={60}>Every minute</option>
                <option value={120}>Every 2 minutes</option>
                <option value={300}>Every 5 minutes</option>
              </select>
              <p className="text-xs text-gray-500 mt-1">
                Shorter intervals use more battery but provide more accurate tracking.
              </p>
            </div>
          </div>
        </SettingSection>
      )}

      {/* Security */}
      <SettingSection
        icon={Shield}
        title="Security"
        description="Manage your account security"
      >
        <div className="space-y-3">
          <button
            onClick={() => setShowPasswordModal(true)}
            className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <div className="text-left">
              <p className="font-medium text-gray-900">Change Password</p>
              <p className="text-sm text-gray-500">Update your password</p>
            </div>
            <ChevronRight className="h-5 w-5 text-gray-400" />
          </button>

          <button
            onClick={() => setShowSessionsModal(true)}
            className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <div className="text-left">
              <p className="font-medium text-gray-900">Active Sessions</p>
              <p className="text-sm text-gray-500">Manage your logged-in devices</p>
            </div>
            <ChevronRight className="h-5 w-5 text-gray-400" />
          </button>
        </div>
      </SettingSection>

      {/* App Info */}
      <SettingSection
        icon={Smartphone}
        title="About"
        description="App information"
      >
        <div className="space-y-3 text-sm">
          <div className="flex justify-between py-2">
            <span className="text-gray-500">Version</span>
            <span className="font-mono text-gray-900">1.0.0</span>
          </div>
          <div className="flex justify-between py-2">
            <span className="text-gray-500">API Endpoint</span>
            <span className="font-mono text-gray-900 text-xs">
              {import.meta.env.VITE_API_URL || 'fsm-api.steinmetz.ltd'}
            </span>
          </div>
          <div className="flex justify-between py-2">
            <span className="text-gray-500">GPS Permission</span>
            <span className={`font-mono text-xs ${
              gpsHook.status === 'denied' ? 'text-red-600' : 'text-gray-900'
            }`}>
              {typeof navigator !== 'undefined' && navigator.geolocation
                ? gpsHook.status === 'denied' ? 'Denied' : 'Available'
                : 'Not supported'}
            </span>
          </div>
          <div className="flex justify-between py-2">
            <span className="text-gray-500">Notifications</span>
            <span className={`font-mono text-xs ${
              notifHook.permission === 'denied' ? 'text-red-600' : 'text-gray-900'
            }`}>
              {notifHook.permission === 'unavailable' ? 'Not supported' : notifHook.permission}
            </span>
          </div>
        </div>
      </SettingSection>

      {/* Customer Email Notifications (admin only) */}
      {/* Work Order Types (admin only) */}
      {isAdmin && (
        <SettingSection
          icon={ClipboardList}
          title="Work Order Types"
          description="Manage the types of work orders and their default durations"
        >
          <WorkOrderTypesSection />
        </SettingSection>
      )}

      {/* SMTP / Email Server (admin only) */}
      {isAdmin && (
        <SettingSection
          icon={Server}
          title="Email Server (SMTP)"
          description="Configure your outbound email server for customer notifications"
        >
          <SmtpConfigSection />
        </SettingSection>
      )}

      {isAdmin && (
        <SettingSection
          icon={Mail}
          title="Customer Email Notifications"
          description="Configure automated emails sent to customers"
        >
          <NotificationConfigSection />
        </SettingSection>
      )}

      {/* Customer Portal (admin only) */}
      {isAdmin && (
        <SettingSection
          icon={Settings}
          title="Customer Portal"
          description="Let your customers view work orders, invoices, and request service"
        >
          <PortalConfigSection />
        </SettingSection>
      )}

      {/* Logout */}
      <button
        onClick={handleLogout}
        className="w-full flex items-center justify-center gap-2 p-4 bg-red-50 text-red-700 font-medium rounded-xl hover:bg-red-100 transition-colors"
      >
        <LogOut className="h-5 w-5" />
        Log Out
      </button>

      {/* Modals */}
      <ChangePasswordModal
        isOpen={showPasswordModal}
        onClose={() => setShowPasswordModal(false)}
      />
      <ActiveSessionsModal
        isOpen={showSessionsModal}
        onClose={() => setShowSessionsModal(false)}
      />
    </div>
  );
}
