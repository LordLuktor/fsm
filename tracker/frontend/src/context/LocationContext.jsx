import { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import { gps, sos as sosApi } from '../api/client';
import { useAuth } from './AuthContext';

const LocationContext = createContext(null);

const REFRESH_INTERVAL = 15000; // 15 seconds

export function LocationProvider({ children }) {
  const { isAuthenticated } = useAuth();
  const [familyLocations, setFamilyLocations] = useState([]);
  const [sosActive, setSosActive] = useState(false);
  const [loading, setLoading] = useState(false);
  const intervalRef = useRef(null);

  const fetchLocations = useCallback(async () => {
    if (!isAuthenticated) return;
    try {
      setLoading(true);
      const { data } = await gps.getFamilyLocations();
      setFamilyLocations(data);
    } catch {
      // ignore
    } finally {
      setLoading(false);
    }
  }, [isAuthenticated]);

  // Auto-refresh locations
  useEffect(() => {
    if (!isAuthenticated) {
      setFamilyLocations([]);
      return;
    }

    fetchLocations();
    intervalRef.current = setInterval(fetchLocations, REFRESH_INTERVAL);

    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current);
    };
  }, [isAuthenticated, fetchLocations]);

  // Update own location via browser geolocation
  const updateOwnLocation = useCallback(async () => {
    if (!navigator.geolocation) return;

    return new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(
        async (position) => {
          try {
            const { data } = await gps.updateLocation({
              latitude: position.coords.latitude,
              longitude: position.coords.longitude,
              accuracy: position.coords.accuracy,
              altitude: position.coords.altitude,
              heading: position.coords.heading,
              speed: position.coords.speed,
            });
            resolve(data);
          } catch (err) {
            reject(err);
          }
        },
        reject,
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
      );
    });
  }, []);

  // Trigger SOS
  const triggerSOS = useCallback(async (message) => {
    setSosActive(true);
    try {
      // Get current position
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          enableHighAccuracy: true, timeout: 5000,
        });
      });

      await sosApi.trigger({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        message,
      });
    } catch {
      // Try SOS without location
      await sosApi.trigger({ message });
    }
  }, []);

  const cancelSOS = useCallback(() => {
    setSosActive(false);
  }, []);

  return (
    <LocationContext.Provider value={{
      familyLocations, loading, sosActive,
      fetchLocations, updateOwnLocation, triggerSOS, cancelSOS,
    }}>
      {children}
    </LocationContext.Provider>
  );
}

export function useLocations() {
  const ctx = useContext(LocationContext);
  if (!ctx) throw new Error('useLocations must be used within LocationProvider');
  return ctx;
}
