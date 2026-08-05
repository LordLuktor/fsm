import { useState, useEffect, useMemo } from 'react';
import { workOrders } from '../api/client';
import { parseServerDate, parseScheduleDate } from '../utils/dates';
import { useAuth } from '../context/AuthContext';
import {
  BarChart3,
  TrendingUp,
  TrendingDown,
  Clock,
  CheckCircle,
  AlertTriangle,
  Calendar,
  Users,
  Download,
  RefreshCw,
  XCircle,
  Play,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';

function StatCard({ icon: Icon, label, value, sub, color }) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-5">
      <div className="flex items-center justify-between mb-3">
        <div className={`w-11 h-11 rounded-xl ${color} flex items-center justify-center`}>
          <Icon className="h-5 w-5" />
        </div>
      </div>
      <p className="text-2xl font-bold text-gray-900">{value}</p>
      <p className="text-sm text-gray-500 mt-1">{label}</p>
      {sub && <p className="text-xs text-gray-400 mt-0.5">{sub}</p>}
    </div>
  );
}

function BarChart({ data, maxValue, colorClass = 'bg-primary-500' }) {
  return (
    <div className="space-y-3">
      {data.map((item) => (
        <div key={item.label} className="flex items-center gap-3">
          <span className="text-sm text-gray-600 w-28 shrink-0 truncate">{item.label}</span>
          <div className="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
            <div
              className={`h-full ${colorClass} rounded-full transition-all duration-500`}
              style={{ width: `${maxValue > 0 ? (item.value / maxValue) * 100 : 0}%` }}
            />
          </div>
          <span className="text-sm font-medium text-gray-900 w-10 text-right">{item.value}</span>
        </div>
      ))}
    </div>
  );
}

export default function ReportsPage() {
  const { hasCapability } = useAuth();
  const [allOrders, setAllOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [dateRange, setDateRange] = useState('month');

  useEffect(() => {
    loadData();
  }, [dateRange]);

  const loadData = async () => {
    setLoading(true);
    try {
      // Load all work orders (up to 500 for reporting)
      const { data } = await workOrders.list({ per_page: 500 });
      setAllOrders(data || []);
    } catch (err) {
      console.error('Failed to load reports data:', err);
    } finally {
      setLoading(false);
    }
  };

  // Filter orders by date range
  const filteredOrders = useMemo(() => {
    const now = new Date();
    let cutoff = new Date();

    switch (dateRange) {
      case 'week':
        cutoff.setDate(now.getDate() - 7);
        break;
      case 'month':
        cutoff.setMonth(now.getMonth() - 1);
        break;
      case 'quarter':
        cutoff.setMonth(now.getMonth() - 3);
        break;
      case 'year':
        cutoff.setFullYear(now.getFullYear() - 1);
        break;
      default:
        cutoff.setMonth(now.getMonth() - 1);
    }

    return allOrders.filter((o) => {
      const d = parseScheduleDate(o.scheduled_date) || new Date(o.created_at);
      return d >= cutoff;
    });
  }, [allOrders, dateRange]);

  // Calculate real metrics
  const metrics = useMemo(() => {
    const total = filteredOrders.length;
    const completed = filteredOrders.filter((o) => o.status === 'completed').length;
    const inProgress = filteredOrders.filter((o) => o.status === 'in_progress').length;
    const cancelled = filteredOrders.filter((o) => o.status === 'cancelled').length;
    const pending = filteredOrders.filter((o) =>
      ['new', 'assigned'].includes(o.status)
    ).length;

    // Completion rate
    const completionRate = total > 0 ? Math.round((completed / total) * 100) : 0;

    // Average completion time (for orders with started_at and completed_at)
    const completedWithTimes = filteredOrders.filter(
      (o) => o.status === 'completed' && o.started_at && o.completed_at
    );
    let avgCompletionMinutes = 0;
    if (completedWithTimes.length > 0) {
      const totalMinutes = completedWithTimes.reduce((sum, o) => {
        return sum + (parseServerDate(o.completed_at) - parseServerDate(o.started_at)) / 60000;
      }, 0);
      avgCompletionMinutes = Math.round(totalMinutes / completedWithTimes.length);
    }

    // On-time rate (completed before or at scheduled end time)
    const scheduledCompleted = completedWithTimes.filter(
      (o) => o.scheduled_time_end && o.completed_at
    );
    let onTimeRate = 0;
    if (scheduledCompleted.length > 0) {
      const onTime = scheduledCompleted.filter((o) => {
        const completedTime = parseServerDate(o.completed_at);
        const [h, m] = o.scheduled_time_end.split(':').map(Number);
        const scheduled = parseScheduleDate(o.scheduled_date);
        scheduled.setHours(h, m, 0);
        return completedTime <= scheduled;
      }).length;
      onTimeRate = Math.round((onTime / scheduledCompleted.length) * 100);
    }

    // By status
    const byStatus = {};
    for (const o of filteredOrders) {
      byStatus[o.status] = (byStatus[o.status] || 0) + 1;
    }

    // By type
    const byType = {};
    for (const o of filteredOrders) {
      const type = o.type || 'other';
      byType[type] = (byType[type] || 0) + 1;
    }

    // By priority
    const byPriority = {};
    for (const o of filteredOrders) {
      const pri = o.priority || 'normal';
      byPriority[pri] = (byPriority[pri] || 0) + 1;
    }

    // Top technicians
    const techCounts = {};
    for (const o of filteredOrders) {
      if (o.status === 'completed' && o.assigned_technicians) {
        for (const tech of o.assigned_technicians) {
          const name = tech.name || tech.display_name || tech.title || `Technician ${tech.id || ''}`;
          techCounts[name] = (techCounts[name] || 0) + 1;
        }
      }
    }
    const topTechs = Object.entries(techCounts)
      .map(([name, count]) => ({ label: name, value: count }))
      .sort((a, b) => b.value - a.value)
      .slice(0, 5);

    return {
      total,
      completed,
      inProgress,
      cancelled,
      pending,
      completionRate,
      avgCompletionMinutes,
      onTimeRate,
      byStatus,
      byType,
      byPriority,
      topTechs,
    };
  }, [filteredOrders]);

  const typeData = Object.entries(metrics.byType)
    .map(([label, value]) => ({ label: label.charAt(0).toUpperCase() + label.slice(1), value }))
    .sort((a, b) => b.value - a.value);

  const priorityData = [
    { label: 'Urgent', value: metrics.byPriority.urgent || 0 },
    { label: 'High', value: metrics.byPriority.high || 0 },
    { label: 'Normal', value: metrics.byPriority.normal || 0 },
    { label: 'Low', value: metrics.byPriority.low || 0 },
  ];

  const handleExport = () => {
    const rows = [
      ['Reference', 'Title', 'Status', 'Priority', 'Type', 'Customer', 'Scheduled Date', 'Created'],
      ...filteredOrders.map((o) => [
        o.reference,
        o.title,
        o.status,
        o.priority,
        o.type || '',
        o.customer?.name || '',
        o.scheduled_date || '',
        o.created_at || '',
      ]),
    ];

    const csv = rows.map((r) => r.map((c) => `"${c}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `fsm-report-${dateRange}-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
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
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center">
            <BarChart3 className="h-6 w-6 mr-2" />
            Reports
          </h1>
          <p className="text-gray-500 mt-1">
            {filteredOrders.length} work orders in this period
          </p>
        </div>

        <div className="flex items-center gap-3">
          {/* Date Range */}
          <div className="flex bg-gray-100 rounded-lg p-0.5">
            {['week', 'month', 'quarter', 'year'].map((range) => (
              <button
                key={range}
                onClick={() => setDateRange(range)}
                className={`px-3 py-1.5 text-sm font-medium rounded-md capitalize transition-colors ${
                  dateRange === range
                    ? 'bg-white text-gray-900 shadow-sm'
                    : 'text-gray-600 hover:text-gray-900'
                }`}
              >
                {range}
              </button>
            ))}
          </div>

          <button
            onClick={handleExport}
            className="btn btn-secondary inline-flex items-center gap-1.5 text-sm"
          >
            <Download className="h-4 w-4" />
            Export
          </button>

          <button
            onClick={loadData}
            className="btn btn-secondary inline-flex items-center gap-1.5 text-sm"
          >
            <RefreshCw className="h-4 w-4" />
          </button>
        </div>
      </div>

      {/* Stat Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <StatCard
          icon={BarChart3}
          label="Total Orders"
          value={metrics.total}
          color="bg-blue-100 text-blue-600"
        />
        <StatCard
          icon={CheckCircle}
          label="Completed"
          value={metrics.completed}
          sub={`${metrics.completionRate}% completion rate`}
          color="bg-green-100 text-green-600"
        />
        <StatCard
          icon={Play}
          label="In Progress"
          value={metrics.inProgress}
          color="bg-orange-100 text-orange-600"
        />
        <StatCard
          icon={Clock}
          label="Avg. Completion"
          value={metrics.avgCompletionMinutes > 0 ? `${metrics.avgCompletionMinutes}m` : '—'}
          sub={metrics.onTimeRate > 0 ? `${metrics.onTimeRate}% on-time` : undefined}
          color="bg-purple-100 text-purple-600"
        />
        <StatCard
          icon={XCircle}
          label="Cancelled"
          value={metrics.cancelled}
          color="bg-red-100 text-red-600"
        />
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        {/* By Type */}
        <div className="bg-white rounded-xl border border-gray-200 p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">By Type</h2>
          {typeData.length > 0 ? (
            <BarChart
              data={typeData}
              maxValue={Math.max(...typeData.map((d) => d.value))}
            />
          ) : (
            <p className="text-gray-500 text-sm text-center py-8">No data</p>
          )}
        </div>

        {/* By Priority */}
        <div className="bg-white rounded-xl border border-gray-200 p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">By Priority</h2>
          <BarChart
            data={priorityData}
            maxValue={Math.max(...priorityData.map((d) => d.value), 1)}
            colorClass="bg-orange-500"
          />
        </div>

        {/* Top Technicians */}
        <div className="bg-white rounded-xl border border-gray-200 p-6">
          <h2 className="text-lg font-semibold text-gray-900 mb-4">
            <Users className="h-5 w-5 inline mr-2" />
            Top Technicians
          </h2>
          {metrics.topTechs.length > 0 ? (
            <div className="space-y-3">
              {metrics.topTechs.map((tech, i) => (
                <div key={tech.label} className="flex items-center gap-3">
                  <span className={`w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold ${
                    i === 0 ? 'bg-yellow-100 text-yellow-700' :
                    i === 1 ? 'bg-gray-100 text-gray-700' :
                    i === 2 ? 'bg-orange-100 text-orange-700' :
                    'bg-gray-50 text-gray-500'
                  }`}>
                    {i + 1}
                  </span>
                  <span className="flex-1 text-sm font-medium text-gray-900">{tech.label}</span>
                  <span className="text-sm font-bold text-gray-700">{tech.value} jobs</span>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-sm text-center py-8">No completed orders</p>
          )}
        </div>

        {/* Completion Rate */}
        <div className="bg-white rounded-xl border border-gray-200 p-6 flex flex-col items-center justify-center">
          <h2 className="text-lg font-semibold text-gray-900 mb-6">Completion Rate</h2>
          <div className="relative w-40 h-40">
            <svg className="w-full h-full -rotate-90" viewBox="0 0 100 100">
              <circle
                cx="50" cy="50" r="42" fill="none"
                stroke="#f3f4f6" strokeWidth="10"
              />
              <circle
                cx="50" cy="50" r="42" fill="none"
                stroke="#22c55e" strokeWidth="10"
                strokeLinecap="round"
                strokeDasharray={`${metrics.completionRate * 2.64} 264`}
                className="transition-all duration-1000"
              />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
              <span className="text-3xl font-bold text-gray-900">{metrics.completionRate}%</span>
              <span className="text-xs text-gray-500">
                {metrics.completed}/{metrics.total}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
