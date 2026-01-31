import { useState, useEffect } from 'react';
import { inventory } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  Package,
  Search,
  AlertTriangle,
  Plus,
  Minus,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';

function InventoryPage() {
  const { hasCapability, isTechnician } = useAuth();
  const [items, setItems] = useState([]);
  const [lowStock, setLowStock] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [view, setView] = useState(isTechnician ? 'mine' : 'warehouse');

  useEffect(() => {
    loadInventory();
  }, [view]);

  const loadInventory = async () => {
    setLoading(true);
    try {
      let itemsData;

      if (view === 'mine') {
        const res = await inventory.getMine();
        itemsData = res.data;
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

  const filteredItems = items.filter((item) =>
    item.name.toLowerCase().includes(search.toLowerCase()) ||
    item.sku.toLowerCase().includes(search.toLowerCase())
  );

  const statusColors = {
    active: 'bg-green-100 text-green-800',
    low_stock: 'bg-yellow-100 text-yellow-800',
    out_of_stock: 'bg-red-100 text-red-800',
    discontinued: 'bg-gray-100 text-gray-800',
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h1 className="text-2xl font-bold text-gray-900 flex items-center">
          <Package className="h-6 w-6 mr-2" />
          Inventory
        </h1>

        {hasCapability('fsm_manage_inventory') && (
          <button className="btn btn-primary inline-flex items-center">
            <Plus className="h-4 w-4 mr-2" />
            Add Item
          </button>
        )}
      </div>

      {/* Low Stock Alert */}
      {lowStock.length > 0 && hasCapability('fsm_view_inventory') && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex items-center">
            <AlertTriangle className="h-5 w-5 text-yellow-600 mr-2" />
            <span className="font-medium text-yellow-800">
              {lowStock.length} item{lowStock.length > 1 ? 's' : ''} low on stock
            </span>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="bg-white rounded-lg shadow p-4">
        <div className="flex flex-col sm:flex-row gap-4">
          <div className="flex-1 relative">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              type="text"
              placeholder="Search by name or SKU..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
            />
          </div>

          <div className="flex gap-2">
            {isTechnician && (
              <button
                onClick={() => setView('mine')}
                className={`btn ${view === 'mine' ? 'btn-primary' : 'btn-secondary'}`}
              >
                My Inventory
              </button>
            )}
            {hasCapability('fsm_view_inventory') && (
              <>
                <button
                  onClick={() => setView('warehouse')}
                  className={`btn ${view === 'warehouse' ? 'btn-primary' : 'btn-secondary'}`}
                >
                  Warehouse
                </button>
                <button
                  onClick={() => setView('low-stock')}
                  className={`btn ${view === 'low-stock' ? 'btn-primary' : 'btn-secondary'}`}
                >
                  Low Stock
                </button>
              </>
            )}
          </div>
        </div>
      </div>

      {/* Inventory Table */}
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
                  {hasCapability('fsm_consume_inventory') && (
                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  )}
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
                      ${item.unit_cost.toFixed(2)}
                    </td>
                    {hasCapability('fsm_consume_inventory') && (
                      <td className="px-6 py-4 whitespace-nowrap text-right">
                        <button
                          className="text-primary-600 hover:text-primary-700 p-1"
                          title="Consume"
                        >
                          <Minus className="h-4 w-4" />
                        </button>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

export default InventoryPage;
