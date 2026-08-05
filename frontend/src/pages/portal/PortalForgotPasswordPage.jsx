import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useBranding } from '../../context/BrandingContext';
import { portalAuth } from '../../api/portalClient';
import { Wrench, Loader2, Mail, ArrowLeft } from 'lucide-react';

export default function PortalForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const { branding, loading: brandingLoading } = useBranding();

  if (brandingLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
      </div>
    );
  }

  const primaryColor = branding?.primary_color || '#2563eb';

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      await portalAuth.forgotPassword(email.trim());
    } catch {
      // Intentionally swallow errors — do not reveal whether the email exists
    } finally {
      setLoading(false);
      setSubmitted(true);
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
          <h1 className="text-2xl font-bold text-gray-900">Reset Password</h1>
          <p className="mt-2 text-sm text-gray-500">
            {branding?.company_name || 'Customer Portal'}
          </p>
        </div>

        {/* Card */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
          {submitted ? (
            <div className="text-center">
              <div
                className="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4"
                style={{ backgroundColor: `${primaryColor}15` }}
              >
                <Mail className="w-6 h-6" style={{ color: primaryColor }} />
              </div>
              <h2 className="text-lg font-semibold text-gray-900 mb-2">Check your email</h2>
              <p className="text-sm text-gray-500 mb-6">
                If an account exists for <strong className="text-gray-700">{email}</strong>, we
                sent a password reset link. Please check your inbox and spam folder.
              </p>
              <Link
                to="../login"
                relative="path"
                className="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                style={{ color: primaryColor }}
              >
                <ArrowLeft className="h-4 w-4" />
                Back to sign in
              </Link>
            </div>
          ) : (
            <>
              <p className="text-sm text-gray-500 mb-6">
                Enter the email address associated with your account and we&apos;ll send you a
                link to reset your password.
              </p>

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
                    onFocus={(e) => {
                      e.target.style.boxShadow = `0 0 0 2px ${primaryColor}33`;
                      e.target.style.borderColor = primaryColor;
                    }}
                    onBlur={(e) => {
                      e.target.style.boxShadow = '';
                      e.target.style.borderColor = '';
                    }}
                    className="block w-full px-3 py-2.5 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none text-sm"
                    placeholder="you@example.com"
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
                    'Send Reset Link'
                  )}
                </button>
              </form>

              <div className="mt-6 text-center">
                <Link
                  to="../login"
                  relative="path"
                  className="inline-flex items-center gap-2 text-sm font-medium hover:underline"
                  style={{ color: primaryColor }}
                >
                  <ArrowLeft className="h-4 w-4" />
                  Back to sign in
                </Link>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
