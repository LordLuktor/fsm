import { useState } from 'react';
import { Link, useNavigate, Navigate } from 'react-router-dom';
import { useBranding } from '../../context/BrandingContext';
import { useCustomerAuth } from '../../context/CustomerAuthContext';
import { Wrench, Loader2, Eye, EyeOff } from 'lucide-react';

export default function PortalLoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const { branding, loading: brandingLoading } = useBranding();
  const { login, isAuthenticated, loading: authLoading } = useCustomerAuth();
  const navigate = useNavigate();

  if (authLoading || brandingLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  if (isAuthenticated) {
    return <Navigate to="../dashboard" relative="path" replace />;
  }

  const primaryColor = branding?.primary_color || '#2563eb';

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await login(email, password);
      navigate('../dashboard', { relative: 'path' });
    } catch (err) {
      const message =
        err.response?.data?.message ||
        err.message ||
        'Login failed. Please check your credentials.';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

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
          <h1 className="text-2xl font-bold text-gray-900">
            {branding?.company_name || 'Customer Portal'}
          </h1>
          {branding?.welcome_message ? (
            <p className="mt-2 text-sm text-gray-500">{branding.welcome_message}</p>
          ) : (
            <p className="mt-2 text-sm text-gray-500">Sign in to your account</p>
          )}
        </div>

        {/* Login Card */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
          {error && (
            <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
              {error}
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
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
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:border-transparent text-sm"
                style={{
                  '--tw-ring-color': primaryColor,
                  focusRingColor: primaryColor,
                }}
                onFocus={(e) => {
                  e.target.style.boxShadow = `0 0 0 2px ${primaryColor}33`;
                  e.target.style.borderColor = primaryColor;
                }}
                onBlur={(e) => {
                  e.target.style.boxShadow = '';
                  e.target.style.borderColor = '';
                }}
                placeholder="you@example.com"
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
                  autoComplete="current-password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="block w-full px-3 py-2.5 pr-10 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:border-transparent text-sm"
                  onFocus={(e) => {
                    e.target.style.boxShadow = `0 0 0 2px ${primaryColor}33`;
                    e.target.style.borderColor = primaryColor;
                  }}
                  onBlur={(e) => {
                    e.target.style.boxShadow = '';
                    e.target.style.borderColor = '';
                  }}
                  placeholder="Enter your password"
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

            <div className="flex justify-end">
              <Link
                to="../forgot-password"
                relative="path"
                className="text-sm font-medium hover:underline"
                style={{ color: primaryColor }}
              >
                Forgot password?
              </Link>
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
                'Sign in'
              )}
            </button>
          </form>
        </div>

        {/* Register link */}
        <p className="mt-6 text-center text-sm text-gray-500">
          Don&apos;t have an account?{' '}
          <Link
            to="../register"
            relative="path"
            className="font-medium hover:underline"
            style={{ color: primaryColor }}
          >
            Create one
          </Link>
        </p>
      </div>
    </div>
  );
}
