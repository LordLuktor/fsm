import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { auth } from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [member, setMember] = useState(null);
  const [familyData, setFamilyData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem('tracker_token');
    if (token) {
      auth.getMe()
        .then(({ data }) => {
          setUser(data.user);
          setMember(data.member);
          setFamilyData(data.family);
        })
        .catch(() => {
          localStorage.removeItem('tracker_token');
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

  const login = useCallback(async (email, password) => {
    const { data } = await auth.login(email, password);
    localStorage.setItem('tracker_token', data.token);
    setUser(data.user);
    setMember(data.member);
    setFamilyData(data.family);
    return data;
  }, []);

  const register = useCallback(async (formData) => {
    const { data } = await auth.register(formData);
    localStorage.setItem('tracker_token', data.token);
    setUser(data.user);
    setMember(data.member);
    setFamilyData(data.family);
    return data;
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem('tracker_token');
    setUser(null);
    setMember(null);
    setFamilyData(null);
  }, []);

  const refreshUser = useCallback(async () => {
    try {
      const { data } = await auth.getMe();
      setUser(data.user);
      setMember(data.member);
      setFamilyData(data.family);
    } catch {
      // ignore
    }
  }, []);

  const isAuthenticated = !!user;
  const isOwner = user?.is_owner || false;

  return (
    <AuthContext.Provider value={{
      user, member, family: familyData, loading,
      isAuthenticated, isOwner,
      login, register, logout, refreshUser,
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
