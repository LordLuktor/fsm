import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import {
  MapPin, Shield, Bell, Share2, Clock, Users,
  Check, ArrowRight, Zap, Radio,
} from 'lucide-react';

const features = [
  { icon: MapPin, title: 'Real-Time Location', desc: 'See where your family members are on a live map, updated every 15 seconds.' },
  { icon: Shield, title: 'Safe Zones', desc: 'Create geofences and get alerts when family members enter or leave areas.' },
  { icon: Bell, title: 'SOS Button', desc: 'One-tap emergency alert sends your location to all family members instantly.' },
  { icon: Share2, title: 'Share Location', desc: 'Generate temporary share links so others can see your live location.' },
  { icon: Clock, title: 'Location History', desc: 'Review past locations and routes with timeline replay.' },
  { icon: Users, title: 'Family Groups', desc: 'Track up to 10 family members in one group with unique avatars.' },
];

const tiers = [
  {
    name: 'Free',
    price: '$0',
    period: 'forever',
    features: ['2 family members', 'Live map tracking', '24-hour history', 'SOS button', 'Saved places'],
    cta: 'Get Started',
    highlight: false,
    color: 'gray',
  },
  {
    name: 'Premium',
    price: '$9.99',
    period: '/month',
    features: ['8 family members', 'Live map tracking', '14-day history', '10 geofences & alerts', '5 sharing links', 'SOS button', 'Saved places', 'Priority support'],
    cta: 'Start Free Trial',
    highlight: true,
    color: 'primary',
  },
  {
    name: 'Ultimate',
    price: '$24.95',
    period: '/month',
    features: ['Unlimited family members', 'Live map tracking', '30-day history', '20 geofences & alerts', 'Unlimited sharing links', 'Push-to-Talk (PTT)', 'SOS button', 'Saved places', 'Priority support'],
    cta: 'Go Ultimate',
    highlight: false,
    color: 'violet',
  },
];

export default function LandingPage() {
  const { isAuthenticated } = useAuth();

  return (
    <div className="min-h-screen bg-white">
      {/* Nav */}
      <nav className="border-b border-gray-100">
        <div className="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
          <Link to="/" className="flex items-center gap-2">
            <div className="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center">
              <MapPin className="w-5 h-5 text-white" />
            </div>
            <span className="text-xl font-bold text-gray-900">Family Tracker</span>
          </Link>
          <div className="flex items-center gap-3">
            {isAuthenticated ? (
              <Link to="/map" className="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                Open Dashboard
              </Link>
            ) : (
              <>
                <Link to="/login" className="text-gray-600 hover:text-gray-900 text-sm font-medium px-3 py-2">
                  Sign In
                </Link>
                <Link to="/register" className="bg-primary-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
                  Get Started
                </Link>
              </>
            )}
          </div>
        </div>
      </nav>

      {/* Hero */}
      <section className="py-20 px-4">
        <div className="max-w-4xl mx-auto text-center">
          <div className="inline-flex items-center gap-2 bg-primary-50 text-primary-700 px-3 py-1.5 rounded-full text-sm font-medium mb-6">
            <Zap className="w-4 h-4" /> No Google Maps API key needed
          </div>
          <h1 className="text-5xl sm:text-6xl font-extrabold text-gray-900 leading-tight mb-6">
            Know where your<br />
            <span className="text-primary-600">family</span> is, always.
          </h1>
          <p className="text-xl text-gray-600 max-w-2xl mx-auto mb-8">
            Real-time GPS tracking for your family. Set safe zones, get instant alerts,
            and share locations with a tap. Free to start.
          </p>
          <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
            <Link
              to="/register"
              className="bg-primary-600 text-white px-8 py-3.5 rounded-xl text-lg font-semibold hover:bg-primary-700 transition-colors flex items-center gap-2"
            >
              Start for Free <ArrowRight className="w-5 h-5" />
            </Link>
            <a href="#pricing" className="text-gray-600 hover:text-gray-900 px-6 py-3.5 text-lg font-medium">
              View Pricing
            </a>
          </div>
        </div>
      </section>

      {/* Features */}
      <section className="py-20 bg-gray-50 px-4">
        <div className="max-w-6xl mx-auto">
          <h2 className="text-3xl font-bold text-center text-gray-900 mb-12">
            Everything you need to keep your family safe
          </h2>
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {features.map(({ icon: Icon, title, desc }) => (
              <div key={title} className="bg-white rounded-xl p-6 border border-gray-200">
                <div className="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center mb-4">
                  <Icon className="w-5 h-5 text-primary-600" />
                </div>
                <h3 className="font-semibold text-gray-900 mb-2">{title}</h3>
                <p className="text-sm text-gray-600">{desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing */}
      <section id="pricing" className="py-20 px-4">
        <div className="max-w-4xl mx-auto">
          <h2 className="text-3xl font-bold text-center text-gray-900 mb-4">
            Simple, transparent pricing
          </h2>
          <p className="text-center text-gray-600 mb-12">Start free, upgrade when you need more.</p>
          <div className="grid md:grid-cols-3 gap-6 max-w-5xl mx-auto">
            {tiers.map((tier) => (
              <div
                key={tier.name}
                className={`rounded-xl p-6 border-2 ${
                  tier.highlight
                    ? 'border-primary-600 bg-primary-50/30 shadow-lg'
                    : tier.color === 'violet'
                      ? 'border-violet-300 bg-violet-50/30'
                      : 'border-gray-200 bg-white'
                }`}
              >
                {tier.highlight && (
                  <span className="inline-block bg-primary-600 text-white text-xs font-bold px-2.5 py-1 rounded-full mb-3">
                    Most Popular
                  </span>
                )}
                {tier.color === 'violet' && !tier.highlight && (
                  <span className="inline-flex items-center gap-1 bg-violet-600 text-white text-xs font-bold px-2.5 py-1 rounded-full mb-3">
                    <Radio className="w-3 h-3" /> PTT Included
                  </span>
                )}
                <h3 className="text-xl font-bold text-gray-900">{tier.name}</h3>
                <div className="mt-2 mb-6">
                  <span className="text-4xl font-extrabold text-gray-900">{tier.price}</span>
                  <span className="text-gray-500 ml-1">{tier.period}</span>
                </div>
                <ul className="space-y-3 mb-8">
                  {tier.features.map((f) => (
                    <li key={f} className="flex items-start gap-2 text-sm text-gray-700">
                      <Check className={`w-4 h-4 mt-0.5 flex-shrink-0 ${
                        tier.color === 'violet' ? 'text-violet-500' : 'text-green-500'
                      }`} />
                      {f}
                    </li>
                  ))}
                </ul>
                <Link
                  to="/register"
                  className={`block text-center py-2.5 rounded-lg font-semibold text-sm transition-colors ${
                    tier.highlight
                      ? 'bg-primary-600 text-white hover:bg-primary-700'
                      : tier.color === 'violet'
                        ? 'bg-violet-600 text-white hover:bg-violet-700'
                        : 'bg-gray-100 text-gray-900 hover:bg-gray-200'
                  }`}
                >
                  {tier.cta}
                </Link>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Footer */}
      <footer className="border-t border-gray-200 py-8 px-4">
        <div className="max-w-6xl mx-auto flex items-center justify-between text-sm text-gray-500">
          <div className="flex items-center gap-2">
            <MapPin className="w-4 h-4" />
            <span>Family Tracker by Steinmetz Ventures</span>
          </div>
          <span>&copy; {new Date().getFullYear()}</span>
        </div>
      </footer>
    </div>
  );
}
