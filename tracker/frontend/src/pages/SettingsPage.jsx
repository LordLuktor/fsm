import { useState, useEffect } from 'react';
import { useAuth } from '../context/AuthContext';
import { useSubscription } from '../context/SubscriptionContext';
import { subscription as subApi, sharing as sharingApi, ptt as pttApi } from '../api/client';
import { useSearchParams } from 'react-router-dom';
import {
  Settings, Crown, CreditCard, Share2, ExternalLink,
  Check, Copy, Trash2, Loader, CheckCircle, Radio,
} from 'lucide-react';

export default function SettingsPage() {
  const { user, family, member, logout } = useAuth();
  const { plan, isPremium, isUltimate, limits, fetchSubscription } = useSubscription();
  const [searchParams] = useSearchParams();
  const [shareLinks, setShareLinks] = useState([]);
  const [checkoutLoading, setCheckoutLoading] = useState(false);
  const [portalLoading, setPortalLoading] = useState(false);
  const [showCheckoutSuccess, setShowCheckoutSuccess] = useState(false);
  const [pttConnection, setPttConnection] = useState(null);
  const [pttLoading, setPttLoading] = useState(false);

  useEffect(() => {
    if (searchParams.get('checkout') === 'success') {
      setShowCheckoutSuccess(true);
      fetchSubscription();
      setTimeout(() => setShowCheckoutSuccess(false), 5000);
    }
  }, [searchParams]);

  useEffect(() => {
    sharingApi.list().then(({ data }) => setShareLinks(data)).catch(() => {});
  }, []);

  useEffect(() => {
    if (isUltimate) {
      setPttLoading(true);
      pttApi.getConnection()
        .then(({ data }) => setPttConnection(data))
        .catch(() => {})
        .finally(() => setPttLoading(false));
    }
  }, [isUltimate]);

  const handleUpgrade = async (targetPlan = 'premium') => {
    setCheckoutLoading(true);
    try {
      const { data } = await subApi.createCheckout(targetPlan);
      window.location.href = data.checkout_url;
    } catch {
      setCheckoutLoading(false);
    }
  };

  const handleManageBilling = async () => {
    setPortalLoading(true);
    try {
      const { data } = await subApi.createPortal();
      window.location.href = data.portal_url;
    } catch {
      setPortalLoading(false);
    }
  };

  const handleCreateShare = async () => {
    try {
      await sharingApi.create({ expires_in: 86400 });
      const { data } = await sharingApi.list();
      setShareLinks(data);
    } catch { /* ignore */ }
  };

  const handleRevokeShare = async (id) => {
    try {
      await sharingApi.revoke(id);
      const { data } = await sharingApi.list();
      setShareLinks(data);
    } catch { /* ignore */ }
  };

  const planLabel = isUltimate ? 'Ultimate' : isPremium ? 'Premium' : 'Free';
  const planPrice = isUltimate ? '$24.95/month' : isPremium ? '$9.99/month' : null;

  const historyLabel = isUltimate ? '30 days' : isPremium ? '14 days' : '24 hours';
  const maxMembers = limits.max_members === 0 ? 'Unlimited' : `${limits.max_members || 2} max`;
  const geofencesLabel = isUltimate ? '20' : isPremium ? '10' : 'Upgrade required';
  const shareLinksLabel = isUltimate ? 'Unlimited' : isPremium ? '5' : 'Upgrade required';

  return (
    <div className="p-4 lg:p-6 max-w-3xl mx-auto space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Settings</h1>

      {showCheckoutSuccess && (
        <div className="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
          <CheckCircle className="w-5 h-5" />
          Subscription activated! You now have {planLabel} access.
        </div>
      )}

      {/* Account */}
      <section className="bg-white rounded-xl border border-gray-200 p-6">
        <h2 className="font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Settings className="w-4 h-4" /> Account
        </h2>
        <div className="grid sm:grid-cols-2 gap-4 text-sm">
          <div>
            <p className="text-gray-500">Name</p>
            <p className="font-medium text-gray-900">{user?.display_name}</p>
          </div>
          <div>
            <p className="text-gray-500">Email</p>
            <p className="font-medium text-gray-900">{user?.email}</p>
          </div>
          <div>
            <p className="text-gray-500">Family</p>
            <p className="font-medium text-gray-900">{family?.name}</p>
          </div>
          <div>
            <p className="text-gray-500">Role</p>
            <p className="font-medium text-gray-900 capitalize">{member?.role || 'member'}</p>
          </div>
        </div>
      </section>

      {/* Subscription */}
      <section className="bg-white rounded-xl border border-gray-200 p-6">
        <h2 className="font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Crown className="w-4 h-4" /> Subscription
        </h2>

        <div className="flex items-center gap-3 mb-4">
          <span className={`px-3 py-1 rounded-full text-sm font-semibold ${
            isUltimate
              ? 'bg-violet-100 text-violet-700'
              : isPremium
                ? 'bg-primary-100 text-primary-700'
                : 'bg-gray-100 text-gray-600'
          }`}>
            {planLabel}
          </span>
          {planPrice && (
            <span className="text-sm text-gray-500">{planPrice}</span>
          )}
        </div>

        <div className="text-sm text-gray-600 space-y-1 mb-4">
          <p>Members: {maxMembers}</p>
          <p>History: {historyLabel}</p>
          <p>Geofences: {geofencesLabel}</p>
          <p>Share links: {shareLinksLabel}</p>
          <p>Push-to-Talk: {isUltimate ? 'Enabled' : 'Upgrade to Ultimate'}</p>
        </div>

        <div className="flex flex-wrap gap-2">
          {isUltimate ? (
            <button
              onClick={handleManageBilling}
              disabled={portalLoading}
              className="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 flex items-center gap-2"
            >
              {portalLoading ? <Loader className="w-4 h-4 animate-spin" /> : <CreditCard className="w-4 h-4" />}
              Manage Billing
            </button>
          ) : isPremium ? (
            <>
              <button
                onClick={() => handleUpgrade('ultimate')}
                disabled={checkoutLoading}
                className="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 flex items-center gap-2"
              >
                {checkoutLoading ? <Loader className="w-4 h-4 animate-spin" /> : <Crown className="w-4 h-4" />}
                Upgrade to Ultimate — $24.95/mo
              </button>
              <button
                onClick={handleManageBilling}
                disabled={portalLoading}
                className="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 flex items-center gap-2"
              >
                {portalLoading ? <Loader className="w-4 h-4 animate-spin" /> : <CreditCard className="w-4 h-4" />}
                Manage Billing
              </button>
            </>
          ) : (
            <div className="flex flex-col sm:flex-row gap-2">
              <button
                onClick={() => handleUpgrade('premium')}
                disabled={checkoutLoading}
                className="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 flex items-center gap-2"
              >
                {checkoutLoading ? <Loader className="w-4 h-4 animate-spin" /> : <Crown className="w-4 h-4" />}
                Upgrade to Premium — $9.99/mo
              </button>
              <button
                onClick={() => handleUpgrade('ultimate')}
                disabled={checkoutLoading}
                className="bg-violet-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-700 flex items-center gap-2"
              >
                {checkoutLoading ? <Loader className="w-4 h-4 animate-spin" /> : <Radio className="w-4 h-4" />}
                Upgrade to Ultimate — $24.95/mo
              </button>
            </div>
          )}
        </div>
      </section>

      {/* Push-to-Talk (Ultimate only) */}
      {isUltimate && (
        <section className="bg-white rounded-xl border border-violet-200 p-6">
          <h2 className="font-semibold text-gray-900 mb-4 flex items-center gap-2">
            <Radio className="w-4 h-4 text-violet-600" /> Push-to-Talk
          </h2>

          {pttLoading ? (
            <div className="flex items-center gap-2 text-sm text-gray-500">
              <Loader className="w-4 h-4 animate-spin" /> Loading connection info...
            </div>
          ) : pttConnection ? (
            <div className="space-y-3">
              <div className="grid sm:grid-cols-2 gap-4 text-sm">
                <div>
                  <p className="text-gray-500">Server</p>
                  <p className="font-medium text-gray-900">{pttConnection.server}</p>
                </div>
                <div>
                  <p className="text-gray-500">Port</p>
                  <p className="font-medium text-gray-900">{pttConnection.port}</p>
                </div>
                <div>
                  <p className="text-gray-500">Username</p>
                  <p className="font-medium text-gray-900">{pttConnection.username}</p>
                </div>
                {pttConnection.defaultChannel && (
                  <div>
                    <p className="text-gray-500">Channel</p>
                    <p className="font-medium text-gray-900">{pttConnection.defaultChannel}</p>
                  </div>
                )}
              </div>
              {pttConnection.connectionUrl && (
                <a
                  href={pttConnection.connectionUrl}
                  className="inline-flex items-center gap-2 bg-violet-50 text-violet-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-violet-100 border border-violet-200"
                >
                  <ExternalLink className="w-4 h-4" />
                  Open in Mumble
                </a>
              )}
              <p className="text-xs text-gray-400">
                Use Mumble (mumble.info) or any compatible PTT app to connect.
              </p>
            </div>
          ) : (
            <p className="text-sm text-gray-500">
              PTT is being set up for your family. Please check back in a moment.
            </p>
          )}
        </section>
      )}

      {/* Share Links */}
      <section className="bg-white rounded-xl border border-gray-200 p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="font-semibold text-gray-900 flex items-center gap-2">
            <Share2 className="w-4 h-4" /> Share Links
          </h2>
          <button
            onClick={handleCreateShare}
            className="text-sm text-primary-600 hover:text-primary-700 font-medium"
          >
            + New Link
          </button>
        </div>

        {shareLinks.length === 0 ? (
          <p className="text-sm text-gray-500">No active share links.</p>
        ) : (
          <div className="space-y-2">
            {shareLinks.map((link) => (
              <div key={link.id} className="flex items-center gap-2 text-sm">
                <code className="flex-1 bg-gray-50 px-2 py-1 rounded text-xs truncate">
                  {link.share_url}
                </code>
                <span className={`text-xs ${link.active ? 'text-green-600' : 'text-gray-400'}`}>
                  {link.active ? 'Active' : 'Revoked'}
                </span>
                {link.active && (
                  <button
                    onClick={() => handleRevokeShare(link.id)}
                    className="text-gray-400 hover:text-red-500"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                )}
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Logout */}
      <button
        onClick={logout}
        className="w-full bg-red-50 text-red-700 py-3 rounded-xl font-medium text-sm hover:bg-red-100 border border-red-200 transition-colors"
      >
        Sign Out
      </button>
    </div>
  );
}
