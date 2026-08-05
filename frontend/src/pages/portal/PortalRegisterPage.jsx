import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useBranding } from '../../context/BrandingContext';
import { portalAuth } from '../../api/portalClient';
import { Wrench, Loader2, CheckCircle, Eye, EyeOff } from 'lucide-react';

export default function PortalRegisterPage() {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    confirm_password: '',
    organization_code: '',
    website: '', // honeypot
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const { branding, loading: brandingLoading } = useBranding();

  if (brandingLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  const primaryColor = branding?.primary_color || '#2563eb';

  const handleChange = (e) => {
    setFormData((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const focusStyle = (e) => {
    e.target.style.boxShadow = `0 0 0 2px ${primaryColor}33`;
    e.target.style.borderColor = primaryColor;
  };

  const blurStyle = (e) => {
    e.target.style.boxShadow = '';
    e.target.style.borderColor = '';
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    // honeypot check — bots fill hidden fields
    if (formData.website) {
      // Silently reject
      setSuccess(true);
      return;
    }

    if (formData.password !== formData.confirm_password) {
      setError('Passwords do not match.');
      return;
    }

    if (formData.password.length < 8) {
      setError('Password must be at least 8 characters.');
      return;
    }

    setLoading(true);

    try {
      const payload = {
        first_name: formData.first_name.trim(),
        last_name: formData.last_name.trim(),
        email: formData.email.trim(),
        phone: formData.phone.trim(),
        password: formData.password,
      };

      if (formData.organization_code.trim()) {
        payload.organization_code = formData.organization_code.trim();
      }

      await portalAuth.register(payload);
      setSuccess(true);
    } catch (err) {
      if (err.response?.status === 429) {
        setError('Too many registration attempts. Please try again later.');
      } else {
        const message =
          err.response?.data?.message ||
          err.message ||
          'Registration failed. Please try again.';
        setError(message);
      }
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-md w-full text-center">
          <div
            className="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4"
            style={{ backgroundColor: `${primaryColor}15` }}
          >
            <CheckCircle className="w-7 h-7" style={{ color: primaryColor }} />
          </div>
          <h1 className="text-2xl font-bold text-gray-900 mb-2">Account Created</h1>
          <p className="text-gray-500 mb-6">
            Your account has been created successfully. Please check your email to verify your
            address, then sign in.
          </p>
          <Link
            to="../login"
            relative="path"
            className="inline-flex items-center justify-center px-6 py-2.5 rounded-lg text-sm font-medium text-white transition-colors"
            style={{ backgroundColor: primaryColor }}
          >
            Go to Sign In
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full">
        {/* Logo + Company Name */}
        <div className="text-center mb-8">
          {branding?.logo_url ? (
            <img
              src={branding.logo_url}
              alt={branding.company_name || 'Company logo'}
              className="h-14 w-14 rounded-xl object-cover mx-auto mb-4"
            />
          ) : (
            <div
              className="w-14 h-14 rounded-xl flex items-center justify-center mx-auto mb-4"
              style={{ backgroundColor: primaryColor }}
            >
              <Wrench className="w-7 h-7 text-white" />
            </div>
          )}
          <h1 className="text-2xl font-bold text-gray-900">Create an Account</h1>
          <p className="mt-2 text-sm text-gray-500">
            {branding?.company_name
              ? `Sign up for ${branding.company_name}'s customer portal`
              : 'Sign up for the customer portal'}
          </p>
        </div>

        {/* Register Card */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
          {error && (
            <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            {/* Honeypot — hidden from real users */}
            <div className="sr-only" aria-hidden="true">
              <label htmlFor="website">Website</label>
              <input
                type="text"
                id="website"
                name="website"
                value={formData.website}
                onChange={handleChange}
                tabIndex={-1}
                autoComplete="off"
              />
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label htmlFor="first_name" className="block text-sm font-medium text-gray-700 mb-1">
                  First name
                </label>
                <input
                  id="first_name"
                  name="first_name"
                  type="text"
                  autoComplete="given-name"
                  required
                  value={formData.first_name}
                  onChange={handleChange}
                  onFocus={focusStyle}
                  onBlur={blurStyle}
                  className="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none text-sm"
                  placeholder="Jane"
                />
              </div>
              <div>
                <label htmlFor="last_name" className="block text-sm font-medium text-gray-700 mb-1">
                  Last name
                </label>
                <input
                  id="last_name"
                  name="last_name"
                  type="text"
                  autoComplete="family-name"
                  required
                  value={formData.last_name}
                  onChange={handleChange}
                  onFocus={focusStyle}
                  onBlur={blurStyle}
                  className="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none text-sm"
                  placeholder="Smith"
                />
              </div>
            </div>

            <div>
              <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                Email address
              </label>
              <input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                required
                value={formData.email}
                onChange={handleChange}
                onFocus={focusStyle}
                onBlur={blurStyle}
                className="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none text-sm"
                placeholder="you@example.com"
              />
            </div>

            <div>
              <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
                Phone number
              </label>
              <input
                id="phone"
                name="phone"
                type="tel"
                autoComplete="tel"
                value={formData.phone}
                onChange={handleChange}
                onFocus={focusStyle}
                onBlur={blurStyle}
                className="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none text-sm"
                placeholder="(555) 123-4567"
              />
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                Password
              </label>
              <div className="relative">
                <input
                  id="password"
                  name="password"
                  type={showPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  required
                  minLength={8}
                  value={formData.password}
                  onChange={handleChange}
                  onFocus={focusStyle}
                  onBlur={blurStyle}
                  className="block w-full px-3 py-2.5 pr-10 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none text-sm"
                  placeholder="Minimum 8 characters"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                >
                  {showPassword ? (
                    <EyeOff className="h-4 w-4" />
                  ) : (
                    <Eye className="h-4 w-4" />
                  )}
                </button>
              </div>
            </div>

            <div>
              <label htmlFor="confirm_password" className="block text-sm font-medium text-gray-700 mb-1">
                Confirm password
              </label>
              <input
                id="confirm_password"
                name="confirm_password"
                type={showPassword ? 'text' : 'password'}
                autoComplete="new-password"
                required
                minLength={8}
                value={formData.confirm_password}
                onChange={handleChange}
                onFocus={focusStyle}
                onBlur={blurStyle}
                className="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none text-sm"
                placeholder="Re-enter your password"
              />
            </div>

            <div>
              <label htmlFor="organization_code" className="block text-sm font-medium text-gray-700 mb-1">
                Organization code{' '}
                <span className="text-gray-400 font-normal">(optional)</span>
              </label>
              <input
                id="organization_code"
                name="organization_code"
                type="text"
                value={formData.organization_code}
                onChange={handleChange}
                onFocus={focusStyle}
                onBlur={blurStyle}
                className="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none text-sm"
                placeholder="If provided by your service company"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              style={{
                backgroundColor: primaryColor,
                '--tw-ring-color': primaryColor,
              }}
              onMouseEnter={(e) => {
                if (!loading) e.target.style.filter = 'brightness(0.9)';
              }}
              onMouseLeave={(e) => {
                e.target.style.filter = '';
              }}
            >
              {loading ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                'Create Account'
              )}
            </button>
          </form>
        </div>

        {/* Login link */}
        <p className="mt-6 text-center text-sm text-gray-500">
          Already have an account?{' '}
          <Link
            to="../login"
            relative="path"
            className="font-medium hover:underline"
            style={{ color: primaryColor }}
          >
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
