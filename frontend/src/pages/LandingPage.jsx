import { Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import {
  ClipboardList, Truck, MapPin, Package, Radio, BarChart3,
  Check, ArrowRight, Zap, Shield, Clock, ChevronRight,
  Users, Wrench, Building2, Star,
} from 'lucide-react';

const features = [
  {
    icon: ClipboardList,
    title: 'Work Order Management',
    desc: 'Create, assign, and track work orders through their entire lifecycle. Real-time status updates keep everyone in sync.',
  },
  {
    icon: Truck,
    title: 'Smart Dispatch',
    desc: 'Drag-and-drop scheduling board with technician availability, skill matching, and route optimization at a glance.',
  },
  {
    icon: MapPin,
    title: 'Live GPS Tracking',
    desc: 'See your entire fleet on a real-time map. Know exactly where every technician is and when they\'ll arrive.',
  },
  {
    icon: Package,
    title: 'Inventory Control',
    desc: 'Track parts at the warehouse and on every truck. Consume, transfer, and restock without leaving the app.',
  },
  {
    icon: Radio,
    title: 'Push-to-Talk',
    desc: 'Built-in team radio — no third-party app needed. Instant voice communication between dispatch and field crews.',
  },
  {
    icon: BarChart3,
    title: 'Reporting & Analytics',
    desc: 'Completion rates, response times, revenue per tech. The insights you need to grow, generated automatically.',
  },
];

const competitors = [
  { name: 'ServiceTitan', price: '$250+', users: 'Per user/mo', lock: '12-mo contract', ptt: false },
  { name: 'Housecall Pro', price: '$79', users: 'Per user/mo', lock: 'Annual billing', ptt: false },
  { name: 'Jobber', price: '$69', users: 'Per user/mo', lock: 'Annual billing', ptt: false },
];

const steps = [
  { num: '01', title: 'Set Up Your Team', desc: 'Add your technicians, dispatchers, and admin staff. Configure roles and permissions in minutes.' },
  { num: '02', title: 'Create Work Orders', desc: 'Enter jobs, assign them to techs, and schedule with our drag-and-drop dispatch board.' },
  { num: '03', title: 'Track Everything', desc: 'GPS, inventory, job status, time tracking — all updating in real-time across every device.' },
];

const stats = [
  { value: '40%', label: 'Faster Dispatch', sub: 'vs. phone-based scheduling' },
  { value: '99.9%', label: 'Uptime', sub: 'self-hosted reliability' },
  { value: '$0', label: 'Per-User Fees', sub: 'unlimited users included' },
  { value: '< 5min', label: 'Setup Time', sub: 'try the live demo instantly' },
];

export default function LandingPage() {
  const { isAuthenticated } = useAuth();

  return (
    <div className="min-h-screen bg-white">
      {/* ─── Nav ─────────────────────────────────────── */}
      <nav className="fixed top-0 inset-x-0 z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
          <Link to="/welcome" className="flex items-center gap-2.5">
            <div className="w-9 h-9 bg-primary-600 rounded-xl flex items-center justify-center shadow-md shadow-primary-600/20">
              <Wrench className="w-5 h-5 text-white" />
            </div>
            <span className="text-xl font-bold text-gray-900 tracking-tight">FSM</span>
          </Link>

          <div className="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600">
            <a href="#features" className="hover:text-gray-900 transition-colors">Features</a>
            <a href="#compare" className="hover:text-gray-900 transition-colors">Compare</a>
            <a href="#how" className="hover:text-gray-900 transition-colors">How It Works</a>
            <a href="#pricing" className="hover:text-gray-900 transition-colors">Pricing</a>
          </div>

          <div className="flex items-center gap-3">
            {isAuthenticated ? (
              <Link to="/dashboard" className="btn btn-primary text-sm flex items-center gap-1.5">
                Open Dashboard <ArrowRight className="w-4 h-4" />
              </Link>
            ) : (
              <>
                <Link to="/login" className="text-gray-600 hover:text-gray-900 text-sm font-medium px-3 py-2">
                  Try Demo
                </Link>
                <Link to="/login" className="text-gray-600 hover:text-gray-900 text-sm font-medium px-3 py-2">
                  Log In
                </Link>
                <Link to="/register" className="btn btn-primary text-sm flex items-center gap-1.5">
                  Sign Up <ArrowRight className="w-4 h-4" />
                </Link>
              </>
            )}
          </div>
        </div>
      </nav>

      {/* ─── Hero ────────────────────────────────────── */}
      <section className="pt-32 pb-20 px-4 sm:px-6 overflow-hidden relative">
        {/* Background gradient */}
        <div className="absolute inset-0 bg-gradient-to-br from-primary-50 via-white to-blue-50 -z-10" />
        <div className="absolute top-20 right-0 w-96 h-96 bg-primary-200/30 rounded-full blur-3xl -z-10" />
        <div className="absolute bottom-0 left-0 w-80 h-80 bg-blue-100/40 rounded-full blur-3xl -z-10" />

        <div className="max-w-5xl mx-auto text-center">
          <div className="inline-flex items-center gap-2 bg-primary-50 text-primary-700 px-4 py-1.5 rounded-full text-sm font-semibold mb-8 border border-primary-100">
            <Zap className="w-4 h-4" />
            Built for HVAC, Plumbing & Electrical teams
          </div>

          <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-[1.1] mb-6 tracking-tight">
            The field service platform<br className="hidden sm:block" />
            that <span className="text-primary-600">doesn't charge per user</span>
          </h1>

          <p className="text-lg sm:text-xl text-gray-600 max-w-2xl mx-auto mb-10 leading-relaxed">
            Work orders. Dispatch. GPS tracking. Inventory. Push-to-talk. Reports.
            One platform, unlimited users, zero per-seat fees.
          </p>

          <div className="flex flex-col sm:flex-row items-center justify-center gap-4 mb-16">
            <Link
              to="/login"
              className="bg-primary-600 text-white px-8 py-4 rounded-xl text-lg font-semibold hover:bg-primary-700 transition-all shadow-lg shadow-primary-600/25 hover:shadow-xl hover:shadow-primary-600/30 flex items-center gap-2"
            >
              Try the Live Demo <ArrowRight className="w-5 h-5" />
            </Link>
            <a
              href="#features"
              className="text-gray-600 hover:text-gray-900 px-6 py-4 text-lg font-medium flex items-center gap-1 transition-colors"
            >
              See Features <ChevronRight className="w-5 h-5" />
            </a>
          </div>

          {/* Mock UI preview */}
          <div className="relative max-w-4xl mx-auto">
            <div className="bg-gray-900 rounded-2xl shadow-2xl p-2 sm:p-3">
              <div className="flex items-center gap-1.5 px-3 pb-2">
                <div className="w-3 h-3 rounded-full bg-red-500/70" />
                <div className="w-3 h-3 rounded-full bg-yellow-500/70" />
                <div className="w-3 h-3 rounded-full bg-green-500/70" />
                <span className="ml-3 text-xs text-gray-500 font-mono">fsm.steinmetz.ltd</span>
              </div>
              <div className="bg-gray-50 rounded-xl overflow-hidden">
                {/* Simulated dashboard */}
                <div className="flex">
                  {/* Sidebar mock */}
                  <div className="hidden sm:block w-48 bg-primary-800 p-4 space-y-3 min-h-[280px]">
                    <div className="flex items-center gap-2 text-white text-sm font-bold mb-4">
                      <Wrench className="w-4 h-4" /> FSM
                    </div>
                    {['Dashboard', 'Work Orders', 'Dispatch', 'GPS Map', 'Inventory', 'Reports'].map((item, i) => (
                      <div key={item} className={`text-xs px-3 py-2 rounded-md ${i === 0 ? 'bg-primary-700 text-white' : 'text-primary-200'}`}>
                        {item}
                      </div>
                    ))}
                  </div>
                  {/* Content mock */}
                  <div className="flex-1 p-4 sm:p-6 space-y-4">
                    <div className="flex items-center justify-between">
                      <div className="h-5 w-32 bg-gray-200 rounded" />
                      <div className="h-8 w-24 bg-primary-100 rounded-lg" />
                    </div>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                      {[
                        { label: 'Active Jobs', val: '12', color: 'blue' },
                        { label: 'Techs Online', val: '8', color: 'green' },
                        { label: 'Completed', val: '47', color: 'purple' },
                        { label: 'Parts Used', val: '156', color: 'orange' },
                      ].map((s) => (
                        <div key={s.label} className="bg-white border border-gray-200 rounded-lg p-3">
                          <div className="text-[10px] text-gray-500 uppercase tracking-wider">{s.label}</div>
                          <div className="text-xl font-bold text-gray-900 mt-1">{s.val}</div>
                        </div>
                      ))}
                    </div>
                    <div className="grid sm:grid-cols-2 gap-3">
                      <div className="bg-white border border-gray-200 rounded-lg p-3 h-24" />
                      <div className="bg-white border border-gray-200 rounded-lg p-3 h-24" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
            {/* Glow effect */}
            <div className="absolute -inset-4 bg-gradient-to-r from-primary-400/10 via-primary-600/10 to-blue-400/10 rounded-3xl blur-2xl -z-10" />
          </div>
        </div>
      </section>

      {/* ─── Stats ───────────────────────────────────── */}
      <section className="py-16 border-y border-gray-100 bg-gray-50/50">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 grid grid-cols-2 lg:grid-cols-4 gap-8">
          {stats.map((s) => (
            <div key={s.label} className="text-center">
              <div className="text-3xl sm:text-4xl font-extrabold text-primary-600 mb-1">{s.value}</div>
              <div className="text-sm font-semibold text-gray-900">{s.label}</div>
              <div className="text-xs text-gray-500 mt-0.5">{s.sub}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ─── Features ────────────────────────────────── */}
      <section id="features" className="py-20 px-4 sm:px-6">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-14">
            <span className="text-sm font-semibold text-primary-600 uppercase tracking-wider">Features</span>
            <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mt-2 mb-4">
              Everything your team needs in one place
            </h2>
            <p className="text-gray-600 max-w-xl mx-auto">
              Stop juggling five different apps. FSM handles work orders, dispatch, GPS,
              inventory, communication, and reporting — all under one roof.
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {features.map(({ icon: Icon, title, desc }) => (
              <div
                key={title}
                className="group bg-white rounded-2xl p-7 border border-gray-200 hover:border-primary-200 hover:shadow-lg hover:shadow-primary-100/50 transition-all duration-300"
              >
                <div className="w-12 h-12 bg-primary-50 group-hover:bg-primary-100 rounded-xl flex items-center justify-center mb-5 transition-colors">
                  <Icon className="w-6 h-6 text-primary-600" />
                </div>
                <h3 className="text-lg font-semibold text-gray-900 mb-2">{title}</h3>
                <p className="text-sm text-gray-600 leading-relaxed">{desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Why FSM / Comparison ────────────────────── */}
      <section id="compare" className="py-20 px-4 sm:px-6 bg-gray-50">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-14">
            <span className="text-sm font-semibold text-primary-600 uppercase tracking-wider">Why FSM</span>
            <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mt-2 mb-4">
              Stop paying per seat. Start scaling.
            </h2>
            <p className="text-gray-600 max-w-xl mx-auto">
              Competitors charge $69–$250+ per user per month with long-term contracts.
              FSM gives you everything for a flat rate — add as many users as you want.
            </p>
          </div>

          {/* Comparison table */}
          <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-gray-200 bg-gray-50">
                    <th className="text-left p-4 font-semibold text-gray-900">Feature</th>
                    <th className="p-4 text-center font-bold text-primary-600 bg-primary-50">
                      <div className="flex items-center justify-center gap-1.5">
                        <Wrench className="w-4 h-4" /> FSM
                      </div>
                    </th>
                    {competitors.map((c) => (
                      <th key={c.name} className="p-4 text-center font-medium text-gray-500">{c.name}</th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {[
                    { feature: 'Unlimited Users', fsm: true, others: [false, false, false] },
                    { feature: 'Per-User Pricing', fsm: '$0', others: ['$250+/mo', '$79/mo', '$69/mo'] },
                    { feature: 'Work Orders', fsm: true, others: [true, true, true] },
                    { feature: 'Smart Dispatch', fsm: true, others: [true, true, true] },
                    { feature: 'GPS Tracking', fsm: true, others: [true, true, true] },
                    { feature: 'Inventory Management', fsm: true, others: [true, false, false] },
                    { feature: 'Push-to-Talk Radio', fsm: true, others: [false, false, false] },
                    { feature: 'No Long-Term Contract', fsm: true, others: [false, false, false] },
                    { feature: 'Self-Hosted Option', fsm: true, others: [false, false, false] },
                    { feature: 'White-Label Ready', fsm: true, others: [false, false, false] },
                  ].map((row) => (
                    <tr key={row.feature} className="hover:bg-gray-50/50">
                      <td className="p-4 font-medium text-gray-900">{row.feature}</td>
                      <td className="p-4 text-center bg-primary-50/30">
                        {row.fsm === true ? (
                          <Check className="w-5 h-5 text-green-500 mx-auto" />
                        ) : (
                          <span className="font-semibold text-green-600">{row.fsm}</span>
                        )}
                      </td>
                      {row.others.map((val, i) => (
                        <td key={i} className="p-4 text-center">
                          {val === true ? (
                            <Check className="w-5 h-5 text-gray-400 mx-auto" />
                          ) : val === false ? (
                            <span className="text-gray-300">—</span>
                          ) : (
                            <span className="text-gray-500">{val}</span>
                          )}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          <div className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4">
            <div className="flex items-center gap-2 text-sm text-gray-500">
              <Shield className="w-4 h-4 text-green-500" />
              Your data, your servers, your rules
            </div>
            <div className="flex items-center gap-2 text-sm text-gray-500">
              <Clock className="w-4 h-4 text-primary-500" />
              Cancel anytime — no lock-in
            </div>
          </div>
        </div>
      </section>

      {/* ─── How It Works ────────────────────────────── */}
      <section id="how" className="py-20 px-4 sm:px-6">
        <div className="max-w-5xl mx-auto">
          <div className="text-center mb-14">
            <span className="text-sm font-semibold text-primary-600 uppercase tracking-wider">How It Works</span>
            <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mt-2 mb-4">
              Up and running in minutes
            </h2>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            {steps.map((step, i) => (
              <div key={step.num} className="relative text-center">
                <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary-600 text-white text-lg font-bold mb-5 shadow-lg shadow-primary-600/20">
                  {step.num}
                </div>
                {i < steps.length - 1 && (
                  <div className="hidden md:block absolute top-7 left-[60%] w-[80%] border-t-2 border-dashed border-primary-200" />
                )}
                <h3 className="text-lg font-semibold text-gray-900 mb-2">{step.title}</h3>
                <p className="text-sm text-gray-600 leading-relaxed">{step.desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Industries ──────────────────────────────── */}
      <section className="py-16 px-4 sm:px-6 bg-gray-50 border-y border-gray-100">
        <div className="max-w-6xl mx-auto text-center">
          <h3 className="text-lg font-semibold text-gray-900 mb-8">Built for service businesses of every size</h3>
          <div className="flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-gray-400">
            {[
              { icon: Wrench, label: 'Plumbing' },
              { icon: Zap, label: 'Electrical' },
              { icon: Building2, label: 'HVAC' },
              { icon: Shield, label: 'Security' },
              { icon: Users, label: 'Cleaning' },
              { icon: Truck, label: 'Delivery' },
            ].map(({ icon: Icon, label }) => (
              <div key={label} className="flex items-center gap-2 text-sm font-medium text-gray-500">
                <Icon className="w-5 h-5" /> {label}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Pricing ─────────────────────────────────── */}
      <section id="pricing" className="py-20 px-4 sm:px-6">
        <div className="max-w-4xl mx-auto">
          <div className="text-center mb-14">
            <span className="text-sm font-semibold text-primary-600 uppercase tracking-wider">Pricing</span>
            <h2 className="text-3xl sm:text-4xl font-bold text-gray-900 mt-2 mb-4">
              Simple pricing. No per-user fees. Ever.
            </h2>
            <p className="text-gray-600">Add your whole team without watching costs climb.</p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-5">
            {[
              {
                name: 'VenturOS Beta',
                price: 'Free',
                period: '',
                desc: 'Try the full platform — no credit card required',
                features: [
                  'Unlimited users',
                  'Work order management',
                  'Dispatch board',
                  'GPS fleet tracking',
                  'Inventory management',
                  'Push-to-Talk radio',
                  'Customer portal',
                  'Reporting & analytics',
                ],
                highlight: false,
                beta: true,
              },
              {
                name: 'Starter',
                price: '$49',
                period: '/month',
                desc: 'For small teams getting started',
                features: [
                  'Everything in Beta',
                  'Priority email support',
                  'Custom branding',
                  'Email notifications',
                ],
                highlight: false,
                comingSoon: true,
              },
              {
                name: 'Professional',
                price: '$149',
                period: '/month',
                desc: 'For growing service businesses',
                features: [
                  'Everything in Starter',
                  'Custom domain portal',
                  'Barcode scanning',
                  'Invoice generation',
                  'Advanced reporting',
                  'Priority support',
                ],
                highlight: true,
                comingSoon: true,
              },
              {
                name: 'Enterprise',
                price: 'Custom',
                period: '',
                desc: 'For multi-location operations',
                features: [
                  'Everything in Professional',
                  'Self-hosted deployment',
                  'White-label branding',
                  'Custom integrations',
                  'Dedicated account manager',
                  'SLA guarantee',
                ],
                highlight: false,
                comingSoon: true,
              },
            ].map((tier) => (
              <div
                key={tier.name}
                className={`rounded-2xl p-6 border-2 transition-all relative ${
                  tier.comingSoon
                    ? 'border-gray-200 opacity-50 grayscale'
                    : tier.highlight
                    ? 'border-primary-600 shadow-xl shadow-primary-100/50 scale-[1.02]'
                    : tier.beta
                    ? 'border-green-300 bg-green-50/30'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                {tier.comingSoon && (
                  <span className="inline-flex items-center gap-1 bg-gray-500 text-white text-xs font-bold px-3 py-1 rounded-full mb-3">
                    Coming Soon
                  </span>
                )}
                {tier.highlight && !tier.comingSoon && (
                  <span className="inline-flex items-center gap-1 bg-primary-600 text-white text-xs font-bold px-3 py-1 rounded-full mb-3">
                    <Star className="w-3 h-3" /> Most Popular
                  </span>
                )}
                {tier.beta && (
                  <span className="inline-flex items-center gap-1 bg-green-600 text-white text-xs font-bold px-3 py-1 rounded-full mb-3">
                    <Zap className="w-3 h-3" /> Free Beta
                  </span>
                )}
                <h3 className="text-lg font-bold text-gray-900">{tier.name}</h3>
                <p className="text-xs text-gray-500 mt-1 mb-3">{tier.desc}</p>
                <div className="mb-5">
                  <span className="text-3xl font-extrabold text-gray-900">{tier.price}</span>
                  {tier.period && <span className="text-gray-500 ml-1 text-sm">{tier.period}</span>}
                </div>
                <ul className="space-y-2.5 mb-6">
                  {tier.features.map((f) => (
                    <li key={f} className="flex items-start gap-2 text-sm text-gray-700">
                      <Check className="w-4 h-4 mt-0.5 flex-shrink-0 text-green-500" />
                      {f}
                    </li>
                  ))}
                </ul>
                {tier.comingSoon ? (
                  <span className="block text-center py-2.5 rounded-xl font-semibold text-sm bg-gray-100 text-gray-400 cursor-not-allowed">
                    Coming Soon
                  </span>
                ) : (
                  <Link
                    to={tier.beta ? '/register' : '/login'}
                    className={`block text-center py-2.5 rounded-xl font-semibold text-sm transition-all ${
                      tier.highlight
                        ? 'bg-primary-600 text-white hover:bg-primary-700 shadow-md shadow-primary-600/20'
                        : tier.beta
                        ? 'bg-green-600 text-white hover:bg-green-700 shadow-md shadow-green-600/20'
                        : 'bg-gray-100 text-gray-900 hover:bg-gray-200'
                    }`}
                  >
                    {tier.beta ? 'Start Free' : tier.price === 'Custom' ? 'Contact Sales' : 'Start Free Trial'}
                  </Link>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── Final CTA ───────────────────────────────── */}
      <section className="py-20 px-4 sm:px-6">
        <div className="max-w-3xl mx-auto text-center bg-gradient-to-br from-primary-600 to-primary-700 rounded-3xl p-10 sm:p-14 shadow-2xl shadow-primary-600/20 relative overflow-hidden">
          <div className="absolute top-0 right-0 w-64 h-64 bg-primary-500/30 rounded-full blur-3xl" />
          <div className="absolute bottom-0 left-0 w-48 h-48 bg-blue-500/20 rounded-full blur-3xl" />
          <div className="relative">
            <h2 className="text-3xl sm:text-4xl font-bold text-white mb-4">
              Ready to streamline your operations?
            </h2>
            <p className="text-primary-100 text-lg mb-8 max-w-lg mx-auto">
              Try the live demo with pre-loaded data for plumbing, electrical, or HVAC.
              No signup required.
            </p>
            <Link
              to="/login"
              className="inline-flex items-center gap-2 bg-white text-primary-700 px-8 py-4 rounded-xl text-lg font-semibold hover:bg-primary-50 transition-colors shadow-lg"
            >
              Launch Demo Now <ArrowRight className="w-5 h-5" />
            </Link>
          </div>
        </div>
      </section>

      {/* ─── Footer ──────────────────────────────────── */}
      <footer className="border-t border-gray-200 py-10 px-4 sm:px-6">
        <div className="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
          <div className="flex items-center gap-2">
            <div className="w-7 h-7 bg-primary-600 rounded-lg flex items-center justify-center">
              <Wrench className="w-4 h-4 text-white" />
            </div>
            <span className="font-medium text-gray-700">FSM</span>
            <span className="text-gray-400">by Steinmetz Ventures</span>
          </div>
          <div className="flex items-center gap-6">
            <a href="#features" className="hover:text-gray-900 transition-colors">Features</a>
            <a href="#pricing" className="hover:text-gray-900 transition-colors">Pricing</a>
            <Link to="/login" className="hover:text-gray-900 transition-colors">Sign In</Link>
          </div>
          <span>&copy; {new Date().getFullYear()} Steinmetz Ventures LLC</span>
        </div>
        <div className="max-w-6xl mx-auto mt-8 pt-8 border-t border-gray-200 text-center">
          <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
            More from the Steinmetz Network
          </h4>
          <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-gray-500">
            <a href="https://steinmetz.ltd" className="hover:text-primary-600 transition-colors">Steinmetz Ventures</a>
            <a href="https://rackos.steinmetz.ltd" className="hover:text-primary-600 transition-colors">RackOS</a>
            <a href="https://chores.steinmetz.ltd" className="hover:text-primary-600 transition-colors">ChoreQuest</a>
            <a href="https://aac.steinmetz.ltd" className="hover:text-primary-600 transition-colors">OpenVoice</a>
            <a href="https://fundraiser.steinmetz.ltd" className="hover:text-primary-600 transition-colors">Fundraiser Pro</a>
            <a href="https://scottsteinmetz.biz" className="hover:text-primary-600 transition-colors">Scott Steinmetz — Portfolio</a>
            <a href="https://apexweb.us" className="hover:text-primary-600 transition-colors">Apex Webcrafters</a>
            <a href="https://steinmetzprinting.com" className="hover:text-primary-600 transition-colors">Steinmetz Printing</a>
          </div>
        </div>
      </footer>
    </div>
  );
}
