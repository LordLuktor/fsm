import { useState, useEffect } from 'react';
import { useNavigate, Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { demo } from '../api/client';
import LoadingSpinner from '../components/common/LoadingSpinner';

// Strip HTML tags from API error messages
const stripHtml = (html) => {
  if (!html || typeof html !== 'string') return html;
  const doc = new DOMParser().parseFromString(html, 'text/html');
  return doc.body.textContent || '';
};

function LoginPage() {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showDemoOptions, setShowDemoOptions] = useState(false);
  const [demoTemplates, setDemoTemplates] = useState(null);
  const [loadingDemo, setLoadingDemo] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState(null);

  const { login, isAuthenticated } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    // Load demo templates
    demo.getTemplates()
      .then(({ data }) => setDemoTemplates(data.templates))
      .catch(() => setDemoTemplates(null));
  }, []);

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      await login(username, password);
      navigate('/dashboard');
    } catch (err) {
      setError(stripHtml(err.message) || 'Login failed. Please check your credentials.');
    } finally {
      setLoading(false);
    }
  };

  const handleDemoLogin = async (template) => {
    setSelectedTemplate(template);
    setLoadingDemo(true);
    setError('');

    try {
      const { data } = await demo.login(template, 'admin');

      // Store token and demo flag
      localStorage.setItem('fsm_token', data.token);
      localStorage.setItem('fsm_is_demo', 'true');
      localStorage.setItem('fsm_demo_template', template);
      localStorage.setItem('fsm_demo_org_name', data.organization?.name || '');

      // Reload to trigger auth context
      window.location.href = '/';
    } catch (err) {
      setError(stripHtml(err.response?.data?.message) || 'Failed to start demo. Please try again.');
      setSelectedTemplate(null);
    } finally {
      setLoadingDemo(false);
    }
  };

  const templateIcons = {
    plumbing: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-8 h-8">
        <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
      </svg>
    ),
    electrical: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-8 h-8">
        <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
      </svg>
    ),
    hvac: (
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-8 h-8">
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
      </svg>
    ),
  };

  const templateColors = {
    plumbing: 'bg-blue-500 hover:bg-blue-600',
    electrical: 'bg-yellow-500 hover:bg-yellow-600',
    hvac: 'bg-green-500 hover:bg-green-600',
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-md w-full space-y-8">
        <div>
          <h1 className="text-center text-4xl font-bold text-primary-600">FSM</h1>
          <h2 className="mt-2 text-center text-xl text-gray-600">
            Field Service Management
          </h2>
          <p className="mt-2 text-center text-sm text-gray-500">
            {showDemoOptions ? 'Select a demo to explore' : 'Sign in to your account'}
          </p>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md text-sm">
            {error}
          </div>
        )}

        {showDemoOptions ? (
          <div className="bg-white p-8 rounded-lg shadow space-y-6">
            <h3 className="text-lg font-medium text-gray-900 text-center">
              Choose an Industry Demo
            </h3>

            <div className="grid gap-4">
              {demoTemplates && Object.entries(demoTemplates).map(([key, template]) => (
                <button
                  key={key}
                  onClick={() => handleDemoLogin(key)}
                  disabled={loadingDemo}
                  className={`${templateColors[key]} text-white p-4 rounded-lg flex items-center gap-4 transition-colors disabled:opacity-50 disabled:cursor-not-allowed`}
                >
                  <div className="flex-shrink-0">
                    {selectedTemplate === key && loadingDemo ? (
                      <LoadingSpinner size="small" className="border-white border-t-transparent" />
                    ) : (
                      templateIcons[key]
                    )}
                  </div>
                  <div className="text-left">
                    <div className="font-semibold">{template.name}</div>
                    <div className="text-sm opacity-90">{template.company}</div>
                  </div>
                </button>
              ))}
            </div>

            <p className="text-xs text-gray-500 text-center">
              Demo data resets when you log out. Feel free to explore and make changes!
            </p>

            <button
              onClick={() => setShowDemoOptions(false)}
              className="w-full text-center text-sm text-gray-600 hover:text-gray-900"
            >
              Back to login
            </button>
          </div>
        ) : (
          <>
            <form className="mt-8 space-y-6 bg-white p-8 rounded-lg shadow" onSubmit={handleSubmit}>
              <div className="space-y-4">
                <div>
                  <label htmlFor="username" className="block text-sm font-medium text-gray-700">
                    Username
                  </label>
                  <input
                    id="username"
                    name="username"
                    type="text"
                    autoComplete="username"
                    required
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    className="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Enter your username"
                  />
                </div>

                <div>
                  <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                    Password
                  </label>
                  <div className="relative mt-1">
                    <input
                      id="password"
                      name="password"
                      type={showPassword ? 'text' : 'password'}
                      autoComplete="current-password"
                      required
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="block w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                      placeholder="Enter your password"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                    >
                      {showPassword ? (
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                      ) : (
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                          <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                      )}
                    </button>
                  </div>
                </div>
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {loading ? (
                  <LoadingSpinner size="small" className="border-white border-t-transparent" />
                ) : (
                  'Sign in'
                )}
              </button>
            </form>

            {demoTemplates && (
              <div className="text-center">
                <div className="relative">
                  <div className="absolute inset-0 flex items-center">
                    <div className="w-full border-t border-gray-300"></div>
                  </div>
                  <div className="relative flex justify-center text-sm">
                    <span className="px-2 bg-gray-100 text-gray-500">or</span>
                  </div>
                </div>

                <button
                  onClick={() => setShowDemoOptions(true)}
                  className="mt-4 w-full flex justify-center items-center gap-2 py-2 px-4 border-2 border-primary-500 rounded-md text-sm font-medium text-primary-600 bg-white hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-5 h-5">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                  </svg>
                  Try a Demo
                </button>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

export default LoginPage;
