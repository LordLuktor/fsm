import { useState } from 'react';
import { useCustomerAuth } from '../../context/CustomerAuthContext';
import { useBranding } from '../../context/BrandingContext';
import { portalProfile } from '../../api/portalClient';
import { account } from '../../api/client';
import {
  User,
  Lock,
  LogOut,
  Check,
  Loader2,
  AlertCircle,
  Eye,
  EyeOff,
  Building2,
  Mail,
  Phone,
  Shield,
} from 'lucide-react';

export default function PortalAccountPage() {
  const { user, logout, loadUser } = useCustomerAuth();
  const { branding } = useBranding();

  const primaryColor = branding?.primary_color || '#2563eb';

  // Profile state
  const [profile, setProfile] = useState({
    first_name: user?.first_name || '',
    last_name: user?.last_name || '',
    phone: user?.phone || '',
  });
  const [profileSaving, setProfileSaving] = useState(false);
  const [profileSaved, setProfileSaved] = useState(false);
  const [profileError, setProfileError] = useState(null);

  // Password state
  const [passwords, setPasswords] = useState({
    current_password: '',
    new_password: '',
    confirm: '',
  });
  const [showCurrentPw, setShowCurrentPw] = useState(false);
  const [showNewPw, setShowNewPw] = useState(false);
  const [passwordSaving, setPasswordSaving] = useState(false);
  const [passwordSuccess, setPasswordSuccess] = useState(false);
  const [passwordError, setPasswordError] = useState(null);

  const handleSaveProfile = async (e) => {
    e.preventDefault();
    setProfileError(null);
    setProfileSaving(true);

    try {
      await portalProfile.update({
        first_name: profile.first_name,
        last_name: profile.last_name,
        phone: profile.phone,
      });
      setProfileSaved(true);
      setTimeout(() => setProfileSaved(false), 2500);
      // Refresh user data in auth context
      if (loadUser) await loadUser();
    } catch (err) {
      setProfileError(err.response?.data?.message || 'Failed to save profile.');
    } finally {
      setProfileSaving(false);
    }
  };

  const handleChangePassword = async (e) => {
    e.preventDefault();
    setPasswordError(null);

    if (passwords.new_password.length < 8) {
      setPasswordError('New password must be at least 8 characters.');
      return;
    }

    if (passwords.new_password !== passwords.confirm) {
      setPasswordError('New passwords do not match.');
      return;
    }

    if (passwords.new_password === passwords.current_password) {
      setPasswordError('New password must be different from current password.');
      return;
    }

    setPasswordSaving(true);
    try {
      await account.changePassword(passwords.current_password, passwords.new_password);
      setPasswordSuccess(true);
      setPasswords({ current_password: '', new_password: '', confirm: '' });
      // Password change invalidates token - redirect to login after brief delay
      setTimeout(() => {
        logout();
      }, 2000);
    } catch (err) {
      setPasswordError(err.response?.data?.message || 'Failed to change password.');
    } finally {
      setPasswordSaving(false);
    }
  };

  const handleLogout = () => {
    if (window.confirm('Are you sure you want to log out?')) {
      logout();
    }
  };

  const getPasswordStrength = () => {
    const pw = passwords.new_password;
    if (pw.length === 0) return { label: '', color: '' };
    if (pw.length < 8) return { label: 'Too short', color: 'text-red-500' };
    let score = 0;
    if (/[a-z]/.test(pw)) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^a-zA-Z0-9]/.test(pw)) score++;
    if (pw.length >= 12) score++;

    if (score <= 2) return { label: 'Weak', color: 'text-red-500' };
    if (score <= 3) return { label: 'Fair', color: 'text-yellow-500' };
    return { label: 'Strong', color: 'text-green-500' };
  };

  const strength = getPasswordStrength();

  const inputFocusStyle = (e) => {
    e.target.style.boxShadow = `0 0 0 2px ${primaryColor}33`;
    e.target.style.borderColor = primaryColor;
  };

  const inputBlurStyle = (e) => {
    e.target.style.boxShadow = '';
    e.target.style.borderColor = '';
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <User className="h-6 w-6" />
          My Account
        </h1>
        <p className="text-gray-500 mt-1">
          Manage your profile and account settings
        </p>
      </div>

      {/* Profile Section */}
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100 bg-gray-50">
          <div className="flex items-center gap-3">
            <div
              className="w-10 h-10 rounded-lg flex items-center justify-center"
              style={{ backgroundColor: `${primaryColor}15` }}
            >
              <User className="h-5 w-5" style={{ color: primaryColor }} />
            </div>
            <div>
              <h3 className="font-semibold text-gray-900">Profile</h3>
              <p className="text-sm text-gray-500">Your personal information</p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSaveProfile} className="p-6 space-y-4">
          {profileError && (
            <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
              <AlertCircle className="h-4 w-4 shrink-0" />
              {profileError}
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                First Name
              </label>
              <input
                type="text"
                value={profile.first_name}
                onChange={(e) => setProfile({ ...profile, first_name: e.target.value })}
                className="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:border-transparent"
                onFocus={inputFocusStyle}
                onBlur={inputBlurStyle}
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Last Name
              </label>
              <input
                type="text"
                value={profile.last_name}
                onChange={(e) => setProfile({ ...profile, last_name: e.target.value })}
                className="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:border-transparent"
                onFocus={inputFocusStyle}
                onBlur={inputBlurStyle}
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              <span className="flex items-center gap-1">
                <Mail className="h-3.5 w-3.5" />
                Email
              </span>
            </label>
            <input
              type="email"
              value={user?.email || ''}
              readOnly
              className="w-full px-4 py-2.5 border border-gray-200 rounded-lg bg-gray-50 text-sm text-gray-500 cursor-not-allowed"
            />
            <p className="text-xs text-gray-400 mt-1">
              Email cannot be changed. Contact support if you need to update it.
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              <span className="flex items-center gap-1">
                <Phone className="h-3.5 w-3.5" />
                Phone
              </span>
            </label>
            <input
              type="tel"
              value={profile.phone}
              onChange={(e) => setProfile({ ...profile, phone: e.target.value })}
              placeholder="(555) 123-4567"
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:border-transparent"
              onFocus={inputFocusStyle}
              onBlur={inputBlurStyle}
            />
          </div>

          <div className="pt-2">
            <button
              type="submit"
              disabled={profileSaving}
              className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors disabled:opacity-50"
              style={{ backgroundColor: primaryColor }}
              onMouseEnter={(e) => { if (!profileSaving) e.target.style.filter = 'brightness(0.9)'; }}
              onMouseLeave={(e) => { e.target.style.filter = ''; }}
            >
              {profileSaving ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : profileSaved ? (
                <Check className="h-4 w-4" />
              ) : (
                <Check className="h-4 w-4" />
              )}
              {profileSaved ? 'Saved!' : 'Save Changes'}
            </button>
          </div>
        </form>
      </div>

      {/* Change Password Section */}
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100 bg-gray-50">
          <div className="flex items-center gap-3">
            <div
              className="w-10 h-10 rounded-lg flex items-center justify-center"
              style={{ backgroundColor: `${primaryColor}15` }}
            >
              <Shield className="h-5 w-5" style={{ color: primaryColor }} />
            </div>
            <div>
              <h3 className="font-semibold text-gray-900">Change Password</h3>
              <p className="text-sm text-gray-500">Update your account password</p>
            </div>
          </div>
        </div>

        <form onSubmit={handleChangePassword} className="p-6 space-y-4">
          {passwordError && (
            <div className="flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
              <AlertCircle className="h-4 w-4 shrink-0" />
              {passwordError}
            </div>
          )}

          {passwordSuccess && (
            <div className="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
              <Check className="h-4 w-4 shrink-0" />
              Password changed successfully! Redirecting to login...
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Current Password
            </label>
            <div className="relative">
              <input
                type={showCurrentPw ? 'text' : 'password'}
                value={passwords.current_password}
                onChange={(e) => setPasswords({ ...passwords, current_password: e.target.value })}
                required
                className="w-full px-4 py-2.5 pr-12 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:border-transparent"
                onFocus={inputFocusStyle}
                onBlur={inputBlurStyle}
              />
              <button
                type="button"
                onClick={() => setShowCurrentPw(!showCurrentPw)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                {showCurrentPw ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              New Password
            </label>
            <div className="relative">
              <input
                type={showNewPw ? 'text' : 'password'}
                value={passwords.new_password}
                onChange={(e) => setPasswords({ ...passwords, new_password: e.target.value })}
                required
                minLength={8}
                className="w-full px-4 py-2.5 pr-12 border border-gray-300 rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:border-transparent"
                onFocus={inputFocusStyle}
                onBlur={inputBlurStyle}
              />
              <button
                type="button"
                onClick={() => setShowNewPw(!showNewPw)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                {showNewPw ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
              </button>
            </div>
            {passwords.new_password && (
              <p className={`text-xs mt-1 ${strength.color}`}>
                Strength: {strength.label}
              </p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Confirm New Password
            </label>
            <input
              type="password"
              value={passwords.confirm}
              onChange={(e) => setPasswords({ ...passwords, confirm: e.target.value })}
              required
              className={`w-full px-4 py-2.5 border rounded-lg shadow-sm text-sm focus:outline-none focus:ring-2 focus:border-transparent ${
                passwords.confirm && passwords.confirm !== passwords.new_password
                  ? 'border-red-300'
                  : 'border-gray-300'
              }`}
              onFocus={inputFocusStyle}
              onBlur={inputBlurStyle}
            />
            {passwords.confirm && passwords.confirm !== passwords.new_password && (
              <p className="text-xs text-red-500 mt-1">Passwords do not match</p>
            )}
          </div>

          <div className="pt-2">
            <button
              type="submit"
              disabled={
                passwordSaving ||
                passwordSuccess ||
                !passwords.current_password ||
                !passwords.new_password ||
                passwords.new_password !== passwords.confirm
              }
              className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              style={{ backgroundColor: primaryColor }}
              onMouseEnter={(e) => { if (!passwordSaving) e.target.style.filter = 'brightness(0.9)'; }}
              onMouseLeave={(e) => { e.target.style.filter = ''; }}
            >
              {passwordSaving ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Lock className="h-4 w-4" />
              )}
              Change Password
            </button>
          </div>
        </form>
      </div>

      {/* Linked Organizations */}
      {user?.organizations && user.organizations.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div className="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <div className="flex items-center gap-3">
              <div
                className="w-10 h-10 rounded-lg flex items-center justify-center"
                style={{ backgroundColor: `${primaryColor}15` }}
              >
                <Building2 className="h-5 w-5" style={{ color: primaryColor }} />
              </div>
              <div>
                <h3 className="font-semibold text-gray-900">Linked Organizations</h3>
                <p className="text-sm text-gray-500">Organizations associated with your account</p>
              </div>
            </div>
          </div>

          <div className="divide-y divide-gray-100">
            {user.organizations.map((org) => (
              <div key={org.id || org.name} className="px-6 py-4 flex items-center gap-4">
                <div className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-sm font-bold text-gray-500">
                  {(org.name || 'O').charAt(0).toUpperCase()}
                </div>
                <div className="min-w-0">
                  <p className="text-sm font-medium text-gray-900 truncate">
                    {org.name}
                  </p>
                  {org.role && (
                    <p className="text-xs text-gray-500">{org.role}</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Logout Button */}
      <button
        onClick={handleLogout}
        className="w-full flex items-center justify-center gap-2 p-4 bg-red-50 text-red-700 font-medium rounded-xl hover:bg-red-100 transition-colors"
      >
        <LogOut className="h-5 w-5" />
        Log Out
      </button>
    </div>
  );
}
