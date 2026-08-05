import { Outlet, Link, useLocation, useNavigate } from 'react-router-dom';
import { useBranding } from '../../context/BrandingContext';
import { useCustomerAuth } from '../../context/CustomerAuthContext';
import {
  LayoutDashboard, ClipboardList, FileText, PlusCircle,
  User, LogOut, Menu, X, Wrench, Phone, Mail,
} from 'lucide-react';
import { useState } from 'react';

const navItems = [
  { to: 'dashboard', icon: LayoutDashboard, label: 'Dashboard' },
  { to: 'work-orders', icon: ClipboardList, label: 'Work Orders' },
  { to: 'invoices', icon: FileText, label: 'Invoices' },
  { to: 'request-service', icon: PlusCircle, label: 'Request Service' },
  { to: 'account', icon: User, label: 'My Account' },
];

export default function PortalLayout() {
  const { branding } = useBranding();
  const { user, logout } = useCustomerAuth();
  const location = useLocation();
  const navigate = useNavigate();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const basePath = location.pathname.split('/').slice(0, 3).join('/'); // /portal/:slug
  const currentPath = location.pathname.replace(basePath + '/', '').split('/')[0];

  const handleLogout = () => {
    logout();
    navigate(basePath + '/login');
  };

  const primaryColor = branding?.primary_color || '#2563eb';

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top nav */}
      <nav className="bg-white border-b border-gray-200 sticky top-0 z-40">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="flex items-center justify-between h-16">
            {/* Logo / Company */}
            <Link to={basePath + '/dashboard'} className="flex items-center gap-3">
              {branding?.logo_url ? (
                <img src={branding.logo_url} alt="" className="h-8 w-8 rounded-lg object-cover" />
              ) : (
                <div
                  className="w-8 h-8 rounded-lg flex items-center justify-center"
                  style={{ backgroundColor: primaryColor }}
                >
                  <Wrench className="w-4 h-4 text-white" />
                </div>
              )}
              <span className="font-bold text-gray-900 text-lg">
                {branding?.company_name || 'Service Portal'}
              </span>
            </Link>

            {/* Desktop nav */}
            <div className="hidden md:flex items-center gap-1">
              {navItems.map((item) => (
                <Link
                  key={item.to}
                  to={basePath + '/' + item.to}
                  className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                    currentPath === item.to
                      ? 'text-white'
                      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                  }`}
                  style={currentPath === item.to ? { backgroundColor: primaryColor } : undefined}
                >
                  <item.icon className="h-4 w-4" />
                  {item.label}
                </Link>
              ))}
            </div>

            {/* User + mobile toggle */}
            <div className="flex items-center gap-3">
              <span className="hidden sm:block text-sm text-gray-500">
                {user?.first_name || user?.email}
              </span>
              <button
                onClick={handleLogout}
                className="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
                title="Log out"
              >
                <LogOut className="h-4 w-4" />
              </button>
              <button
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className="md:hidden p-2 text-gray-400 hover:text-gray-600"
              >
                {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
              </button>
            </div>
          </div>

          {/* Mobile nav */}
          {mobileMenuOpen && (
            <div className="md:hidden pb-4 border-t border-gray-100 pt-2">
              {navItems.map((item) => (
                <Link
                  key={item.to}
                  to={basePath + '/' + item.to}
                  onClick={() => setMobileMenuOpen(false)}
                  className={`flex items-center gap-3 px-3 py-3 rounded-lg text-sm font-medium ${
                    currentPath === item.to
                      ? 'text-white'
                      : 'text-gray-600 hover:bg-gray-100'
                  }`}
                  style={currentPath === item.to ? { backgroundColor: primaryColor } : undefined}
                >
                  <item.icon className="h-4 w-4" />
                  {item.label}
                </Link>
              ))}
            </div>
          )}
        </div>
      </nav>

      {/* Content */}
      <main className="max-w-6xl mx-auto px-4 sm:px-6 py-8">
        <Outlet />
      </main>

      {/* Footer */}
      <footer className="border-t border-gray-200 py-8 mt-8">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-400">
          <div className="flex items-center gap-4">
            {branding?.phone && (
              <a href={`tel:${branding.phone}`} className="flex items-center gap-1 hover:text-gray-600">
                <Phone className="h-3.5 w-3.5" /> {branding.phone}
              </a>
            )}
            {branding?.email && (
              <a href={`mailto:${branding.email}`} className="flex items-center gap-1 hover:text-gray-600">
                <Mail className="h-3.5 w-3.5" /> {branding.email}
              </a>
            )}
          </div>
          <span>Powered by FSM</span>
        </div>
      </footer>
    </div>
  );
}
