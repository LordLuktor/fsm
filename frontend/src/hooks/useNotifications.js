import { useState, useEffect, useRef, useCallback } from 'react';
import { workOrders } from '../api/client';
import { getSettings } from './useSettings';

export function useNotifications({ enabled = false, userId = null }) {
  const [permission, setPermission] = useState(
    typeof Notification !== 'undefined' ? Notification.permission : 'unavailable'
  );
  const [lastCheck, setLastCheck] = useState(null);
  const pollRef = useRef(null);
  const knownOrdersRef = useRef(new Set());

  const requestPermission = useCallback(async () => {
    if (typeof Notification === 'undefined') {
      setPermission('unavailable');
      return false;
    }

    if (Notification.permission === 'granted') {
      setPermission('granted');
      return true;
    }

    if (Notification.permission === 'denied') {
      setPermission('denied');
      return false;
    }

    try {
      const result = await Notification.requestPermission();
      setPermission(result);
      return result === 'granted';
    } catch {
      setPermission('denied');
      return false;
    }
  }, []);

  const showNotification = useCallback((title, options = {}) => {
    if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;

    const notif = new Notification(title, {
      icon: '/favicon.svg',
      badge: '/favicon.svg',
      ...options,
    });

    notif.onclick = () => {
      window.focus();
      if (options.url) {
        window.location.href = options.url;
      }
      notif.close();
    };
  }, []);

  const checkForNewAssignments = useCallback(async () => {
    const settings = getSettings();
    if (!settings.notifications.newWorkOrders) return;

    try {
      const { data } = await workOrders.getToday();
      const currentIds = new Set(data.map((o) => o.id));

      // On first check, just seed the known set
      if (knownOrdersRef.current.size === 0) {
        knownOrdersRef.current = currentIds;
        setLastCheck(new Date());
        return;
      }

      // Find new orders not in our known set
      for (const order of data) {
        if (!knownOrdersRef.current.has(order.id)) {
          showNotification('New Work Order Assigned', {
            body: `${order.reference}: ${order.title}\n${order.address?.street || ''}`,
            tag: `wo-${order.id}`,
            url: `/work-orders/${order.id}`,
          });
        }
      }

      knownOrdersRef.current = currentIds;
      setLastCheck(new Date());
    } catch (err) {
      console.error('Notification poll failed:', err);
    }
  }, [showNotification]);

  // Start/stop polling
  useEffect(() => {
    if (!enabled || permission !== 'granted') {
      if (pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
      }
      return;
    }

    // Initial check
    checkForNewAssignments();

    // Poll every 60 seconds for new assignments
    pollRef.current = setInterval(checkForNewAssignments, 60000);

    return () => {
      if (pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
      }
    };
  }, [enabled, permission, checkForNewAssignments]);

  return {
    permission,
    lastCheck,
    requestPermission,
    showNotification,
  };
}
