import { useState, useEffect, useCallback, useRef } from 'react';
import { gps, workOrders, inventory } from '../api/client';

const QUEUE_KEY = 'fsm_offline_queue';
const CACHE_KEY = 'fsm_offline_cache';

function loadQueue() {
  try {
    return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
  } catch {
    return [];
  }
}

function saveQueue(queue) {
  localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
}

const API_MAP = {
  'gps.updateLocation': (data) => gps.updateLocation(data),
  'workOrders.updateStatus': ({ id, status }) => workOrders.updateStatus(id, status),
  'workOrders.addNote': ({ id, content, type }) => workOrders.addNote(id, content, type),
  'inventory.consume': ({ itemId, quantity, workOrderId, notes }) =>
    inventory.consume(itemId, quantity, workOrderId, notes),
};

export function useOfflineQueue() {
  const [online, setOnline] = useState(navigator.onLine);
  const [queue, setQueue] = useState(loadQueue);
  const [syncing, setSyncing] = useState(false);
  const syncingRef = useRef(false);

  // Track online/offline status
  useEffect(() => {
    const goOnline = () => {
      setOnline(true);
      flush();
    };
    const goOffline = () => setOnline(false);

    window.addEventListener('online', goOnline);
    window.addEventListener('offline', goOffline);

    return () => {
      window.removeEventListener('online', goOnline);
      window.removeEventListener('offline', goOffline);
    };
  }, []);

  // Enqueue an API call for later execution
  const enqueue = useCallback((action, data) => {
    const entry = {
      id: `${Date.now()}_${Math.random().toString(36).slice(2)}`,
      action,
      data,
      createdAt: new Date().toISOString(),
      attempts: 0,
    };

    setQueue((prev) => {
      const next = [...prev, entry];
      saveQueue(next);
      return next;
    });

    // If we're online, try to flush immediately
    if (navigator.onLine) {
      setTimeout(flush, 100);
    }

    return entry.id;
  }, []);

  // Process the queue
  const flush = useCallback(async () => {
    if (syncingRef.current) return;
    syncingRef.current = true;
    setSyncing(true);

    const current = loadQueue();
    const remaining = [];

    for (const entry of current) {
      const handler = API_MAP[entry.action];
      if (!handler) {
        console.warn('Unknown offline action:', entry.action);
        continue;
      }

      try {
        await handler(entry.data);
      } catch (err) {
        entry.attempts++;
        if (entry.attempts < 5) {
          remaining.push(entry);
        } else {
          console.error('Dropping failed offline entry after 5 attempts:', entry);
        }
      }
    }

    saveQueue(remaining);
    setQueue(remaining);
    syncingRef.current = false;
    setSyncing(false);
  }, []);

  // Cache data for offline reading
  const cacheData = useCallback((key, data) => {
    try {
      const cache = JSON.parse(localStorage.getItem(CACHE_KEY) || '{}');
      cache[key] = { data, cachedAt: new Date().toISOString() };
      localStorage.setItem(CACHE_KEY, JSON.stringify(cache));
    } catch {
      // Storage full or parse error
    }
  }, []);

  const getCachedData = useCallback((key) => {
    try {
      const cache = JSON.parse(localStorage.getItem(CACHE_KEY) || '{}');
      return cache[key]?.data || null;
    } catch {
      return null;
    }
  }, []);

  return {
    online,
    queue,
    queueLength: queue.length,
    syncing,
    enqueue,
    flush,
    cacheData,
    getCachedData,
  };
}
