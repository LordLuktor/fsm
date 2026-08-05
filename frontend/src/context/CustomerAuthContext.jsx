import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { portalAuth, portalProfile } from '../api/portalClient';

const CustomerAuthContext = createContext(null);

export function CustomerAuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    const token = localStorage.getItem('fsm_customer_token');
    if (token) {
      loadUser();
    } else {
      setLoading(false);
    }
  }, []);

  const loadUser = async () => {
    try {
      const { data } = await portalProfile.get();
      setUser(data);
      setIsAuthenticated(true);
    } catch {
      localStorage.removeItem('fsm_customer_token');
      localStorage.removeItem('fsm_customer_user');
      setIsAuthenticated(false);
    } finally {
      setLoading(false);
    }
  };

  const login = useCallback(async (email, password) => {
    const { data } = await portalAuth.login(email, password);
    localStorage.setItem('fsm_customer_token', data.token);
    localStorage.setItem('fsm_customer_user', JSON.stringify(data.user));
    setUser(data.user);
    setIsAuthenticated(true);
    return data;
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem('fsm_customer_token');
    localStorage.removeItem('fsm_customer_user');
    setUser(null);
    setIsAuthenticated(false);
  }, []);

  return (
    <CustomerAuthContext.Provider
      value={{ user, loading, isAuthenticated, login, logout, loadUser }}
    >
      {children}
    </CustomerAuthContext.Provider>
  );
}

export function useCustomerAuth() {
  const ctx = useContext(CustomerAuthContext);
  if (!ctx) throw new Error('useCustomerAuth must be inside CustomerAuthProvider');
  return ctx;
}
