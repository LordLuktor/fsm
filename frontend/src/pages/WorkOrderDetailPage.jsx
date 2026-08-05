import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { workOrders } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  ArrowLeft,
  MapPin,
  Phone,
  Mail,
  Clock,
  User,
  MessageSquare,
  Send,
  CheckCircle,
  XCircle,
  Play,
  Pause,
  Pencil,
  FileText,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import LoadingSpinner from '../components/common/LoadingSpinner';
import PriorityBadge from '../components/common/PriorityBadge';
import { formatScheduleDate } from '../utils/dates';
import PartsUsedSection from '../components/work-orders/PartsUsedSection';
import TimeTracker from '../components/work-orders/TimeTracker';
import CompletionFlow from '../components/work-orders/CompletionFlow';
import InvoiceView from '../components/work-orders/InvoiceView';
import MediaGallery from '../components/work-orders/MediaGallery';
import AssignTechnicianModal from '../components/dispatch/AssignTechnicianModal';

function WorkOrderDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasCapability, isTechnician } = useAuth();

  const [order, setOrder] = useState(null);
  const [notes, setNotes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [newNote, setNewNote] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [showCompletionFlow, setShowCompletionFlow] = useState(false);
  const [showInvoice, setShowInvoice] = useState(false);
  const [showAssignModal, setShowAssignModal] = useState(false);

  useEffect(() => {
    loadWorkOrder();
  }, [id]);

  const loadWorkOrder = async () => {
    try {
      const [orderRes, notesRes] = await Promise.all([
        workOrders.get(id),
        workOrders.getNotes(id),
      ]);
      setOrder(orderRes.data);
      setNotes(notesRes.data);
    } catch (err) {
      console.error('Failed to load work order:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleStatusChange = async (newStatus) => {
    try {
      await workOrders.updateStatus(id, newStatus);
      loadWorkOrder();
    } catch (err) {
      console.error('Failed to update status:', err);
      alert(err.response?.data?.message || 'Failed to update status');
    }
  };

  const handleAddNote = async (e) => {
    e.preventDefault();
    if (!newNote.trim()) return;

    setSubmitting(true);
    try {
      await workOrders.addNote(id, newNote);
      setNewNote('');
      loadWorkOrder();
    } catch (err) {
      console.error('Failed to add note:', err);
    } finally {
      setSubmitting(false);
    }
  };

  const statusColors = {
    new: 'bg-blue-100 text-blue-800 border-blue-200',
    assigned: 'bg-purple-100 text-purple-800 border-purple-200',
    en_route: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    in_progress: 'bg-orange-100 text-orange-800 border-orange-200',
    on_hold: 'bg-gray-100 text-gray-800 border-gray-200',
    completed: 'bg-green-100 text-green-800 border-green-200',
    cancelled: 'bg-red-100 text-red-800 border-red-200',
  };

  const getStatusActions = () => {
    if (!order) return [];

    const actions = [];
    const status = order.status;

    if (isTechnician) {
      if (status === 'assigned') {
        actions.push({ status: 'en_route', label: 'Start Route', icon: Play });
      }
      if (status === 'en_route') {
        actions.push({ status: 'in_progress', label: 'Start Work', icon: Play });
      }
      if (status === 'in_progress') {
        actions.push({ status: 'completed', label: 'Complete', icon: CheckCircle, useFlow: true });
        actions.push({ status: 'on_hold', label: 'Put On Hold', icon: Pause });
      }
      if (status === 'on_hold') {
        actions.push({ status: 'in_progress', label: 'Resume', icon: Play });
      }
    }

    if (hasCapability('fsm_edit_work_orders')) {
      if (['new', 'assigned', 'en_route', 'in_progress', 'on_hold'].includes(status)) {
        actions.push({ status: 'cancelled', label: 'Cancel', icon: XCircle, danger: true });
      }
    }

    return actions;
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="medium" />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="text-center py-12">
        <p className="text-gray-500">Work order not found</p>
        <button onClick={() => navigate('/work-orders')} className="btn btn-primary mt-4">
          Back to Work Orders
        </button>
      </div>
    );
  }

  return (
    <div className={`space-y-6 ${
      order.priority === 'urgent' ? 'border-t-4 border-red-500' :
      order.priority === 'high' ? 'border-t-4 border-orange-400' : ''
    }`}>
      {/* Header */}
      <div className="flex items-center gap-4">
        <button
          onClick={() => navigate(-1)}
          className="p-2 hover:bg-gray-100 rounded-md"
        >
          <ArrowLeft className="h-5 w-5" />
        </button>
        <div className="flex-1">
          <div className="flex items-center gap-3 flex-wrap">
            <span className="text-sm font-mono text-gray-500">{order.reference}</span>
            <span
              className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border ${
                statusColors[order.status]
              }`}
            >
              {order.status_label}
            </span>
            <PriorityBadge priority={order.priority} size="lg" />
            {order.types?.length > 0 && (
              <div className="flex gap-1.5">
                {order.types.map((t) => (
                  <span
                    key={t.id}
                    className="text-xs px-2 py-0.5 rounded font-medium border"
                    style={{
                      backgroundColor: `${t.color}15`,
                      color: t.color,
                      borderColor: `${t.color}40`,
                    }}
                  >
                    {t.name}
                  </span>
                ))}
              </div>
            )}
          </div>
          <h1 className="text-2xl font-bold text-gray-900 mt-1">{order.title}</h1>
        </div>
        {hasCapability('fsm_edit_work_orders') && (
          <Link
            to={`/work-orders/${id}/edit`}
            className="btn btn-secondary inline-flex items-center gap-2"
          >
            <Pencil className="h-4 w-4" />
            Edit
          </Link>
        )}
      </div>

      {/* Status Actions */}
      {getStatusActions().length > 0 && (
        <div className="flex flex-wrap gap-2">
          {getStatusActions().map((action) => (
            <button
              key={action.status}
              onClick={() =>
                action.useFlow
                  ? setShowCompletionFlow(true)
                  : handleStatusChange(action.status)
              }
              className={`btn inline-flex items-center ${
                action.danger ? 'btn-danger' : 'btn-primary'
              }`}
            >
              <action.icon className="h-4 w-4 mr-2" />
              {action.label}
            </button>
          ))}
          {order.status === 'completed' && (
            <button
              onClick={() => setShowInvoice(true)}
              className="btn btn-secondary inline-flex items-center"
            >
              <FileText className="h-4 w-4 mr-2" />
              View Invoice
            </button>
          )}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Description */}
          {order.description && (
            <div className="bg-white rounded-lg shadow p-6">
              <h2 className="text-lg font-medium text-gray-900 mb-4">Description</h2>
              <p className="text-gray-600 whitespace-pre-wrap">{order.description}</p>
            </div>
          )}

          {/* Parts Used */}
          <PartsUsedSection
            workOrderId={id}
            workOrderStatus={order.status}
          />

          {/* Media Attachments (optional — photos/videos for records + content production) */}
          <MediaGallery workOrderId={id} canEdit={true} />

          {/* Notes */}
          <div className="bg-white rounded-lg shadow">
            <div className="px-6 py-4 border-b border-gray-200">
              <h2 className="text-lg font-medium text-gray-900 flex items-center">
                <MessageSquare className="h-5 w-5 mr-2" />
                Notes ({notes.length})
              </h2>
            </div>

            <div className="p-6 space-y-4">
              {notes.length === 0 ? (
                <p className="text-gray-500 text-center py-4">No notes yet</p>
              ) : (
                notes.map((note) => (
                  <div
                    key={note.id}
                    className={`p-4 rounded-lg ${
                      note.type === 'status_change'
                        ? 'bg-blue-50 border border-blue-100'
                        : 'bg-gray-50'
                    }`}
                  >
                    <div className="flex items-center justify-between mb-2">
                      <span className="text-sm font-medium text-gray-900">
                        {note.author?.name}
                      </span>
                      <span className="text-xs text-gray-500">
                        {new Date(note.created_at).toLocaleString()}
                      </span>
                    </div>
                    <p className="text-sm text-gray-600">{note.content}</p>
                  </div>
                ))
              )}

              {/* Add Note Form */}
              <form onSubmit={handleAddNote} className="mt-4">
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={newNote}
                    onChange={(e) => setNewNote(e.target.value)}
                    placeholder="Add a note..."
                    className="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                  />
                  <button
                    type="submit"
                    disabled={submitting || !newNote.trim()}
                    className="btn btn-primary inline-flex items-center disabled:opacity-50"
                  >
                    <Send className="h-4 w-4" />
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Time Tracking */}
          <TimeTracker order={order} />

          {/* Assigned Technicians */}
          {!isTechnician && (
            <div className="bg-white rounded-lg shadow p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-medium text-gray-900 flex items-center">
                  <User className="h-5 w-5 mr-2" />
                  Assigned
                </h2>
                {order.status !== 'completed' && order.status !== 'cancelled' && hasCapability('fsm_assign_work_orders') && (
                  <button
                    onClick={() => setShowAssignModal(true)}
                    className="text-xs font-medium text-primary-600 hover:text-primary-700"
                  >
                    {order.technicians?.length ? 'Reassign' : 'Assign'}
                  </button>
                )}
              </div>
              {order.technicians?.length > 0 ? (
                <div className="space-y-2">
                  {order.technicians.map((tech) => (
                    <div key={tech.id} className="flex items-center gap-2 text-sm">
                      <div className="w-7 h-7 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-semibold">
                        {(tech.name || '?')[0]}
                      </div>
                      <span className="text-gray-900">{tech.name}</span>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-400">Unassigned</p>
              )}
            </div>
          )}

          {/* Customer Info */}
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
              <User className="h-5 w-5 mr-2" />
              Customer
            </h2>
            <div className="space-y-3">
              {order.customer?.name && (
                <p className="font-medium text-gray-900">{order.customer.name}</p>
              )}
              {order.customer?.phone && (
                <a
                  href={`tel:${order.customer.phone}`}
                  className="flex items-center text-sm text-primary-600 hover:text-primary-700"
                >
                  <Phone className="h-4 w-4 mr-2" />
                  {order.customer.phone}
                </a>
              )}
              {order.customer?.email && (
                <a
                  href={`mailto:${order.customer.email}`}
                  className="flex items-center text-sm text-primary-600 hover:text-primary-700"
                >
                  <Mail className="h-4 w-4 mr-2" />
                  {order.customer.email}
                </a>
              )}
            </div>
          </div>

          {/* Location */}
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
              <MapPin className="h-5 w-5 mr-2" />
              Location
            </h2>
            <div className="text-sm text-gray-600">
              {order.address?.street && <p>{order.address.street}</p>}
              {order.address?.city && (
                <p>
                  {order.address.city}
                  {order.address.state && `, ${order.address.state}`}
                  {order.address.zip && ` ${order.address.zip}`}
                </p>
              )}
            </div>
            {order.address?.latitude && order.address?.longitude && (
              <a
                href={`https://maps.google.com/?q=${order.address.latitude},${order.address.longitude}`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center mt-3 text-sm text-primary-600 hover:text-primary-700"
              >
                <MapPin className="h-4 w-4 mr-1" />
                Open in Maps
              </a>
            )}
          </div>

          {/* Schedule */}
          <div className="bg-white rounded-lg shadow p-6">
            <h2 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
              <Clock className="h-5 w-5 mr-2" />
              Schedule
            </h2>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-500">Date:</span>
                <span className="font-medium">
                  {order.scheduled_date
                    ? formatScheduleDate(order.scheduled_date)
                    : 'Not scheduled'}
                </span>
              </div>
              {order.scheduled_time_start && (
                <div className="flex justify-between">
                  <span className="text-gray-500">Time:</span>
                  <span className="font-medium">
                    {order.scheduled_time_start}
                    {order.scheduled_time_end && ` - ${order.scheduled_time_end}`}
                  </span>
                </div>
              )}
              <div className="flex justify-between">
                <span className="text-gray-500">Duration:</span>
                <span className="font-medium">{order.estimated_duration || 60} min</span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-500">Priority:</span>
                <PriorityBadge priority={order.priority} size="sm" />
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Completion Flow Modal */}
      <CompletionFlow
        isOpen={showCompletionFlow}
        onClose={() => setShowCompletionFlow(false)}
        orderId={id}
        onComplete={loadWorkOrder}
      />

      {/* Invoice Modal */}
      <InvoiceView
        isOpen={showInvoice}
        onClose={() => setShowInvoice(false)}
        order={order}
      />

      {/* Assign Technician Modal */}
      <AssignTechnicianModal
        isOpen={showAssignModal}
        onClose={() => setShowAssignModal(false)}
        workOrder={order}
        selectedDate={order.scheduled_date || new Date().toISOString().split('T')[0]}
        onSuccess={loadWorkOrder}
      />
    </div>
  );
}

export default WorkOrderDetailPage;
