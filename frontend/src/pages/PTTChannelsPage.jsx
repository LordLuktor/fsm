import { useState, useEffect } from 'react';
import { ptt } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  Radio,
  Plus,
  Users,
  Lock,
  Unlock,
  ExternalLink,
  Copy,
  Check,
  Loader2,
  RefreshCw,
  Headphones,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';
import Modal from '../components/common/Modal';

function CreateChannelModal({ isOpen, onClose, onSuccess }) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isPrivate, setIsPrivate] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!name.trim()) {
      setError('Channel name is required');
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await ptt.createChannel({
        name: name.trim(),
        description: description.trim(),
        is_private: isPrivate,
      });
      onSuccess?.();
      onClose();
      setName('');
      setDescription('');
      setIsPrivate(false);
    } catch (err) {
      console.error('Failed to create channel:', err);
      setError(err.response?.data?.message || 'Failed to create channel');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Create PTT Channel" size="md">
      <form onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Channel Name <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g., Team Alpha"
              className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Description
            </label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Channel description..."
              rows={2}
              className="w-full px-4 py-2.5 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all resize-none"
            />
          </div>

          <div>
            <label className="flex items-center gap-3 cursor-pointer">
              <button
                type="button"
                onClick={() => setIsPrivate(!isPrivate)}
                className={`relative w-12 h-6 rounded-full transition-colors ${
                  isPrivate ? 'bg-primary-600' : 'bg-gray-200'
                }`}
              >
                <span
                  className={`absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-transform ${
                    isPrivate ? 'left-7' : 'left-1'
                  }`}
                />
              </button>
              <div>
                <span className="font-medium text-gray-900">Private Channel</span>
                <p className="text-sm text-gray-500">
                  Only invited members can join
                </p>
              </div>
            </label>
          </div>
        </div>

        <div className="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Creating...
              </>
            ) : (
              <>
                <Plus className="h-4 w-4" />
                Create Channel
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}

function ConnectionInfoCard({ connectionInfo }) {
  const [copied, setCopied] = useState(null);

  const copyToClipboard = async (text, field) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(field);
      setTimeout(() => setCopied(null), 2000);
    } catch (err) {
      console.error('Failed to copy:', err);
    }
  };

  if (!connectionInfo) return null;

  return (
    <div className="bg-gradient-to-br from-primary-600 to-primary-700 rounded-xl p-6 text-white">
      <div className="flex items-center gap-3 mb-4">
        <div className="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center">
          <Headphones className="h-6 w-6" />
        </div>
        <div>
          <h3 className="font-semibold text-lg">Mumble Server</h3>
          <p className="text-primary-100 text-sm">Push-to-Talk Communication</p>
        </div>
      </div>

      <div className="space-y-3">
        <div className="flex items-center justify-between p-3 bg-white/10 rounded-lg">
          <div>
            <p className="text-xs text-primary-200">Server</p>
            <p className="font-mono">{connectionInfo.host}</p>
          </div>
          <button
            onClick={() => copyToClipboard(connectionInfo.host, 'host')}
            className="p-2 hover:bg-white/10 rounded-lg transition-colors"
          >
            {copied === 'host' ? (
              <Check className="h-4 w-4" />
            ) : (
              <Copy className="h-4 w-4" />
            )}
          </button>
        </div>

        <div className="flex items-center justify-between p-3 bg-white/10 rounded-lg">
          <div>
            <p className="text-xs text-primary-200">Port</p>
            <p className="font-mono">{connectionInfo.port}</p>
          </div>
          <button
            onClick={() => copyToClipboard(String(connectionInfo.port), 'port')}
            className="p-2 hover:bg-white/10 rounded-lg transition-colors"
          >
            {copied === 'port' ? (
              <Check className="h-4 w-4" />
            ) : (
              <Copy className="h-4 w-4" />
            )}
          </button>
        </div>

        {connectionInfo.username && (
          <div className="flex items-center justify-between p-3 bg-white/10 rounded-lg">
            <div>
              <p className="text-xs text-primary-200">Username</p>
              <p className="font-mono">{connectionInfo.username}</p>
            </div>
            <button
              onClick={() => copyToClipboard(connectionInfo.username, 'username')}
              className="p-2 hover:bg-white/10 rounded-lg transition-colors"
            >
              {copied === 'username' ? (
                <Check className="h-4 w-4" />
              ) : (
                <Copy className="h-4 w-4" />
              )}
            </button>
          </div>
        )}
      </div>

      {connectionInfo.instructions?.native_client && (
        <a
          href={connectionInfo.instructions.native_client}
          className="mt-4 w-full flex items-center justify-center gap-2 px-4 py-3 bg-white text-primary-700 font-medium rounded-lg hover:bg-primary-50 transition-colors"
        >
          <ExternalLink className="h-4 w-4" />
          Open in Mumble
        </a>
      )}
    </div>
  );
}

function ChannelCard({ channel }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-primary-100 flex items-center justify-center">
            <Radio className="h-5 w-5 text-primary-600" />
          </div>
          <div>
            <h3 className="font-semibold text-gray-900">{channel.name}</h3>
            {channel.description && (
              <p className="text-sm text-gray-500 mt-0.5">{channel.description}</p>
            )}
          </div>
        </div>
        {channel.is_private ? (
          <Lock className="h-4 w-4 text-gray-400" />
        ) : (
          <Unlock className="h-4 w-4 text-gray-400" />
        )}
      </div>

      <div className="flex items-center justify-between pt-3 border-t border-gray-100">
        <div className="flex items-center gap-1 text-sm text-gray-500">
          <Users className="h-4 w-4" />
          <span>{channel.member_count || 0} members</span>
        </div>

        {channel.is_online && (
          <span className="flex items-center gap-1.5 text-sm">
            <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
            <span className="text-green-600 font-medium">Active</span>
          </span>
        )}
      </div>
    </div>
  );
}

export default function PTTChannelsPage() {
  const { hasCapability, isDispatcher } = useAuth();
  const [channels, setChannels] = useState([]);
  const [connectionInfo, setConnectionInfo] = useState(null);
  const [loading, setLoading] = useState(true);
  const [createModalOpen, setCreateModalOpen] = useState(false);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    setLoading(true);
    try {
      const [channelsRes, connectionRes] = await Promise.all([
        isDispatcher ? ptt.getAllChannels() : ptt.getMyChannels(),
        ptt.getConnectionInfo(),
      ]);
      setChannels(channelsRes.data || []);
      setConnectionInfo(connectionRes.data);
    } catch (err) {
      console.error('Failed to load PTT data:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="medium" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center">
            <Radio className="h-6 w-6 mr-2" />
            Push-to-Talk
          </h1>
          <p className="text-gray-500 mt-1">
            Manage communication channels
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={loadData}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            title="Refresh"
          >
            <RefreshCw className="h-5 w-5 text-gray-600" />
          </button>

          {hasCapability('fsm_manage_ptt') && (
            <button
              onClick={() => setCreateModalOpen(true)}
              className="btn btn-primary inline-flex items-center"
            >
              <Plus className="h-4 w-4 mr-2" />
              Create Channel
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Connection Info */}
        <div className="lg:col-span-1">
          <ConnectionInfoCard connectionInfo={connectionInfo} />
        </div>

        {/* Channels */}
        <div className="lg:col-span-2">
          <div className="bg-white rounded-xl border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-100">
              <h2 className="font-semibold text-gray-900">
                {isDispatcher ? 'All Channels' : 'My Channels'} ({channels.length})
              </h2>
            </div>

            {channels.length === 0 ? (
              <div className="p-12 text-center">
                <Radio className="h-12 w-12 mx-auto text-gray-300 mb-4" />
                <p className="text-gray-500">No channels available</p>
                {hasCapability('fsm_manage_ptt') && (
                  <button
                    onClick={() => setCreateModalOpen(true)}
                    className="mt-4 text-primary-600 hover:text-primary-700 font-medium"
                  >
                    Create your first channel
                  </button>
                )}
              </div>
            ) : (
              <div className="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                {channels.map((channel) => (
                  <ChannelCard key={channel.id} channel={channel} />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Create Channel Modal */}
      <CreateChannelModal
        isOpen={createModalOpen}
        onClose={() => setCreateModalOpen(false)}
        onSuccess={loadData}
      />
    </div>
  );
}
