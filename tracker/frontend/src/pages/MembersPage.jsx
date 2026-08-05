import { useState, useEffect, useCallback } from 'react';
import { family as familyApi } from '../api/client';
import { useAuth } from '../context/AuthContext';
import Modal from '../components/common/Modal';
import {
  Users, UserPlus, Copy, RefreshCw, Trash2,
  CheckCircle, Mail, Link as LinkIcon,
} from 'lucide-react';

export default function MembersPage() {
  const { family } = useAuth();
  const [members, setMembers] = useState([]);
  const [inviteCode, setInviteCode] = useState('');
  const [showInvite, setShowInvite] = useState(false);
  const [inviteEmail, setInviteEmail] = useState('');
  const [loading, setLoading] = useState(true);
  const [copied, setCopied] = useState(false);

  const fetchMembers = useCallback(async () => {
    try {
      const { data } = await familyApi.getMembers();
      setMembers(data);
    } catch { /* ignore */ }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { fetchMembers(); }, [fetchMembers]);

  const handleGetInviteCode = async () => {
    try {
      const { data } = await familyApi.getInviteCode();
      setInviteCode(data.invite_code);
    } catch { /* ignore */ }
  };

  const handleInvite = async (e) => {
    e.preventDefault();
    try {
      const { data } = await familyApi.invite(inviteEmail);
      setInviteCode(data.invite_code);
      setInviteEmail('');
    } catch { /* ignore */ }
  };

  const handleCopyLink = () => {
    const url = `${window.location.origin}/join/${inviteCode}`;
    navigator.clipboard.writeText(url);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleRemove = async (memberId) => {
    if (!confirm('Remove this member from your family?')) return;
    try {
      await familyApi.removeMember(memberId);
      fetchMembers();
    } catch { /* ignore */ }
  };

  return (
    <div className="p-4 lg:p-6 max-w-3xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Family Members</h1>
          <p className="text-sm text-gray-500 mt-1">
            {members.length} of {family?.max_members || 2} members
          </p>
        </div>
        <button
          onClick={() => { setShowInvite(true); handleGetInviteCode(); }}
          className="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 flex items-center gap-2"
        >
          <UserPlus className="w-4 h-4" /> Invite
        </button>
      </div>

      {/* Member list */}
      <div className="space-y-3">
        {members.map((member) => (
          <div key={member.id} className="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
            <div
              className="w-12 h-12 rounded-full flex items-center justify-center text-white font-bold text-lg"
              style={{ backgroundColor: member.avatar_color }}
            >
              {member.display_name?.charAt(0)?.toUpperCase() || '?'}
            </div>
            <div className="flex-1">
              <p className="font-semibold text-gray-900">{member.display_name}</p>
              <div className="flex items-center gap-2 mt-0.5">
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                  member.role === 'owner'
                    ? 'bg-primary-100 text-primary-700'
                    : 'bg-gray-100 text-gray-600'
                }`}>
                  {member.role === 'owner' ? 'Owner' : 'Member'}
                </span>
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                  member.status === 'active'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-gray-100 text-gray-600'
                }`}>
                  {member.status}
                </span>
              </div>
            </div>
            {member.role !== 'owner' && (
              <button
                onClick={() => handleRemove(member.id)}
                className="text-gray-400 hover:text-red-500 transition-colors p-2"
                title="Remove member"
              >
                <Trash2 className="w-4 h-4" />
              </button>
            )}
          </div>
        ))}
      </div>

      {/* Invite Modal */}
      <Modal open={showInvite} onClose={() => setShowInvite(false)} title="Invite Family Member">
        <div className="space-y-4">
          {/* Email invite */}
          <form onSubmit={handleInvite} className="flex gap-2">
            <input
              type="email"
              value={inviteEmail}
              onChange={(e) => setInviteEmail(e.target.value)}
              placeholder="Email address"
              className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
            />
            <button
              type="submit"
              className="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700"
            >
              <Mail className="w-4 h-4" />
            </button>
          </form>

          <div className="relative">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-gray-200" />
            </div>
            <div className="relative flex justify-center text-xs">
              <span className="px-2 bg-white text-gray-500">or share invite link</span>
            </div>
          </div>

          {/* Invite link */}
          {inviteCode && (
            <div className="bg-gray-50 rounded-lg p-3">
              <p className="text-xs text-gray-500 mb-2">Share this link:</p>
              <div className="flex items-center gap-2">
                <code className="flex-1 text-sm bg-white px-3 py-2 rounded border border-gray-200 truncate">
                  {window.location.origin}/join/{inviteCode}
                </code>
                <button
                  onClick={handleCopyLink}
                  className="bg-primary-600 text-white px-3 py-2 rounded-lg hover:bg-primary-700 flex-shrink-0"
                >
                  {copied ? <CheckCircle className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                </button>
              </div>
            </div>
          )}
        </div>
      </Modal>
    </div>
  );
}
