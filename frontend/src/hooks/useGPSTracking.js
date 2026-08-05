import { useState, useEffect, useRef, useCallback } from 'react';
import { gps } from '../api/client';
import { getSettings } from './useSettings';

export function useGPSTracking({ enabled = false, currentOrderId = null }) {
  const [status, setStatus] = useState('idle'); // idle | requesting | tracking | denied | unavailable | error
  const [lastUpdate, setLastUpdate] = useState(null);
  const watchRef = useRef(null);
  const intervalRef = useRef(null);

  const requestPermission = useCallback(async () => {
    if (!navigator.geolocation) {
      setStatus('unavailable');
      return false;
    }

    // Check existing permission via Permissions API if available
    if (navigator.permissions) {
      try {
        const result = await navigator.permissions.query({ name: 'geolocation' });
        if (result.state === 'denied') {
          setStatus('denied');
          return false;
        }
        if (result.state === 'granted') {
          return true;
        }
      } catch {
        // Permissions API not fully supported, fall through to prompt
      }
    }

    // Trigger the browser permission prompt by requesting a single position
    setStatus('requesting');
    return new Promise((resolve) => {
      navigator.geolocation.getCurrentPosition(
        () => {
          resolve(true);
        },
        (err) => {
          if (err.code === 1) {
            setStatus('denied');
          } else {
            setStatus('error');
          }
          resolve(false);
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    });
  }, []);

  const sendLocation = useCallback(
    async (position) => {
      try {
        await gps.updateLocation({
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          altitude: position.coords.altitude,
          heading: position.coords.heading,
          speed: position.coords.speed,
          work_order_id: currentOrderId || undefined,
        });
        setLastUpdate(new Date());
        setStatus('tracking');
      } catch (err) {
        console.error('GPS update failed:', err);
        setStatus('error');
      }
    },
    [currentOrderId]
  );

  const startTracking = useCallback(() => {
    if (!navigator.geolocation) {
      setStatus('unavailable');
      return;
    }

    const settings = getSettings();
    const { highAccuracy, updateInterval } = settings.location;

    // Stop any existing tracking
    if (watchRef.current !== null) {
      navigator.geolocation.clearWatch(watchRef.current);
    }
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
    }

    setStatus('tracking');

    // Get initial position immediately
    navigator.geolocation.getCurrentPosition(sendLocation, () => {}, {
      enableHighAccuracy: highAccuracy,
      timeout: 15000,
    });

    // Set up interval-based updates (more reliable than watchPosition for periodic sends)
    intervalRef.current = setInterval(() => {
      navigator.geolocation.getCurrentPosition(sendLocation, () => {}, {
        enableHighAccuracy: highAccuracy,
        timeout: 15000,
      });
    }, updateInterval * 1000);
  }, [sendLocation]);

  const stopTracking = useCallback(() => {
    if (watchRef.current !== null) {
      navigator.geolocation.clearWatch(watchRef.current);
      watchRef.current = null;
    }
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
    }
    setStatus('idle');
  }, []);

  // Auto-start/stop based on enabled prop
  useEffect(() => {
    if (enabled) {
      requestPermission().then((granted) => {
        if (granted) {
          startTracking();
        }
      });
    } else {
      stopTracking();
    }

    return stopTracking;
  }, [enabled, requestPermission, startTracking, stopTracking]);

  // Single manual update (for the "Update Location" button)
  const updateNow = useCallback(async () => {
    if (!navigator.geolocation) {
      setStatus('unavailable');
      return;
    }

    const granted = await requestPermission();
    if (!granted) return;

    setStatus('requesting');
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        await sendLocation(pos);
      },
      (err) => {
        console.error('GPS error:', err);
        setStatus('error');
        setTimeout(() => setStatus('idle'), 3000);
      },
      { enableHighAccuracy: true, timeout: 15000 }
    );
  }, [requestPermission, sendLocation]);

  return {
    status,
    lastUpdate,
    requestPermission,
    startTracking,
    stopTracking,
    updateNow,
  };
}
