import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { auth } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [capabilities, setCapabilities] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const loadUser = useCallback(async () => {
    const token = localStorage.getItem('fsm_token');
    if (!token) {
      setLoading(false);
      return;
    }

    try {
      // Validate token
      await auth.validate();

      // Get user data
      const { data: userData } = await auth.getUser();
      setUser(userData);

      // Get capabilities
      const { data: capsData } = await auth.getCapabilities();
      setCapabilities(capsData.capabilities || {});

      // Store user in localStorage
      localStorage.setItem('fsm_user', JSON.stringify(userData));
    } catch (err) {
      console.error('Auth error:', err);
      localStorage.removeItem('fsm_token');
      localStorage.removeItem('fsm_user');
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadUser();
  }, [loadUser]);

  const login = async (username, password) => {
    setError(null);
    try {
      const { data } = await auth.login(username, password);
      localStorage.setItem('fsm_token', data.token);
      await loadUser();
      return true;
    } catch (err) {
      const message = err.response?.data?.message || 'Login failed';
      setError(message);
      throw new Error(message);
    }
  };

  const logout = () => {
    localStorage.removeItem('fsm_token');
    localStorage.removeItem('fsm_user');
    setUser(null);
    setCapabilities({});
  };

  const hasCapability = (cap) => {
    return capabilities[cap] === true;
  };

  const isTechnician = user?.is_technician === true;
  const isDispatcher = user?.is_dispatcher === true;
  const isAdmin = user?.is_admin === true;

  const value = {
    user,
    capabilities,
    loading,
    error,
    login,
    logout,
    hasCapability,
    isTechnician,
    isDispatcher,
    isAdmin,
    isAuthenticated: !!user,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

export default AuthContext;
