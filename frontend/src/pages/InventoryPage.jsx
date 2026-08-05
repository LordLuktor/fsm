import { useState, useEffect } from 'react';
import { inventory } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  Package,
  Search,
  AlertTriangle,
  Plus,
  Minus,
  ArrowRightLeft,
  RefreshCw,
  Truck,
  ChevronRight,
  ArrowLeft,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';
import ConsumeInventoryModal from '../components/inventory/ConsumeInventoryModal';
import AddInventoryModal from '../components/inventory/AddInventoryModal';
import TransferInventoryModal from '../components/inventory/TransferInventoryModal';

function InventoryPage() {
  const { hasCapability, isTechnician } = useAuth();
  const [items, setItems] = useState([]);
  const [lowStock, setLowStock] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [view, setView] = useState(isTechnician ? 'mine' : 'warehouse');

  // Vehicle-specific states
  const [vehicles, setVehicles] = useState([]);
  const [selectedVehicle, setSelectedVehicle] = useState(null);
  const [vehicleInventory, setVehicleInventory] = useState(null);

  // Modal states
  const [consumeModalOpen, setConsumeModalOpen] = useState(false);
  const [addModalOpen, setAddModalOpen] = useState(false);
  const [transferModalOpen, setTransferModalOpen] = useState(false);
  const [selectedItem, setSelectedItem] = useState(null);

  useEffect(() => {
    if (view === 'vehicles' && !selectedVehicle) {
      loadVehicles();
    } else if (selectedVehicle) {
      loadVehicleInventory(selectedVehicle.id);
    } else {
      loadInventory();
    }
  }, [view, selectedVehicle]);

  const loadInventory = async () => {
    setLoading(true);
    try {
      let itemsData;

      if (view === 'mine') {
        const res = await inventory.getMine();
        itemsData = res.data;
      } else if (view === 'my-vehicle') {
        const res = await inventory.getMyVehicle();
        itemsData = res.data.items || [];
        setVehicleInventory(res.data);
      } else if (view === 'low-stock') {
        const res = await inventory.getLowStock();
        itemsData = res.data;
      } else {
        const res = await inventory.list({
          location_type: view,
          location_id: 0,
        });
        itemsData = res.data;
      }

      setItems(itemsData);

      // Load low stock for alert
      if (hasCapability('fsm_view_inventory')) {
        const lowRes = await inventory.getLowStock();
        setLowStock(lowRes.data);
      }
    } catch (err) {
      console.error('Failed to load inventory:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadVehicles = async () => {
    setLoading(true);
    try {
      const res = await inventory.getAllVehiclesInventory();
      setVehicles(res.data);
    } catch (err) {
      console.error('Failed to load vehicles:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadVehicleInventory = async (vehicleId) => {
    setLoading(true);
    try {
      const res = await inventory.getVehicleInventory(vehicleId);
      setVehicleInventory(res.data);
      setItems(res.data.items || []);
    } catch (err) {
      console.error('Failed to load vehicle inventory:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleConsume = (item) => {
    setSelectedItem(item);
    setConsumeModalOpen(true);
  };

  const handleTransfer = (item) => {
    setSelectedItem(item);
    setTransferModalOpen(true);
  };

  const handleModalSuccess = () => {
    if (selectedVehicle) {
      loadVehicleInventory(selectedVehicle.id);
    } else if (view === 'vehicles') {
      loadVehicles();
    } else {
      loadInventory();
    }
  };

  const handleVehicleClick = (vehicle) => {
    setSelectedVehicle(vehicle);
  };

  const handleBackToVehicles = () => {
    setSelectedVehicle(null);
    setVehicleInventory(null);
  };

  const filteredItems = items.filter((item) =>
    item.name.toLowerCase().includes(search.toLowerCase()) ||
    item.sku.toLowerCase().includes(search.toLowerCase())
  );

  const filteredVehicles = vehicles.filter((vehicle) =>
    vehicle.name.toLowerCase().includes(search.toLowerCase()) ||
    (vehicle.plate || '').toLowerCase().includes(search.toLowerCase()) ||
    (vehicle.assigned_technician?.name || '').toLowerCase().includes(search.toLowerCase())
  );

  const statusColors = {
    active: 'bg-green-100 text-green-800',
    low_stock: 'bg-yellow-100 text-yellow-800',
    out_of_stock: 'bg-red-100 text-red-800',
    discontinued: 'bg-gray-100 text-gray-800',
  };

  const vehicleStatusColors = {
    active: 'bg-green-100 text-green-800',
    maintenance: 'bg-yellow-100 text-yellow-800',
    retired: 'bg-gray-100 text-gray-800',
  };

  // Render vehicle list
  const renderVehicleList = () => (
    <div className="bg-white rounded-lg shadow overflow-hidden">
      {loading ? (
        <div className="flex items-center justify-center h-64">
          <LoadingSpinner size="medium" />
        </div>
      ) : filteredVehicles.length === 0 ? (
        <div className="p-8 text-center text-gray-500">
          <Truck className="h-12 w-12 mx-auto text-gray-300 mb-3" />
          <p>No vehicles found</p>
        </div>
      ) : (
        <div className="divide-y divide-gray-200">
          {filteredVehicles.map((vehicle) => (
            <div
              key={vehicle.id}
              onClick={() => handleVehicleClick(vehicle)}
              className="p-4 hover:bg-gray-50 cursor-pointer transition-colors"
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <div className="p-3 bg-primary-50 rounded-lg">
                    <Truck className="h-6 w-6 text-primary-600" />
                  </div>
                  <div>
                    <h3 className="font-medium text-gray-900">{vehicle.name}</h3>
                    <div className="text-sm text-gray-500">
                      {vehicle.year} {vehicle.make} {vehicle.model}
                      {vehicle.plate && <span className="ml-2">({vehicle.plate})</span>}
                    </div>
                    {vehicle.assigned_technician && (
                      <div className="text-sm text-gray-500">
                        Assigned to: {vehicle.assigned_technician.name}
                      </div>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-6">
                  <div className="text-right">
                    <div className="text-sm font-medium text-gray-900">
                      {vehicle.inventory.item_count} items
                    </div>
                    <div className="text-sm text-gray-500">
                      {vehicle.inventory.total_quantity} units
                    </div>
                    <div className="text-xs text-gray-400">
                      ${vehicle.inventory.total_value.toFixed(2)} value
                    </div>
                  </div>
                  <span
                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                      vehicleStatusColors[vehicle.status] || vehicleStatusColors.active
                    }`}
                  >
                    {vehicle.status}
                  </span>
                  <ChevronRight className="h-5 w-5 text-gray-400" />
                </div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );

  // Render vehicle inventory header
  const renderVehicleHeader = () => {
    if (!vehicleInventory?.vehicle) return null;

    const v = vehicleInventory.vehicle;
    return (
      <div className="bg-white rounded-lg shadow p-4 mb-4">
        <div className="flex items-center gap-4">
          <button
            onClick={handleBackToVehicles}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
          >
            <ArrowLeft className="h-5 w-5 text-gray-600" />
          </button>
          <div className="p-3 bg-primary-50 rounded-lg">
            <Truck className="h-6 w-6 text-primary-600" />
          </div>
          <div className="flex-1">
            <h2 className="font-semibold text-lg text-gray-900">{v.name}</h2>
            <div className="text-sm text-gray-500">
              {v.year} {v.make} {v.model}
              {v.plate && <span className="ml-2">Plate: {v.plate}</span>}
            </div>
            {v.assigned_technician && (
              <div className="text-sm text-gray-600">
                Assigned to: <span className="font-medium">{v.assigned_technician.name}</span>
              </div>
            )}
          </div>
          <div className="text-right">
            <div className="text-lg font-semibold text-gray-900">
              {vehicleInventory.summary.total_items} items
            </div>
            <div className="text-sm text-gray-500">
              ${vehicleInventory.summary.total_value.toFixed(2)} total value
            </div>
          </div>
        </div>
      </div>
    );
  };

  // Render inventory table
  const renderInventoryTable = () => (
    <div className="bg-white rounded-lg shadow overflow-hidden">
      {loading ? (
        <div className="flex items-center justify-center h-64">
          <LoadingSpinner size="medium" />
        </div>
      ) : filteredItems.length === 0 ? (
        <div className="p-8 text-center text-gray-500">
          <Package className="h-12 w-12 mx-auto text-gray-300 mb-3" />
          <p>No inventory items found</p>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  SKU
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Name
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Category
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Quantity
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Unit Cost
                </th>
                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {filteredItems.map((item) => (
                <tr key={item.id} className="hover:bg-gray-50">
                  <td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500">
                    {item.sku}
                  </td>
                  <td className="px-6 py-4">
                    <div className="text-sm font-medium text-gray-900">{item.name}</div>
                    {item.description && (
                      <div className="text-sm text-gray-500 truncate max-w-xs">
                        {item.description}
                      </div>
                    )}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {item.category || '-'}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="text-sm font-medium text-gray-900">
                      {item.quantity} {item.unit}
                    </div>
                    {item.reserved_quantity > 0 && (
                      <div className="text-xs text-gray-500">
                        ({item.available_quantity} available)
                      </div>
                    )}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span
                      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                        statusColors[item.status]
                      }`}
                    >
                      {item.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    ${(item.unit_cost || 0).toFixed(2)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right">
                    <div className="flex items-center justify-end gap-1">
                      {hasCapability('fsm_consume_inventory') && (
                        <button
                          onClick={() => handleConsume(item)}
                          disabled={item.quantity <= 0}
                          className="p-2 text-gray-600 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                          title="Consume"
                        >
                          <Minus className="h-4 w-4" />
                        </button>
                      )}
                      {hasCapability('fsm_manage_inventory') && (
                        <button
                          onClick={() => handleTransfer(item)}
                          disabled={item.quantity <= 0}
                          className="p-2 text-gray-600 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                          title="Transfer"
                        >
                          <ArrowRightLeft className="h-4 w-4" />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
          <Package className="h-6 w-6 mr-2" />
          Inventory
          {selectedVehicle && (
            <span className="text-gray-400 font-normal ml-2">/ {selectedVehicle.name}</span>
          )}
        </h1>

        <div className="flex items-center gap-2">
          <button
            onClick={() => {
              if (selectedVehicle) {
                loadVehicleInventory(selectedVehicle.id);
              } else if (view === 'vehicles') {
                loadVehicles();
              } else {
                loadInventory();
              }
            }}
            disabled={loading}
            className="p-2 hover:bg-gray-100 rounded-md transition-colors"
            title="Refresh"
          >
            <RefreshCw className={`h-5 w-5 text-gray-600 ${loading ? 'animate-spin' : ''}`} />
          </button>

          {hasCapability('fsm_manage_inventory') && (
            <button
              onClick={() => setAddModalOpen(true)}
              className="btn btn-primary inline-flex items-center"
            >
              <Plus className="h-4 w-4 mr-2" />
              Add Item
            </button>
          )}
        </div>
      </div>

      {/* Low Stock Alert */}
      {lowStock.length > 0 && hasCapability('fsm_view_inventory') && !selectedVehicle && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center">
              <AlertTriangle className="h-5 w-5 text-yellow-600 mr-2" />
              <span className="font-medium text-yellow-800">
                {lowStock.length} item{lowStock.length > 1 ? 's' : ''} low on stock
              </span>
            </div>
            <button
              onClick={() => setView('low-stock')}
              className="text-sm text-yellow-700 hover:text-yellow-800 font-medium"
            >
              View all
            </button>
          </div>
        </div>
      )}

      {/* Filters */}
      {!selectedVehicle && (
        <div className="bg-white rounded-lg shadow p-4">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <input
                type="text"
                placeholder={view === 'vehicles' ? 'Search vehicles...' : 'Search by name or SKU...'}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
              />
            </div>

            <div className="flex gap-2 flex-wrap">
              {isTechnician && (
                <>
                  <button
                    onClick={() => { setView('mine'); setSelectedVehicle(null); }}
                    className={`btn ${view === 'mine' && !selectedVehicle ? 'btn-primary' : 'btn-secondary'}`}
                  >
                    My Inventory
                  </button>
                  <button
                    onClick={() => { setView('my-vehicle'); setSelectedVehicle(null); }}
                    className={`btn ${view === 'my-vehicle' && !selectedVehicle ? 'btn-primary' : 'btn-secondary'}`}
                  >
                    My Truck
                  </button>
                </>
              )}
              {hasCapability('fsm_view_inventory') && (
                <>
                  <button
                    onClick={() => { setView('warehouse'); setSelectedVehicle(null); }}
                    className={`btn ${view === 'warehouse' && !selectedVehicle ? 'btn-primary' : 'btn-secondary'}`}
                  >
                    Warehouse
                  </button>
                  <button
                    onClick={() => { setView('vehicles'); setSelectedVehicle(null); }}
                    className={`btn ${view === 'vehicles' && !selectedVehicle ? 'btn-primary' : 'btn-secondary'}`}
                  >
                    <Truck className="h-4 w-4 mr-1" />
                    Vehicles
                  </button>
                  <button
                    onClick={() => { setView('low-stock'); setSelectedVehicle(null); }}
                    className={`btn ${view === 'low-stock' && !selectedVehicle ? 'btn-primary' : 'btn-secondary'}`}
                  >
                    Low Stock
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Vehicle header when viewing specific vehicle */}
      {selectedVehicle && renderVehicleHeader()}

      {/* Content */}
      {view === 'vehicles' && !selectedVehicle ? renderVehicleList() : renderInventoryTable()}

      {/* Modals */}
      <ConsumeInventoryModal
        isOpen={consumeModalOpen}
        onClose={() => {
          setConsumeModalOpen(false);
          setSelectedItem(null);
        }}
        item={selectedItem}
        onSuccess={handleModalSuccess}
      />

      <AddInventoryModal
        isOpen={addModalOpen}
        onClose={() => setAddModalOpen(false)}
        onSuccess={handleModalSuccess}
      />

      <TransferInventoryModal
        isOpen={transferModalOpen}
        onClose={() => {
          setTransferModalOpen(false);
          setSelectedItem(null);
        }}
        item={selectedItem}
        onSuccess={handleModalSuccess}
      />
    </div>
  );
}

export default InventoryPage;
