import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { dispatch } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  Calendar,
  ChevronLeft,
  ChevronRight,
  Clock,
  User,
  MapPin,
  UserPlus,
  CalendarClock,
  MoreVertical,
  RefreshCw,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';
import PriorityBadge from '../components/common/PriorityBadge';
import AssignTechnicianModal from '../components/dispatch/AssignTechnicianModal';
import RescheduleModal from '../components/dispatch/RescheduleModal';

function DispatchPage() {
  const { hasCapability } = useAuth();
  const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);
  const [board, setBoard] = useState({ date: '', technicians: [] });
  const [unscheduled, setUnscheduled] = useState([]);
  const [loading, setLoading] = useState(true);

  // Modal states
  const [assignModalOpen, setAssignModalOpen] = useState(false);
  const [rescheduleModalOpen, setRescheduleModalOpen] = useState(false);
  const [selectedWorkOrder, setSelectedWorkOrder] = useState(null);
  const [actionMenuOpen, setActionMenuOpen] = useState(null);

  useEffect(() => {
    loadDispatchBoard();
  }, [selectedDate]);

  const loadDispatchBoard = async () => {
    setLoading(true);
    try {
      const [boardRes, unscheduledRes] = await Promise.all([
        dispatch.getBoard(selectedDate),
        dispatch.getUnscheduled(),
      ]);
      setBoard(boardRes.data);
      setUnscheduled(unscheduledRes.data);
    } catch (err) {
      console.error('Failed to load dispatch board:', err);
    } finally {
      setLoading(false);
    }
  };

  const changeDate = (days) => {
    const date = new Date(selectedDate);
    date.setDate(date.getDate() + days);
    setSelectedDate(date.toISOString().split('T')[0]);
  };

  const handleAssign = (workOrder) => {
    setSelectedWorkOrder(workOrder);
    setAssignModalOpen(true);
    setActionMenuOpen(null);
  };

  const handleReschedule = (workOrder) => {
    setSelectedWorkOrder(workOrder);
    setRescheduleModalOpen(true);
    setActionMenuOpen(null);
  };

  const handleModalSuccess = () => {
    loadDispatchBoard();
  };

  const statusColors = {
    new: 'border-l-blue-500 bg-blue-50',
    assigned: 'border-l-purple-500 bg-purple-50',
    en_route: 'border-l-yellow-500 bg-yellow-50',
    in_progress: 'border-l-orange-500 bg-orange-50',
    on_hold: 'border-l-gray-500 bg-gray-50',
  };

  const techStatusColors = {
    available: 'bg-green-100 text-green-800',
    busy: 'bg-orange-100 text-orange-800',
    offline: 'bg-gray-100 text-gray-800',
  };

  if (!hasCapability('fsm_view_dispatch')) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">You don't have permission to view the dispatch board.</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
          <Calendar className="h-6 w-6 mr-2" />
          Dispatch Board
        </h1>

        <div className="flex items-center gap-2">
          {/* Refresh */}
          <button
            onClick={loadDispatchBoard}
            disabled={loading}
            className="p-2 hover:bg-gray-100 rounded-md transition-colors"
            title="Refresh"
          >
            <RefreshCw className={`h-5 w-5 text-gray-600 ${loading ? 'animate-spin' : ''}`} />
          </button>

          {/* Date Navigation */}
          <button
            onClick={() => changeDate(-1)}
            className="p-2 hover:bg-gray-100 rounded-md"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <input
            type="date"
            value={selectedDate}
            onChange={(e) => setSelectedDate(e.target.value)}
            className="px-3 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
          />
          <button
            onClick={() => changeDate(1)}
            className="p-2 hover:bg-gray-100 rounded-md"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
          <button
            onClick={() => setSelectedDate(new Date().toISOString().split('T')[0])}
            className="btn btn-secondary"
          >
            Today
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-64">
          <LoadingSpinner size="medium" />
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Unscheduled Work Orders */}
          <div className="bg-white rounded-lg shadow">
            <div className="px-4 py-3 border-b border-gray-200 bg-gray-50">
              <h2 className="font-medium text-gray-900">
                Unassigned ({unscheduled.length})
              </h2>
            </div>
            <div className="p-2 max-h-[600px] overflow-y-auto">
              {unscheduled.length === 0 ? (
                <p className="text-sm text-gray-500 text-center py-4">
                  No unassigned work orders
                </p>
              ) : (
                <div className="space-y-2">
                  {unscheduled.map((wo) => (
                    <div
                      key={wo.id}
                      className="relative p-3 bg-gray-50 rounded-md hover:bg-gray-100 transition-colors group"
                    >
                      <Link to={`/work-orders/${wo.id}`} className="block">
                        <div className="text-xs font-mono text-gray-500">{wo.reference}</div>
                        <div className="text-sm font-medium text-gray-900 truncate">
                          {wo.title}
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                          {wo.customer_name} - {wo.estimated_duration}min
                        </div>
                      </Link>

                      {/* Action buttons */}
                      <div className="absolute right-2 top-2 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                        <button
                          onClick={(e) => {
                            e.preventDefault();
                            handleAssign(wo);
                          }}
                          className="p-1.5 bg-primary-600 text-white rounded hover:bg-primary-700 transition-colors"
                          title="Assign Technician"
                        >
                          <UserPlus className="h-3.5 w-3.5" />
                        </button>
                        <button
                          onClick={(e) => {
                            e.preventDefault();
                            handleReschedule(wo);
                          }}
                          className="p-1.5 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors"
                          title="Schedule"
                        >
                          <CalendarClock className="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Technician Columns */}
          <div className="lg:col-span-3 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {board.technicians.length === 0 ? (
              <div className="col-span-full text-center py-12 text-gray-500">
                No active technicians
              </div>
            ) : (
              board.technicians.map((tech) => (
                <div key={tech.technician.id} className="bg-white rounded-lg shadow">
                  {/* Technician Header */}
                  <div className="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div className="flex items-center">
                      <div className="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center">
                        <User className="h-4 w-4 text-primary-600" />
                      </div>
                      <div className="ml-3">
                        <div className="font-medium text-gray-900">
                          {tech.technician.name}
                        </div>
                        <div className="text-xs text-gray-500">
                          {tech.work_orders.length} jobs - {tech.total_duration}min
                        </div>
                      </div>
                    </div>
                    <span
                      className={`text-xs px-2 py-1 rounded-full ${
                        techStatusColors[tech.technician.status] || techStatusColors.offline
                      }`}
                    >
                      {tech.technician.status || 'offline'}
                    </span>
                  </div>

                  {/* Work Orders */}
                  <div className="p-2 space-y-2 max-h-[500px] overflow-y-auto">
                    {tech.work_orders.length === 0 ? (
                      <p className="text-sm text-gray-500 text-center py-4">
                        No scheduled work orders
                      </p>
                    ) : (
                      tech.work_orders.map((wo) => (
                        <div
                          key={wo.id}
                          className={`relative p-3 rounded-md border-l-4 ${
                            wo.priority === 'urgent' ? 'border-l-red-500 bg-red-50' :
                            wo.priority === 'high' ? 'border-l-orange-500 bg-orange-50' :
                            statusColors[wo.status] || 'border-l-gray-300 bg-gray-50'
                          } hover:shadow-md transition-shadow group`}
                        >
                          <Link to={`/work-orders/${wo.id}`} className="block">
                            <div className="flex items-center justify-between mb-1">
                              <div className="flex items-center gap-1.5">
                                <span className="text-xs font-mono text-gray-500">
                                  {wo.reference}
                                </span>
                                {(wo.priority === 'urgent' || wo.priority === 'high') && (
                                  <PriorityBadge priority={wo.priority} size="xs" />
                                )}
                              </div>
                              {wo.time_start && (
                                <span className="text-xs text-gray-500 flex items-center">
                                  <Clock className="h-3 w-3 mr-1" />
                                  {wo.time_start}
                                </span>
                              )}
                            </div>
                            <div className="text-sm font-medium text-gray-900 truncate">
                              {wo.title}
                            </div>
                            {wo.types?.length > 0 && (
                              <div className="flex gap-1 mt-1">
                                {wo.types.map((t) => (
                                  <span
                                    key={t.id}
                                    className="text-[9px] px-1 py-0.5 rounded font-medium"
                                    style={{
                                      backgroundColor: `${t.color}20`,
                                      color: t.color,
                                    }}
                                  >
                                    {t.name}
                                  </span>
                                ))}
                              </div>
                            )}
                            <div className="text-xs text-gray-500 mt-1 flex items-center">
                              <MapPin className="h-3 w-3 mr-1 flex-shrink-0" />
                              <span className="truncate">
                                {wo.customer_name} - {wo.address?.city}
                              </span>
                            </div>
                          </Link>

                          {/* Action menu */}
                          <div className="absolute right-2 top-2">
                            <button
                              onClick={(e) => {
                                e.preventDefault();
                                setActionMenuOpen(actionMenuOpen === wo.id ? null : wo.id);
                              }}
                              className="p-1 opacity-0 group-hover:opacity-100 hover:bg-white/50 rounded transition-all"
                            >
                              <MoreVertical className="h-4 w-4 text-gray-600" />
                            </button>

                            {actionMenuOpen === wo.id && (
                              <>
                                <div
                                  className="fixed inset-0 z-10"
                                  onClick={() => setActionMenuOpen(null)}
                                />
                                <div className="absolute right-0 mt-1 w-40 bg-white rounded-lg shadow-lg border border-gray-100 py-1 z-20">
                                  <button
                                    onClick={(e) => {
                                      e.preventDefault();
                                      handleReschedule(wo);
                                    }}
                                    className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                  >
                                    <CalendarClock className="h-4 w-4" />
                                    Reschedule
                                  </button>
                                  <button
                                    onClick={(e) => {
                                      e.preventDefault();
                                      handleAssign(wo);
                                    }}
                                    className="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                  >
                                    <UserPlus className="h-4 w-4" />
                                    Reassign
                                  </button>
                                </div>
                              </>
                            )}
                          </div>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {/* Modals */}
      <AssignTechnicianModal
        isOpen={assignModalOpen}
        onClose={() => {
          setAssignModalOpen(false);
          setSelectedWorkOrder(null);
        }}
        workOrder={selectedWorkOrder}
        selectedDate={selectedDate}
        onSuccess={handleModalSuccess}
      />

      <RescheduleModal
        isOpen={rescheduleModalOpen}
        onClose={() => {
          setRescheduleModalOpen(false);
          setSelectedWorkOrder(null);
        }}
        workOrder={selectedWorkOrder}
        onSuccess={handleModalSuccess}
      />
    </div>
  );
}

export default DispatchPage;
