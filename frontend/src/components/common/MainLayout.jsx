import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { demo } from '../../api/client';
import {
  LayoutDashboard,
  ClipboardList,
  Calendar,
  Package,
  User,
  Users,
  LogOut,
  Menu,
  X,
  MapPin,
  Radio,
  BarChart3,
  Settings,
  RefreshCw,
  Play,
} from 'lucide-react';
import { useState } from 'react';

function MainLayout() {
  const { user, logout, isDispatcher, isTechnician, isAdmin } = useAuth();
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [resettingDemo, setResettingDemo] = useState(false);

  const isDemo = localStorage.getItem('fsm_is_demo') === 'true';
  const demoTemplate = localStorage.getItem('fsm_demo_template');
  const demoOrgName = localStorage.getItem('fsm_demo_org_name');

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const handleResetDemo = async () => {
    if (!confirm('Reset demo data? All your changes will be lost.')) return;

    setResettingDemo(true);
    try {
      await demo.reset();
      window.location.reload();
    } catch (err) {
      alert('Failed to reset demo. Please try again.');
    } finally {
      setResettingDemo(false);
    }
  };

  const isLeadTech = user?.is_lead_technician === true;

  const navItems = [
    { to: '/dashboard', icon: LayoutDashboard, label: 'Dashboard', show: true },
    { to: '/work-orders', icon: ClipboardList, label: 'Work Orders', show: true },
    { to: '/dispatch', icon: Calendar, label: 'Dispatch', show: isDispatcher || isAdmin || isLeadTech },
    { to: '/gps', icon: MapPin, label: (isTechnician && !isLeadTech) ? 'Nearby Techs' : isLeadTech ? 'My Team Map' : 'GPS Map', show: true },
    { to: '/inventory', icon: Package, label: 'Inventory', show: true },
    { to: '/ptt', icon: Radio, label: 'PTT Channels', show: true },
    { to: '/team', icon: Users, label: 'Team', show: isDispatcher || isAdmin },
    { to: '/reports', icon: BarChart3, label: 'Reports', show: isDispatcher || isAdmin || isLeadTech },
    { to: '/technician', icon: User, label: 'My Dashboard', show: isTechnician || isLeadTech },
    { to: '/settings', icon: Settings, label: 'Settings', show: true },
  ].filter((item) => item.show);

  return (
    <div className="min-h-screen bg-gray-100">
      {/* Mobile sidebar backdrop */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-gray-600 bg-opacity-75 z-20 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`fixed inset-y-0 left-0 z-30 w-64 bg-primary-800 transform transition-transform duration-300 ease-in-out lg:translate-x-0 ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center justify-between h-16 px-4 bg-primary-900">
          <span className="text-xl font-bold text-white">FSM</span>
          <button
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden text-white hover:text-gray-200"
          >
            <X size={24} />
          </button>
        </div>

        <nav className="mt-4 px-2 overflow-y-auto" style={{ maxHeight: 'calc(100vh - 180px)' }}>
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.to === '/'}
              onClick={() => setSidebarOpen(false)}
              className={({ isActive }) =>
                `flex items-center px-4 py-3 mb-1 rounded-md text-sm font-medium transition-colors ${
                  isActive
                    ? 'bg-primary-700 text-white'
                    : 'text-primary-100 hover:bg-primary-700 hover:text-white'
                }`
              }
            >
              <item.icon className="mr-3 h-5 w-5" />
              {item.label}
            </NavLink>
          ))}
        </nav>

        {/* User section */}
        <div className="absolute bottom-0 left-0 right-0 p-4 border-t border-primary-700">
          <div className="flex items-center">
            <div className="w-8 h-8 rounded-full bg-primary-600 flex items-center justify-center text-white text-sm font-medium">
              {user?.display_name?.charAt(0) || 'U'}
            </div>
            <div className="ml-3 flex-1 min-w-0">
              <p className="text-sm font-medium text-white truncate">
                {user?.display_name}
              </p>
              <p className="text-xs text-primary-300 truncate">
                {isAdmin ? 'Admin' : isDispatcher ? 'Dispatcher' : 'Technician'}
              </p>
            </div>
            <button
              onClick={handleLogout}
              className="ml-2 p-2 text-primary-300 hover:text-white rounded-md hover:bg-primary-700"
              title="Logout"
            >
              <LogOut size={18} />
            </button>
          </div>
        </div>
      </aside>

      {/* Main content */}
      <div className="lg:pl-64">
        {/* Demo Banner */}
        {isDemo && (
          <div className="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-4 py-2">
            <div className="flex items-center justify-between max-w-7xl mx-auto">
              <div className="flex items-center gap-2">
                <Play size={16} className="fill-current" />
                <span className="text-sm font-medium">
                  Demo Mode: {demoOrgName || (demoTemplate === 'plumbing' ? 'Plumbing Company' : demoTemplate === 'electrical' ? 'Electrical Company' : 'HVAC Company')}
                </span>
                <span className="text-xs opacity-75">
                  - Changes reset on logout
                </span>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={handleResetDemo}
                  disabled={resettingDemo}
                  className="flex items-center gap-1 px-3 py-1 text-xs font-medium bg-white/20 hover:bg-white/30 rounded-full transition-colors disabled:opacity-50"
                >
                  <RefreshCw size={12} className={resettingDemo ? 'animate-spin' : ''} />
                  Reset Demo
                </button>
                <button
                  onClick={handleLogout}
                  className="flex items-center gap-1 px-3 py-1 text-xs font-medium bg-white/20 hover:bg-white/30 rounded-full transition-colors"
                >
                  <LogOut size={12} />
                  Exit Demo
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Top bar */}
        <header className="sticky top-0 z-10 bg-white shadow-sm">
          <div className="flex items-center justify-between h-16 px-4">
            <button
              onClick={() => setSidebarOpen(true)}
              className="lg:hidden p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100"
            >
              <Menu size={24} />
            </button>
            <div className="flex-1" />
            <div className="text-sm text-gray-500">
              {new Date().toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
              })}
            </div>
          </div>
        </header>

        {/* Page content */}
        <main className="p-4 lg:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

export default MainLayout;
