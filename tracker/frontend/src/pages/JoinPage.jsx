import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { family as familyApi } from '../api/client';
import { MapPin, Users, CheckCircle } from 'lucide-react';

export default function JoinPage() {
  const { code } = useParams();
  const { isAuthenticated, refreshUser } = useAuth();
  const navigate = useNavigate();
  const [status, setStatus] = useState('idle'); // idle, joining, success, error
  const [error, setError] = useState('');
  const [familyInfo, setFamilyInfo] = useState(null);

  const handleJoin = async () => {
    if (!isAuthenticated) {
      // Store code and redirect to register
      localStorage.setItem('tracker_join_code', code);
      navigate('/register');
      return;
    }

    setStatus('joining');
    try {
      const { data } = await familyApi.join(code);
      setFamilyInfo(data.family);
      setStatus('success');
      await refreshUser();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to join family.');
      setStatus('error');
    }
  };

  // Auto-join after registration
  useEffect(() => {
    const savedCode = localStorage.getItem('tracker_join_code');
    if (isAuthenticated && savedCode) {
      localStorage.removeItem('tracker_join_code');
      handleJoin();
    }
  }, [isAuthenticated]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
      <div className="w-full max-w-sm text-center">
        <div className="w-12 h-12 bg-primary-100 rounded-xl flex items-center justify-center mx-auto mb-4">
          <Users className="w-6 h-6 text-primary-600" />
        </div>

        {status === 'success' ? (
          <>
            <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
            <h1 className="text-2xl font-bold text-gray-900 mb-2">You're in!</h1>
            <p className="text-gray-600 mb-6">
              You've joined {familyInfo?.name || 'the family'}. Head to the map to see everyone.
            </p>
            <Link
              to="/map"
              className="inline-block bg-primary-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-primary-700 transition-colors"
            >
              Open Map
            </Link>
          </>
        ) : status === 'error' ? (
          <>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">Couldn't Join</h1>
            <p className="text-red-600 mb-6">{error}</p>
            <Link to="/" className="text-primary-600 hover:text-primary-700 font-medium">
              Go Home
            </Link>
          </>
        ) : (
          <>
            <h1 className="text-2xl font-bold text-gray-900 mb-2">Join a Family</h1>
            <p className="text-gray-600 mb-6">
              You've been invited to join a family on Family Tracker.
            </p>
            <button
              onClick={handleJoin}
              disabled={status === 'joining'}
              className="w-full bg-primary-600 text-white py-2.5 rounded-lg font-medium hover:bg-primary-700 disabled:opacity-50 transition-colors"
            >
              {status === 'joining' ? 'Joining...' : isAuthenticated ? 'Join Family' : 'Create Account & Join'}
            </button>
            {!isAuthenticated && (
              <p className="text-sm text-gray-500 mt-3">
                Already have an account?{' '}
                <Link to="/login" className="text-primary-600 font-medium">Sign in first</Link>
              </p>
            )}
          </>
        )}
      </div>
    </div>
  );
}
