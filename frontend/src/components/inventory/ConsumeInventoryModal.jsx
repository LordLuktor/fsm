import { useState, useEffect } from 'react';
import { inventory, workOrders } from '../../api/client';
import { Minus, Loader2, Check, Search, Camera } from 'lucide-react';
import Modal from '../common/Modal';
import BarcodeScanner from '../common/BarcodeScanner';

export default function ConsumeInventoryModal({
  isOpen,
  onClose,
  item,
  onSuccess,
}) {
  const [quantity, setQuantity] = useState(1);
  const [workOrderId, setWorkOrderId] = useState('');
  const [notes, setNotes] = useState('');
  const [recentOrders, setRecentOrders] = useState([]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [searchQuery, setSearchQuery] = useState('');

  useEffect(() => {
    if (isOpen) {
      loadRecentWorkOrders();
      setQuantity(1);
      setWorkOrderId('');
      setNotes('');
      setError(null);
    }
  }, [isOpen]);

  const loadRecentWorkOrders = async () => {
    try {
      const { data } = await workOrders.getToday();
      setRecentOrders(data.filter((wo) =>
        ['assigned', 'en_route', 'in_progress'].includes(wo.status)
      ));
    } catch (err) {
      console.error('Failed to load work orders:', err);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (quantity <= 0) {
      setError('Quantity must be greater than 0');
      return;
    }

    if (quantity > (item?.available_quantity || item?.quantity || 0)) {
      setError('Not enough inventory available');
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await inventory.consume(item.id, quantity, workOrderId || null, notes || null);
      onSuccess?.();
      onClose();
    } catch (err) {
      console.error('Failed to consume inventory:', err);
      setError(err.response?.data?.message || 'Failed to consume inventory');
    } finally {
      setSubmitting(false);
    }
  };

  const filteredOrders = recentOrders.filter((wo) =>
    wo.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    wo.reference.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const maxQuantity = item?.available_quantity || item?.quantity || 0;

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Consume Inventory" size="md">
      <form onSubmit={handleSubmit}>
        {/* Item info */}
        <div className="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100">
          <p className="text-xs font-mono text-gray-500">{item?.sku}</p>
          <p className="font-medium text-gray-900 mt-1">{item?.name}</p>
          <p className="text-sm text-gray-500 mt-1">
            Available: {maxQuantity} {item?.unit}
          </p>
        </div>

        {/* Error */}
        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Quantity */}
        <div className="mb-5">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Quantity
          </label>
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setQuantity(Math.max(1, quantity - 1))}
              className="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            >
              <Minus className="h-4 w-4" />
            </button>
            <input
              type="number"
              value={quantity}
              onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
              min="1"
              max={maxQuantity}
              className="w-24 px-4 py-2 text-center border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
            />
            <button
              type="button"
              onClick={() => setQuantity(Math.min(maxQuantity, quantity + 1))}
              className="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            >
              <span className="text-lg font-medium">+</span>
            </button>
            <span className="text-sm text-gray-500">{item?.unit}</span>
          </div>

          {/* Quick quantity buttons */}
          <div className="flex gap-2 mt-3">
            {[1, 2, 5, 10].map((q) => (
              <button
                key={q}
                type="button"
                onClick={() => setQuantity(Math.min(maxQuantity, q))}
                disabled={q > maxQuantity}
                className={`px-3 py-1 text-sm rounded-lg transition-colors ${
                  quantity === q
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 disabled:opacity-50'
                }`}
              >
                {q}
              </button>
            ))}
          </div>
        </div>

        {/* Work Order (optional) */}
        <div className="mb-5">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Link to Work Order (optional)
          </label>

          {recentOrders.length > 0 && (
            <>
              <div className="relative mb-3">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search work orders..."
                  className="w-full pl-10 pr-4 py-2 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
                />
              </div>

              <div className="space-y-2 max-h-40 overflow-y-auto">
                {filteredOrders.map((wo) => (
                  <button
                    key={wo.id}
                    type="button"
                    onClick={() => setWorkOrderId(workOrderId === wo.id ? '' : wo.id)}
                    className={`w-full text-left p-3 rounded-lg border-2 transition-all ${
                      workOrderId === wo.id
                        ? 'border-primary-500 bg-primary-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-xs font-mono text-gray-500">{wo.reference}</p>
                        <p className="text-sm font-medium text-gray-900 truncate">
                          {wo.title}
                        </p>
                      </div>
                      {workOrderId === wo.id && (
                        <Check className="h-5 w-5 text-primary-600" />
                      )}
                    </div>
                  </button>
                ))}
              </div>
            </>
          )}
        </div>

        {/* Notes */}
        <div className="mb-5">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Notes (optional)
          </label>
          <textarea
            value={notes}
            onChange={(e) => setNotes(e.target.value)}
            placeholder="Add any notes about this usage..."
            rows={2}
            className="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none resize-none"
          />
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-gray-700 font-medium hover:bg-gray-100 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={submitting || quantity <= 0}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Consuming...
              </>
            ) : (
              <>
                <Minus className="h-4 w-4" />
                Consume {quantity} {item?.unit}
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}
