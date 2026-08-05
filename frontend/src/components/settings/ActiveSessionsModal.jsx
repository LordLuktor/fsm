import { useState, useEffect } from 'react';
import { account } from '../../api/client';
import { Monitor, Smartphone, Globe, Loader2, Trash2, Check, Shield } from 'lucide-react';
import Modal from '../common/Modal';

export default function ActiveSessionsModal({ isOpen, onClose }) {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [destroying, setDestroying] = useState(false);
  const [destroyed, setDestroyed] = useState(false);

  useEffect(() => {
    if (isOpen) {
      loadSessions();
      setDestroyed(false);
    }
  }, [isOpen]);

  const loadSessions = async () => {
    setLoading(true);
    try {
      const { data } = await account.getSessions();
      setSessions(data.sessions || []);
    } catch (err) {
      console.error('Failed to load sessions:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleDestroyOthers = async () => {
    if (!confirm('This will log you out of all other devices. Continue?')) return;

    setDestroying(true);
    try {
      await account.destroyOtherSessions();
      setDestroyed(true);
      loadSessions();
    } catch (err) {
      console.error('Failed to destroy sessions:', err);
    } finally {
      setDestroying(false);
    }
  };

  const getDeviceIcon = (session) => {
    const ua = (session.ua || '').toLowerCase();
    if (ua.includes('mobile') || ua.includes('android') || ua.includes('iphone')) {
      return Smartphone;
    }
    return Monitor;
  };

  const formatDate = (timestamp) => {
    if (!timestamp) return 'Unknown';
    return new Date(timestamp * 1000).toLocaleString();
  };

  const otherSessionCount = sessions.filter((s) => !s.is_current).length;

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Active Sessions" size="lg">
      {loading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
        </div>
      ) : (
        <div className="space-y-4">
          {destroyed && (
            <div className="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
              <Check className="h-4 w-4" />
              All other sessions have been terminated.
            </div>
          )}

          <p className="text-sm text-gray-500">
            {sessions.length} active session{sessions.length !== 1 ? 's' : ''}
          </p>

          <div className="space-y-3 max-h-80 overflow-y-auto">
            {sessions.map((session, i) => {
              const DeviceIcon = getDeviceIcon(session);

              return (
                <div
                  key={i}
                  className={`flex items-center gap-4 p-4 rounded-lg border-2 ${
                    session.is_current
                      ? 'border-green-200 bg-green-50'
                      : 'border-gray-200 bg-white'
                  }`}
                >
                  <div className={`w-10 h-10 rounded-lg flex items-center justify-center ${
                    session.is_current ? 'bg-green-100' : 'bg-gray-100'
                  }`}>
                    <DeviceIcon className={`h-5 w-5 ${
                      session.is_current ? 'text-green-600' : 'text-gray-500'
                    }`} />
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="font-medium text-gray-900 text-sm">
                        {session.browser || 'Unknown Browser'}
                      </p>
                      {session.is_current && (
                        <span className="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">
                          This device
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-gray-500 mt-0.5">
                      <Globe className="h-3 w-3 inline mr-1" />
                      {session.ip || 'Unknown IP'}
                    </p>
                    <p className="text-xs text-gray-400 mt-0.5">
                      Logged in: {formatDate(session.login)}
                    </p>
                  </div>
                </div>
              );
            })}
          </div>

          {otherSessionCount > 0 && (
            <div className="pt-4 border-t border-gray-100">
              <button
                onClick={handleDestroyOthers}
                disabled={destroying}
                className="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-700 font-medium rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50"
              >
                {destroying ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Trash2 className="h-4 w-4" />
                )}
                Log Out All Other Sessions ({otherSessionCount})
              </button>
            </div>
          )}
        </div>
      )}
    </Modal>
  );
}
