import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import MainLayout from './components/common/MainLayout';
import LoadingSpinner from './components/common/LoadingSpinner';

import LandingPage from './pages/LandingPage';
import LoginPage from './pages/LoginPage';
import RegisterPage from './pages/RegisterPage';
import JoinPage from './pages/JoinPage';
import MapDashboard from './pages/MapDashboard';
import MembersPage from './pages/MembersPage';
import HistoryPage from './pages/HistoryPage';
import GeofencesPage from './pages/GeofencesPage';
import AlertsPage from './pages/AlertsPage';
import PlacesPage from './pages/PlacesPage';
import SettingsPage from './pages/SettingsPage';
import SharedViewPage from './pages/SharedViewPage';

function ProtectedRoute({ children, ownerOnly = false }) {
  const { isAuthenticated, isOwner, loading } = useAuth();

  if (loading) return <LoadingSpinner />;
  if (!isAuthenticated) return <Navigate to="/login" replace />;
  if (ownerOnly && !isOwner) return <Navigate to="/map" replace />;

  return children;
}

export default function App() {
  return (
    <Routes>
      {/* Public routes */}
      <Route path="/" element={<LandingPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/join/:code" element={<JoinPage />} />
      <Route path="/s/:token" element={<SharedViewPage />} />

      {/* Protected routes */}
      <Route path="/map" element={
        <ProtectedRoute>
          <MainLayout><MapDashboard /></MainLayout>
        </ProtectedRoute>
      } />
      <Route path="/members" element={
        <ProtectedRoute ownerOnly>
          <MainLayout><MembersPage /></MainLayout>
        </ProtectedRoute>
      } />
      <Route path="/history/:id" element={
        <ProtectedRoute>
          <MainLayout><HistoryPage /></MainLayout>
        </ProtectedRoute>
      } />
      <Route path="/geofences" element={
        <ProtectedRoute ownerOnly>
          <MainLayout><GeofencesPage /></MainLayout>
        </ProtectedRoute>
      } />
      <Route path="/alerts" element={
        <ProtectedRoute>
          <MainLayout><AlertsPage /></MainLayout>
        </ProtectedRoute>
      } />
      <Route path="/places" element={
        <ProtectedRoute>
          <MainLayout><PlacesPage /></MainLayout>
        </ProtectedRoute>
      } />
      <Route path="/settings" element={
        <ProtectedRoute>
          <MainLayout><SettingsPage /></MainLayout>
        </ProtectedRoute>
      } />

      {/* Fallback */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
