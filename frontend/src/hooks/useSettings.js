import { useState, useCallback } from 'react';

const STORAGE_KEY = 'fsm_settings';

const defaults = {
  notifications: {
    push: false,
    email: true,
    newWorkOrders: true,
    statusChanges: true,
    reminders: true,
  },
  location: {
    autoUpdate: false,
    highAccuracy: true,
    updateInterval: 30,
  },
};

function loadSettings() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
      const parsed = JSON.parse(stored);
      return {
        notifications: { ...defaults.notifications, ...parsed.notifications },
        location: { ...defaults.location, ...parsed.location },
      };
    }
  } catch {
    // ignore parse errors
  }
  return defaults;
}

export function useSettings() {
  const [settings, setSettingsState] = useState(loadSettings);

  const updateSettings = useCallback((section, values) => {
    setSettingsState((prev) => {
      const next = {
        ...prev,
        [section]: { ...prev[section], ...values },
      };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      return next;
    });
  }, []);

  const updateNotifications = useCallback(
    (values) => updateSettings('notifications', values),
    [updateSettings]
  );

  const updateLocation = useCallback(
    (values) => updateSettings('location', values),
    [updateSettings]
  );

  return {
    settings,
    notifications: settings.notifications,
    location: settings.location,
    updateNotifications,
    updateLocation,
  };
}

// Standalone reader for hooks that just need current values without reactivity
export function getSettings() {
  return loadSettings();
}
