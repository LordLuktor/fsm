import { useState, useEffect } from 'react';
import { dispatch, technicians } from '../../api/client';
import { User, Check, Loader2, Clock, MapPin, Navigation } from 'lucide-react';
import Modal from '../common/Modal';
import { useRouteOptimization } from '../../hooks/useRouteOptimization';

export default function AssignTechnicianModal({
  isOpen,
  onClose,
  workOrder,
  selectedDate,
  onSuccess,
}) {
  const [technicianList, setTechnicianList] = useState([]);
  const [selectedTechs, setSelectedTechs] = useState([]);
  const [primaryTech, setPrimaryTech] = useState(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const { suggestions, getSuggestions } = useRouteOptimization();

  useEffect(() => {
    if (isOpen) {
      loadTechnicians();
      // Load route optimization data if work order has coordinates
      if (workOrder?.address?.latitude) {
        getSuggestions(workOrder, selectedDate);
      }
    }
  }, [isOpen, selectedDate]);

  const loadTechnicians = async () => {
    setLoading(true);
    setError(null);
    try {
      // Get availability for the selected date
      const { data } = await dispatch.getAvailability(
        selectedDate,
        workOrder?.estimated_duration || 60
      );
      setTechnicianList(data.technicians || []);
    } catch (err) {
      console.error('Failed to load technicians:', err);
      // Fallback to simple technician list
      try {
        const { data } = await technicians.list({ status: 'active' });
        setTechnicianList(
          data.map((t) => ({
            ...t,
            available: true,
            scheduled_minutes: 0,
          }))
        );
      } catch (fallbackErr) {
        setError('Failed to load technicians');
      }
    } finally {
      setLoading(false);
    }
  };

  const toggleTechnician = (techId) => {
    setSelectedTechs((prev) => {
      if (prev.includes(techId)) {
        // Remove technician
        const updated = prev.filter((id) => id !== techId);
        // If removed tech was primary, clear primary
        if (primaryTech === techId) {
          setPrimaryTech(updated[0] || null);
        }
        return updated;
      } else {
        // Add technician
        const updated = [...prev, techId];
        // If first selection, make them primary
        if (!primaryTech) {
          setPrimaryTech(techId);
        }
        return updated;
      }
    });
  };

  const handleSubmit = async () => {
    if (selectedTechs.length === 0) {
      setError('Please select at least one technician');
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await dispatch.assign(
        workOrder.id,
        selectedTechs,
        primaryTech || selectedTechs[0]
      );
      onSuccess?.();
      onClose();
    } catch (err) {
      console.error('Failed to assign technicians:', err);
      setError(err.response?.data?.message || 'Failed to assign technicians');
    } finally {
      setSubmitting(false);
    }
  };

  const getStatusColor = (tech) => {
    if (!tech.available) return 'bg-red-100 text-red-700';
    if (tech.scheduled_minutes > 360) return 'bg-yellow-100 text-yellow-700';
    return 'bg-green-100 text-green-700';
  };

  const getStatusText = (tech) => {
    if (!tech.available) return 'Unavailable';
    if (tech.scheduled_minutes > 360) return 'Heavy load';
    return 'Available';
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Assign Technician" size="lg">
      {/* Work Order Summary */}
      {workOrder && (
        <div className="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-xs font-mono text-gray-500">{workOrder.reference}</p>
              <p className="font-medium text-gray-900 mt-1">{workOrder.title}</p>
              <p className="text-sm text-gray-500 mt-1 flex items-center gap-1">
                <MapPin className="h-3.5 w-3.5" />
                {workOrder.customer_name || workOrder.customer?.name} - {workOrder.address?.city}
              </p>
            </div>
            <div className="text-right">
              <p className="text-sm text-gray-500 flex items-center gap-1">
                <Clock className="h-3.5 w-3.5" />
                {workOrder.estimated_duration || 60} min
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Error */}
      {error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
          {error}
        </div>
      )}

      {/* Technician List */}
      <div className="space-y-2 max-h-80 overflow-y-auto">
        {loading ? (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
          </div>
        ) : technicianList.length === 0 ? (
          <div className="text-center py-12 text-gray-500">
            No technicians available
          </div>
        ) : (
          technicianList.map((tech) => {
            const isSelected = selectedTechs.includes(tech.id);
            const isPrimary = primaryTech === tech.id;

            return (
              <div
                key={tech.id}
                onClick={() => toggleTechnician(tech.id)}
                className={`relative p-4 rounded-lg border-2 cursor-pointer transition-all ${
                  isSelected
                    ? 'border-primary-500 bg-primary-50'
                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                }`}
              >
                <div className="flex items-center gap-4">
                  {/* Selection indicator */}
                  <div
                    className={`w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all ${
                      isSelected
                        ? 'border-primary-600 bg-primary-600'
                        : 'border-gray-300'
                    }`}
                  >
                    {isSelected && <Check className="h-4 w-4 text-white" />}
                  </div>

                  {/* Avatar */}
                  <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center">
                    <User className="h-5 w-5 text-primary-600" />
                  </div>

                  {/* Info */}
                  <div className="flex-1">
                    <div className="flex items-center gap-2">
                      <p className="font-medium text-gray-900">{tech.name || tech.display_name}</p>
                      {isPrimary && (
                        <span className="text-xs px-2 py-0.5 rounded-full bg-primary-600 text-white">
                          Primary
                        </span>
                      )}
                    </div>
                    <p className="text-sm text-gray-500">
                      {tech.scheduled_minutes > 0
                        ? `${Math.round(tech.scheduled_minutes / 60)}h scheduled today`
                        : 'No jobs scheduled'}
                    </p>
                    {(() => {
                      const suggestion = suggestions.find((s) => s.id === tech.id);
                      if (suggestion?.distance !== null && suggestion?.distance !== undefined) {
                        return (
                          <p className="text-xs text-primary-600 flex items-center gap-1 mt-0.5">
                            <Navigation className="h-3 w-3" />
                            {suggestion.distance} mi away
                            {suggestion.travelTime && ` (~${suggestion.travelTime} min drive)`}
                          </p>
                        );
                      }
                      return null;
                    })()}
                  </div>

                  {/* Status */}
                  <span
                    className={`text-xs px-2.5 py-1 rounded-full font-medium ${getStatusColor(tech)}`}
                  >
                    {getStatusText(tech)}
                  </span>
                </div>

                {/* Set as primary button */}
                {isSelected && selectedTechs.length > 1 && !isPrimary && (
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      setPrimaryTech(tech.id);
                    }}
                    className="absolute right-4 bottom-2 text-xs text-primary-600 hover:text-primary-700"
                  >
                    Set as primary
                  </button>
                )}
              </div>
            );
          })
        )}
      </div>

      {/* Actions */}
      <div className="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
        <button
          onClick={onClose}
          className="px-4 py-2 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
        >
          Cancel
        </button>
        <button
          onClick={handleSubmit}
          disabled={submitting || selectedTechs.length === 0}
          className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {submitting ? (
            <>
              <Loader2 className="h-4 w-4 animate-spin" />
              Assigning...
            </>
          ) : (
            <>
              <Check className="h-4 w-4" />
              Assign {selectedTechs.length > 0 ? `(${selectedTechs.length})` : ''}
            </>
          )}
        </button>
      </div>
    </Modal>
  );
}
