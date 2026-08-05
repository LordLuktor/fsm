import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { subscription } from '../api/client';
import { useAuth } from './AuthContext';

const SubscriptionContext = createContext(null);

export function SubscriptionProvider({ children }) {
  const { isAuthenticated } = useAuth();
  const [plan, setPlan] = useState('free');
  const [features, setFeatures] = useState({});
  const [limits, setLimits] = useState({});
  const [loading, setLoading] = useState(false);

  const fetchSubscription = useCallback(async () => {
    if (!isAuthenticated) return;
    try {
      setLoading(true);
      const { data } = await subscription.getFeatures();
      setPlan(data.tier);
      setFeatures(data.features || {});
      setLimits(data.limits || {});
    } catch {
      // default to free
    } finally {
      setLoading(false);
    }
  }, [isAuthenticated]);

  useEffect(() => {
    fetchSubscription();
  }, [fetchSubscription]);

  const canUse = useCallback((feature) => {
    return features[feature] === true;
  }, [features]);

  const isPremium = plan === 'premium' || plan === 'ultimate';
  const isUltimate = plan === 'ultimate';

  return (
    <SubscriptionContext.Provider value={{
      plan, features, limits, loading, isPremium, isUltimate,
      canUse, fetchSubscription,
    }}>
      {children}
    </SubscriptionContext.Provider>
  );
}

export function useSubscription() {
  const ctx = useContext(SubscriptionContext);
  if (!ctx) throw new Error('useSubscription must be used within SubscriptionProvider');
  return ctx;
}
