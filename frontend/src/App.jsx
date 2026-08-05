import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import { useCustomerAuth } from './context/CustomerAuthContext';
import { useBranding } from './context/BrandingContext';

// Layouts
import MainLayout from './components/common/MainLayout';
import PortalLayout from './components/portal/PortalLayout';
import LoadingSpinner from './components/common/LoadingSpinner';

// Staff Pages
import LandingPage from './pages/LandingPage';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import DashboardPage from './pages/DashboardPage';
import WorkOrdersPage from './pages/WorkOrdersPage';
import WorkOrderDetailPage from './pages/WorkOrderDetailPage';
import WorkOrderFormPage from './pages/WorkOrderFormPage';
import DispatchPage from './pages/DispatchPage';
import InventoryPage from './pages/InventoryPage';
import TechnicianPage from './pages/TechnicianPage';
import GPSMapPage from './pages/GPSMapPage';
import PTTChannelsPage from './pages/PTTChannelsPage';
import SettingsPage from './pages/SettingsPage';
import ReportsPage from './pages/ReportsPage';
import TeamPage from './pages/TeamPage';

// Legacy single-WO token portal (keep for backward compat)
import CustomerPortalPage from './pages/CustomerPortalPage';

// Customer Portal Pages
import PortalLoginPage from './pages/portal/PortalLoginPage';
import PortalRegisterPage from './pages/portal/PortalRegisterPage';
import PortalForgotPasswordPage from './pages/portal/PortalForgotPasswordPage';
import PortalDashboardPage from './pages/portal/PortalDashboardPage';
import PortalWorkOrdersPage from './pages/portal/PortalWorkOrdersPage';
import PortalWorkOrderDetailPage from './pages/portal/PortalWorkOrderDetailPage';
import PortalInvoicesPage from './pages/portal/PortalInvoicesPage';
import PortalInvoiceDetailPage from './pages/portal/PortalInvoiceDetailPage';
import PortalRequestServicePage from './pages/portal/PortalRequestServicePage';
import PortalAccountPage from './pages/portal/PortalAccountPage';

// ─── Route guards ──────────────────────────────────────

function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();
  if (loading) return <LoadingSpinner />;
  if (!isAuthenticated) return <Navigate to="/welcome" replace />;
  return children;
}

function CustomerProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useCustomerAuth();
  if (loading) return <LoadingSpinner />;
  if (!isAuthenticated) return <Navigate to="../login" relative="path" replace />;
  return children;
}

function PublicHome() {
  const { isAuthenticated, loading } = useAuth();
  const { isCustomDomain } = useBranding();
  if (loading) return <LoadingSpinner />;
  if (isCustomDomain) return <Navigate to="/login" replace />; // Custom domain = portal login
  if (isAuthenticated) return <Navigate to="/dashboard" replace />;
  return <LandingPage />;
}

// ─── App ───────────────────────────────────────────────

function App() {
  const { loading: staffLoading } = useAuth();
  const { loading: brandingLoading, isCustomDomain } = useBranding();

  if (staffLoading || brandingLoading) {
    return <LoadingSpinner />;
  }

  // Custom domain: show only portal routes
  if (isCustomDomain) {
    return (
      <Routes>
        <Route path="/login" element={<PortalLoginPage />} />
        <Route path="/register" element={<PortalRegisterPage />} />
        <Route path="/forgot-password" element={<PortalForgotPasswordPage />} />

        <Route path="/" element={<CustomerProtectedRoute><PortalLayout /></CustomerProtectedRoute>}>
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="dashboard" element={<PortalDashboardPage />} />
          <Route path="work-orders" element={<PortalWorkOrdersPage />} />
          <Route path="work-orders/:id" element={<PortalWorkOrderDetailPage />} />
          <Route path="invoices" element={<PortalInvoicesPage />} />
          <Route path="invoices/:id" element={<PortalInvoiceDetailPage />} />
          <Route path="request-service" element={<PortalRequestServicePage />} />
          <Route path="account" element={<PortalAccountPage />} />
        </Route>

        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    );
  }

  // FSM host: show staff app + portal routes under /portal/:slug
  return (
    <Routes>
      {/* Public */}
      <Route path="/welcome" element={<LandingPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />

      {/* Legacy single-WO token portal */}
      <Route path="/portal/status/:token" element={<CustomerPortalPage />} />

      {/* Customer portal (under /portal/:slug) */}
      <Route path="/portal/:slug/login" element={<PortalLoginPage />} />
      <Route path="/portal/:slug/register" element={<PortalRegisterPage />} />
      <Route path="/portal/:slug/forgot-password" element={<PortalForgotPasswordPage />} />

      <Route path="/portal/:slug" element={<CustomerProtectedRoute><PortalLayout /></CustomerProtectedRoute>}>
        <Route index element={<Navigate to="dashboard" replace />} />
        <Route path="dashboard" element={<PortalDashboardPage />} />
        <Route path="work-orders" element={<PortalWorkOrdersPage />} />
        <Route path="work-orders/:id" element={<PortalWorkOrderDetailPage />} />
        <Route path="invoices" element={<PortalInvoicesPage />} />
        <Route path="invoices/:id" element={<PortalInvoiceDetailPage />} />
        <Route path="request-service" element={<PortalRequestServicePage />} />
        <Route path="account" element={<PortalAccountPage />} />
      </Route>

      {/* Staff app */}
      <Route
        path="/"
        element={<ProtectedRoute><MainLayout /></ProtectedRoute>}
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="dashboard" element={<DashboardPage />} />
        <Route path="work-orders" element={<WorkOrdersPage />} />
        <Route path="work-orders/new" element={<WorkOrderFormPage />} />
        <Route path="work-orders/:id" element={<WorkOrderDetailPage />} />
        <Route path="work-orders/:id/edit" element={<WorkOrderFormPage />} />
        <Route path="dispatch" element={<DispatchPage />} />
        <Route path="inventory" element={<InventoryPage />} />
        <Route path="gps" element={<GPSMapPage />} />
        <Route path="ptt" element={<PTTChannelsPage />} />
        <Route path="reports" element={<ReportsPage />} />
        <Route path="team" element={<TeamPage />} />
        <Route path="settings" element={<SettingsPage />} />
        <Route path="technician" element={<TechnicianPage />} />
      </Route>

      <Route path="*" element={<PublicHome />} />
    </Routes>
  );
}

export default App;
