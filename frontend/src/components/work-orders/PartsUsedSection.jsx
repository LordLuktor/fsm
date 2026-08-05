import { useState, useEffect } from 'react';
import { inventory, workOrders as woApi } from '../../api/client';
import { Package, Plus, ScanLine, Minus, Search, Check, Loader2, Camera } from 'lucide-react';
import Modal from '../common/Modal';
import BarcodeScanner from '../common/BarcodeScanner';

export default function PartsUsedSection({ workOrderId, workOrderStatus }) {
  const [consumedItems, setConsumedItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showConsumeModal, setShowConsumeModal] = useState(false);
  const [showScanner, setShowScanner] = useState(false);

  const isActive = ['assigned', 'en_route', 'in_progress'].includes(workOrderStatus);

  useEffect(() => {
    loadConsumedParts();
  }, [workOrderId]);

  const loadConsumedParts = async () => {
    try {
      const { data } = await inventory.getTransactions({
        work_order_id: workOrderId,
        type: 'consume',
      });
      setConsumedItems(data || []);
    } catch (err) {
      console.error('Could not load consumed parts:', err);
      setConsumedItems([]);
    } finally {
      setLoading(false);
    }
  };

  const handleConsumeSuccess = () => {
    loadConsumedParts();
  };

  const totalCost = consumedItems.reduce(
    (sum, item) => sum + (item.quantity_consumed || 0) * (item.unit_cost || 0),
    0
  );

  return (
    <div className="bg-white rounded-lg shadow">
      <div className="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 className="text-lg font-medium text-gray-900 flex items-center">
          <Package className="h-5 w-5 mr-2" />
          Parts Used
        </h2>
        {isActive && (
          <div className="flex gap-2">
            <button
              onClick={() => setShowScanner(true)}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
            >
              <Camera className="h-4 w-4" />
              Scan
            </button>
            <button
              onClick={() => setShowConsumeModal(true)}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors"
            >
              <Plus className="h-4 w-4" />
              Add Part
            </button>
          </div>
        )}
      </div>

      <div className="p-6">
        {loading ? (
          <div className="text-center py-4">
            <Loader2 className="h-5 w-5 animate-spin mx-auto text-gray-400" />
          </div>
        ) : consumedItems.length === 0 ? (
          <p className="text-gray-500 text-center py-4 text-sm">
            No parts consumed yet
            {isActive && ' — tap "Add Part" or "Scan" to record usage'}
          </p>
        ) : (
          <>
            <div className="space-y-3">
              {consumedItems.map((item, i) => (
                <div
                  key={item.id || i}
                  className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                >
                  <div className="flex-1 min-w-0">
                    <p className="text-xs font-mono text-gray-500">{item.sku}</p>
                    <p className="font-medium text-gray-900 text-sm truncate">
                      {item.name}
                    </p>
                  </div>
                  <div className="text-right ml-4">
                    <p className="font-medium text-gray-900 text-sm">
                      x{item.quantity_consumed || item.quantity}
                    </p>
                    {item.unit_cost > 0 && (
                      <p className="text-xs text-gray-500">
                        ${((item.quantity_consumed || item.quantity) * item.unit_cost).toFixed(2)}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>

            {totalCost > 0 && (
              <div className="mt-4 pt-3 border-t border-gray-200 flex justify-between text-sm">
                <span className="font-medium text-gray-600">Total Parts Cost</span>
                <span className="font-bold text-gray-900">${totalCost.toFixed(2)}</span>
              </div>
            )}
          </>
        )}
      </div>

      {/* Quick Consume Modal (pick from technician's inventory) */}
      {showConsumeModal && (
        <QuickConsumeModal
          isOpen={showConsumeModal}
          onClose={() => setShowConsumeModal(false)}
          workOrderId={workOrderId}
          onSuccess={handleConsumeSuccess}
        />
      )}

      {/* Barcode Scanner */}
      <BarcodeScanner
        isOpen={showScanner}
        onClose={() => setShowScanner(false)}
        onScan={(code) => {
          setShowScanner(false);
          // Open consume modal pre-filled with the scanned SKU
          setShowConsumeModal(true);
          // Store scanned code for the modal to pick up
          window.__fsm_scanned_sku = code;
        }}
      />
    </div>
  );
}

function QuickConsumeModal({ isOpen, onClose, workOrderId, onSuccess }) {
  const [myInventory, setMyInventory] = useState([]);
  const [selectedItem, setSelectedItem] = useState(null);
  const [quantity, setQuantity] = useState(1);
  const [searchQuery, setSearchQuery] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (isOpen) {
      loadInventory();
      setSelectedItem(null);
      setQuantity(1);
      setError(null);

      // Check for scanned SKU
      if (window.__fsm_scanned_sku) {
        setSearchQuery(window.__fsm_scanned_sku);
        delete window.__fsm_scanned_sku;
      } else {
        setSearchQuery('');
      }
    }
  }, [isOpen]);

  const loadInventory = async () => {
    try {
      const [mineRes, vehicleRes] = await Promise.allSettled([
        inventory.getMine(),
        inventory.getMyVehicle(),
      ]);

      const items = [];
      if (mineRes.status === 'fulfilled') items.push(...(mineRes.value.data || []));
      if (vehicleRes.status === 'fulfilled') items.push(...(vehicleRes.value.data || []));

      setMyInventory(items);

      // Auto-select if scanned SKU matches exactly one item
      if (window.__fsm_scanned_sku) {
        const match = items.find(
          (i) => i.sku?.toLowerCase() === window.__fsm_scanned_sku.toLowerCase()
        );
        if (match) setSelectedItem(match);
      }
    } catch (err) {
      console.error('Failed to load inventory:', err);
    }
  };

  const filtered = myInventory.filter(
    (item) =>
      item.name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      item.sku?.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!selectedItem) return;

    const available = selectedItem.available_quantity || selectedItem.quantity || 0;
    if (quantity > available) {
      setError(`Only ${available} available`);
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await inventory.consume(selectedItem.id, quantity, workOrderId, null);
      onSuccess?.();
      onClose();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to consume');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Add Part to Work Order" size="md">
      <form onSubmit={handleSubmit}>
        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {error}
          </div>
        )}

        {/* Search */}
        <div className="relative mb-4">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search by name or SKU..."
            className="w-full pl-10 pr-4 py-2.5 border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
            autoFocus
          />
        </div>

        {/* Item list */}
        <div className="space-y-2 max-h-48 overflow-y-auto mb-4">
          {filtered.length === 0 ? (
            <p className="text-center text-gray-500 text-sm py-4">
              {searchQuery ? 'No matching items' : 'No inventory available'}
            </p>
          ) : (
            filtered.map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => {
                  setSelectedItem(item);
                  setQuantity(1);
                }}
                className={`w-full text-left p-3 rounded-lg border-2 transition-all ${
                  selectedItem?.id === item.id
                    ? 'border-primary-500 bg-primary-50'
                    : 'border-gray-200 hover:border-gray-300'
                }`}
              >
                <div className="flex items-center justify-between">
                  <div className="min-w-0 flex-1">
                    <p className="text-xs font-mono text-gray-500">{item.sku}</p>
                    <p className="text-sm font-medium text-gray-900 truncate">
                      {item.name}
                    </p>
                  </div>
                  <div className="ml-3 text-right">
                    <p className="text-sm text-gray-600">
                      {item.available_quantity || item.quantity} {item.unit}
                    </p>
                    {selectedItem?.id === item.id && (
                      <Check className="h-4 w-4 text-primary-600 ml-auto" />
                    )}
                  </div>
                </div>
              </button>
            ))
          )}
        </div>

        {/* Quantity (shown when item selected) */}
        {selectedItem && (
          <div className="mb-4 p-4 bg-gray-50 rounded-lg">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Quantity to use
            </label>
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => setQuantity(Math.max(1, quantity - 1))}
                className="p-2 bg-white border border-gray-200 hover:bg-gray-100 rounded-lg"
              >
                <Minus className="h-4 w-4" />
              </button>
              <input
                type="number"
                value={quantity}
                onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
                min="1"
                max={selectedItem.available_quantity || selectedItem.quantity}
                className="w-20 px-3 py-2 text-center border-2 border-gray-200 rounded-lg focus:border-primary-500 outline-none"
              />
              <button
                type="button"
                onClick={() => setQuantity(quantity + 1)}
                className="p-2 bg-white border border-gray-200 hover:bg-gray-100 rounded-lg"
              >
                <Plus className="h-4 w-4" />
              </button>
              <span className="text-sm text-gray-500">{selectedItem.unit}</span>
            </div>
          </div>
        )}

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
            disabled={!selectedItem || submitting}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Adding...
              </>
            ) : (
              <>
                <Package className="h-4 w-4" />
                Use {quantity} {selectedItem?.unit || 'unit(s)'}
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}
