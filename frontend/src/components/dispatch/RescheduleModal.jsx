import { useState } from 'react';
import { dispatch } from '../../api/client';
import { Calendar, Clock, Loader2, Check } from 'lucide-react';
import Modal from '../common/Modal';

export default function RescheduleModal({
  isOpen,
  onClose,
  workOrder,
  onSuccess,
}) {
  const [date, setDate] = useState(workOrder?.scheduled_date || '');
  const [timeStart, setTimeStart] = useState(workOrder?.scheduled_time_start || workOrder?.time_start || '');
  const [timeEnd, setTimeEnd] = useState(workOrder?.scheduled_time_end || workOrder?.time_end || '');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!date) {
      setError('Please select a date');
      return;
    }

    if (timeStart && timeEnd && timeStart >= timeEnd) {
      setError('End time must be after start time');
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await dispatch.reschedule(workOrder.id, date, timeStart, timeEnd);
      onSuccess?.();
      onClose();
    } catch (err) {
      console.error('Failed to reschedule:', err);
      setError(err.response?.data?.message || 'Failed to reschedule work order');
    } finally {
      setSubmitting(false);
    }
  };

  const quickDates = [
    { label: 'Today', date: new Date().toISOString().split('T')[0] },
    {
      label: 'Tomorrow',
      date: new Date(Date.now() + 86400000).toISOString().split('T')[0],
    },
    {
      label: 'Next Week',
      date: new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
    },
  ];

  const timeSlots = [
    { label: 'Morning (8-12)', start: '08:00', end: '12:00' },
    { label: 'Afternoon (12-5)', start: '12:00', end: '17:00' },
    { label: 'Full Day (8-5)', start: '08:00', end: '17:00' },
  ];

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Reschedule Work Order" size="md">
      <form onSubmit={handleSubmit}>
        {/* Work Order Info */}
        <div className="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100">
          <p className="text-xs font-mono text-gray-500">{workOrder?.reference}</p>
          <p className="font-medium text-gray-900 mt-1">{workOrder?.title}</p>
        </div>

        {/* Error */}
        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Date Selection */}
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              <Calendar className="inline h-4 w-4 mr-1.5" />
              Date
            </label>

            {/* Quick date buttons */}
            <div className="flex gap-2 mb-3">
              {quickDates.map((qd) => (
                <button
                  key={qd.label}
                  type="button"
                  onClick={() => setDate(qd.date)}
                  className={`px-3 py-1.5 text-sm font-medium rounded-lg transition-colors ${
                    date === qd.date
                      ? 'bg-primary-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  {qd.label}
                </button>
              ))}
            </div>

            <input
              type="date"
              value={date}
              onChange={(e) => setDate(e.target.value)}
              className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
            />
          </div>

          {/* Time Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              <Clock className="inline h-4 w-4 mr-1.5" />
              Time Slot
            </label>

            {/* Quick time slots */}
            <div className="flex flex-wrap gap-2 mb-3">
              {timeSlots.map((slot) => (
                <button
                  key={slot.label}
                  type="button"
                  onClick={() => {
                    setTimeStart(slot.start);
                    setTimeEnd(slot.end);
                  }}
                  className={`px-3 py-1.5 text-sm font-medium rounded-lg transition-colors ${
                    timeStart === slot.start && timeEnd === slot.end
                      ? 'bg-primary-600 text-white'
                      : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                  }`}
                >
                  {slot.label}
                </button>
              ))}
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs text-gray-500 mb-1">Start</label>
                <input
                  type="time"
                  value={timeStart}
                  onChange={(e) => setTimeStart(e.target.value)}
                  className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                />
              </div>
              <div>
                <label className="block text-xs text-gray-500 mb-1">End</label>
                <input
                  type="time"
                  value={timeEnd}
                  onChange={(e) => setTimeEnd(e.target.value)}
                  className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all"
                />
              </div>
            </div>
          </div>
        </div>

        {/* Actions */}
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
                Saving...
              </>
            ) : (
              <>
                <Check className="h-4 w-4" />
                Reschedule
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}
