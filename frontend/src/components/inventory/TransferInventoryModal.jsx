import { useState, useEffect } from 'react';
import { inventory, technicians, vehicles } from '../../api/client';
import { ArrowRight, Loader2, User, Package, Truck } from 'lucide-react';
import Modal from '../common/Modal';

export default function TransferInventoryModal({
  isOpen,
  onClose,
  item,
  onSuccess,
}) {
  const [quantity, setQuantity] = useState(1);
  const [toLocationType, setToLocationType] = useState('technician');
  const [toLocationId, setToLocationId] = useState('');
  const [technicianList, setTechnicianList] = useState([]);
  const [vehicleList, setVehicleList] = useState([]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (isOpen) {
      loadOptions();
      setQuantity(1);
      setToLocationId('');
      setError(null);
    }
  }, [isOpen]);

  const loadOptions = async () => {
    setLoading(true);
    try {
      const [techRes, vehicleRes] = await Promise.all([
        technicians.list({ status: 'active' }),
        vehicles.list(),
      ]);
      setTechnicianList(techRes.data || []);
      setVehicleList(vehicleRes.data || []);
    } catch (err) {
      console.error('Failed to load options:', err);
    } finally {
      setLoading(false);
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

    if ((toLocationType === 'technician' || toLocationType === 'vehicle') && !toLocationId) {
      setError(`Please select a ${toLocationType}`);
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await inventory.transfer(
        item.id,
        quantity,
        toLocationType,
        toLocationType === 'warehouse' ? 0 : parseInt(toLocationId)
      );
      onSuccess?.();
      onClose();
    } catch (err) {
      console.error('Failed to transfer inventory:', err);
      setError(err.response?.data?.message || 'Failed to transfer inventory');
    } finally {
      setSubmitting(false);
    }
  };

  const maxQuantity = item?.available_quantity || item?.quantity || 0;

  const getSourceIcon = () => {
    if (item?.location_type === 'warehouse') {
      return <Package className="h-8 w-8 text-gray-600" />;
    } else if (item?.location_type === 'vehicle') {
      return <Truck className="h-8 w-8 text-gray-600" />;
    }
    return <User className="h-8 w-8 text-gray-600" />;
  };

  const getDestinationIcon = () => {
    if (toLocationType === 'warehouse') {
      return <Package className="h-8 w-8 text-primary-600" />;
    } else if (toLocationType === 'vehicle') {
      return <Truck className="h-8 w-8 text-primary-600" />;
    }
    return <User className="h-8 w-8 text-primary-600" />;
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Transfer Inventory" size="md">
      <form onSubmit={handleSubmit}>
        {/* Item info */}
        <div className="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 rounded-lg bg-primary-100 flex items-center justify-center">
              <Package className="h-6 w-6 text-primary-600" />
            </div>
            <div>
              <p className="text-xs font-mono text-gray-500">{item?.sku}</p>
              <p className="font-medium text-gray-900">{item?.name}</p>
              <p className="text-sm text-gray-500">
                Available: {maxQuantity} {item?.unit}
              </p>
            </div>
          </div>
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
            Quantity to Transfer
          </label>
          <div className="flex items-center gap-3">
            <input
              type="number"
              value={quantity}
              onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
              min="1"
              max={maxQuantity}
              className="w-32 px-4 py-2.5 text-center border-2 border-gray-200 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none"
            />
            <span className="text-sm text-gray-500">{item?.unit}</span>

            <input
              type="range"
              value={quantity}
              onChange={(e) => setQuantity(parseInt(e.target.value))}
              min="1"
              max={maxQuantity}
              className="flex-1"
            />
          </div>
        </div>

        {/* Transfer visualization */}
        <div className="mb-5 flex items-center justify-center gap-4 py-4">
          <div className="text-center">
            <div className="w-16 h-16 mx-auto rounded-xl bg-gray-100 flex items-center justify-center mb-2">
              {getSourceIcon()}
            </div>
            <p className="text-sm font-medium text-gray-900 capitalize">
              {item?.location_type || 'Source'}
            </p>
          </div>

          <ArrowRight className="h-6 w-6 text-primary-600" />

          <div className="text-center">
            <div className="w-16 h-16 mx-auto rounded-xl bg-primary-100 flex items-center justify-center mb-2">
              {getDestinationIcon()}
            </div>
            <p className="text-sm font-medium text-gray-900 capitalize">
              {toLocationType}
            </p>
          </div>
        </div>

        {/* Destination type */}
        <div className="mb-5">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Transfer To
          </label>
          <div className="grid grid-cols-3 gap-3">
            <button
              type="button"
              onClick={() => {
                setToLocationType('warehouse');
                setToLocationId('');
              }}
              className={`p-4 rounded-lg border-2 text-center transition-all ${
                toLocationType === 'warehouse'
                  ? 'border-primary-500 bg-primary-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <Package className={`h-6 w-6 mx-auto mb-2 ${
                toLocationType === 'warehouse' ? 'text-primary-600' : 'text-gray-400'
              }`} />
              <p className="font-medium text-gray-900 text-sm">Warehouse</p>
            </button>
            <button
              type="button"
              onClick={() => {
                setToLocationType('vehicle');
                setToLocationId('');
              }}
              className={`p-4 rounded-lg border-2 text-center transition-all ${
                toLocationType === 'vehicle'
                  ? 'border-primary-500 bg-primary-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <Truck className={`h-6 w-6 mx-auto mb-2 ${
                toLocationType === 'vehicle' ? 'text-primary-600' : 'text-gray-400'
              }`} />
              <p className="font-medium text-gray-900 text-sm">Vehicle</p>
            </button>
            <button
              type="button"
              onClick={() => {
                setToLocationType('technician');
                setToLocationId('');
              }}
              className={`p-4 rounded-lg border-2 text-center transition-all ${
                toLocationType === 'technician'
                  ? 'border-primary-500 bg-primary-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <User className={`h-6 w-6 mx-auto mb-2 ${
                toLocationType === 'technician' ? 'text-primary-600' : 'text-gray-400'
              }`} />
              <p className="font-medium text-gray-900 text-sm">Technician</p>
            </button>
          </div>
        </div>

        {/* Vehicle selection */}
        {toLocationType === 'vehicle' && (
          <div className="mb-5">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select Vehicle
            </label>
            {loading ? (
              <div className="flex items-center justify-center py-8">
                <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
              </div>
            ) : vehicleList.length === 0 ? (
              <div className="text-center py-4 text-gray-500">No vehicles available</div>
            ) : (
              <div className="space-y-2 max-h-48 overflow-y-auto">
                {vehicleList.map((vehicle) => (
                  <button
                    key={vehicle.id}
                    type="button"
                    onClick={() => setToLocationId(String(vehicle.id))}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 transition-all ${
                      toLocationId === String(vehicle.id)
                        ? 'border-primary-500 bg-primary-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <div className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                      <Truck className="h-5 w-5 text-gray-600" />
                    </div>
                    <div className="text-left flex-1">
                      <p className="font-medium text-gray-900">
                        {vehicle.name || vehicle.title}
                      </p>
                      <p className="text-sm text-gray-500">
                        {vehicle.plate && `${vehicle.plate} - `}
                        {vehicle.year} {vehicle.make} {vehicle.model}
                      </p>
                    </div>
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Technician selection */}
        {toLocationType === 'technician' && (
          <div className="mb-5">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select Technician
            </label>
            {loading ? (
              <div className="flex items-center justify-center py-8">
                <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
              </div>
            ) : technicianList.length === 0 ? (
              <div className="text-center py-4 text-gray-500">No technicians available</div>
            ) : (
              <div className="space-y-2 max-h-48 overflow-y-auto">
                {technicianList.map((tech) => (
                  <button
                    key={tech.id}
                    type="button"
                    onClick={() => setToLocationId(String(tech.id))}
                    className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 transition-all ${
                      toLocationId === String(tech.id)
                        ? 'border-primary-500 bg-primary-50'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <div className="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                      <User className="h-5 w-5 text-gray-600" />
                    </div>
                    <div className="text-left">
                      <p className="font-medium text-gray-900">
                        {tech.name || tech.display_name}
                      </p>
                      {tech.email && (
                        <p className="text-sm text-gray-500">{tech.email}</p>
                      )}
                    </div>
                  </button>
                ))}
              </div>
            )}
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
            disabled={submitting || ((toLocationType === 'technician' || toLocationType === 'vehicle') && !toLocationId)}
            className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            {submitting ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" />
                Transferring...
              </>
            ) : (
              <>
                <ArrowRight className="h-4 w-4" />
                Transfer {quantity} {item?.unit}
              </>
            )}
          </button>
        </div>
      </form>
    </Modal>
  );
}
