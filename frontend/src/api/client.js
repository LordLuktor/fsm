import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'https://fsm-api.steinmetz.ltd/wp-json';

const client = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor to add auth token
client.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('fsm_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response interceptor for error handling
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('fsm_token');
      localStorage.removeItem('fsm_user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// Auth endpoints
export const auth = {
  login: (username, password) =>
    client.post('/jwt-auth/v1/token', { username, password }),
  validate: () =>
    client.post('/jwt-auth/v1/token/validate'),
  getUser: () =>
    client.get('/fsm/v1/me'),
  getCapabilities: () =>
    client.get('/fsm/v1/me/capabilities'),
};

// Work Orders
export const workOrders = {
  list: (params) => client.get('/fsm/v1/work-orders', { params }),
  get: (id) => client.get(`/fsm/v1/work-orders/${id}`),
  create: (data) => client.post('/fsm/v1/work-orders', data),
  update: (id, data) => client.patch(`/fsm/v1/work-orders/${id}`, data),
  delete: (id) => client.delete(`/fsm/v1/work-orders/${id}`),
  updateStatus: (id, status) => client.patch(`/fsm/v1/work-orders/${id}/status`, { status }),
  getNotes: (id) => client.get(`/fsm/v1/work-orders/${id}/notes`),
  addNote: (id, content, type = 'note') =>
    client.post(`/fsm/v1/work-orders/${id}/notes`, { content, type }),
  getMine: (params) => client.get('/fsm/v1/work-orders/mine', { params }),
  getToday: () => client.get('/fsm/v1/work-orders/today'),
};

// Dispatch
export const dispatch = {
  getBoard: (date) => client.get('/fsm/v1/dispatch/board', { params: { date } }),
  getAvailability: (date, duration) =>
    client.get('/fsm/v1/dispatch/availability', { params: { date, duration } }),
  assign: (workOrderId, technicianIds, primaryTechnicianId) =>
    client.post('/fsm/v1/dispatch/assign', {
      work_order_id: workOrderId,
      technician_ids: technicianIds,
      primary_technician_id: primaryTechnicianId,
    }),
  unassign: (workOrderId, technicianId) =>
    client.post('/fsm/v1/dispatch/unassign', {
      work_order_id: workOrderId,
      technician_id: technicianId,
    }),
  reschedule: (workOrderId, date, timeStart, timeEnd) =>
    client.post('/fsm/v1/dispatch/reschedule', {
      work_order_id: workOrderId,
      date,
      time_start: timeStart,
      time_end: timeEnd,
    }),
  getTechnicianSchedule: (technicianId, dateFrom, dateTo) =>
    client.get(`/fsm/v1/dispatch/schedule/${technicianId}`, {
      params: { date_from: dateFrom, date_to: dateTo },
    }),
  getUnscheduled: () => client.get('/fsm/v1/dispatch/unscheduled'),
};

// Inventory
export const inventory = {
  list: (params) => client.get('/fsm/v1/inventory', { params }),
  get: (id) => client.get(`/fsm/v1/inventory/${id}`),
  create: (data) => client.post('/fsm/v1/inventory', data),
  update: (id, data) => client.patch(`/fsm/v1/inventory/${id}`, data),
  getMine: () => client.get('/fsm/v1/inventory/mine'),
  getTechnicianInventory: (technicianId) =>
    client.get(`/fsm/v1/inventory/technician/${technicianId}`),
  consume: (itemId, quantity, workOrderId, notes) =>
    client.post('/fsm/v1/inventory/consume', {
      item_id: itemId,
      quantity,
      work_order_id: workOrderId,
      notes,
    }),
  transfer: (itemId, quantity, toLocationType, toLocationId) =>
    client.post('/fsm/v1/inventory/transfer', {
      item_id: itemId,
      quantity,
      to_location_type: toLocationType,
      to_location_id: toLocationId,
    }),
  getLowStock: () => client.get('/fsm/v1/inventory/low-stock'),
};

// GPS
export const gps = {
  updateLocation: (data) => client.post('/fsm/v1/gps/update', data),
  getMyHistory: (date) => client.get('/fsm/v1/gps/history', { params: { date } }),
  getAllLocations: () => client.get('/fsm/v1/gps/all'),
  getTechnicianLocation: (technicianId, includeHistory = false, date) =>
    client.get(`/fsm/v1/gps/technician/${technicianId}`, {
      params: { include_history: includeHistory, date },
    }),
  checkin: (workOrderId, latitude, longitude) =>
    client.post('/fsm/v1/gps/checkin', {
      work_order_id: workOrderId,
      latitude,
      longitude,
    }),
};

// PTT
export const ptt = {
  getMyChannels: () => client.get('/fsm/v1/ptt/channels'),
  getAllChannels: () => client.get('/fsm/v1/ptt/channels/all'),
  createChannel: (data) => client.post('/fsm/v1/ptt/channels', data),
  getConnectionInfo: () => client.get('/fsm/v1/ptt/connection'),
  createWorkOrderChannel: (workOrderId) =>
    client.post(`/fsm/v1/ptt/work-order/${workOrderId}/channel`),
};

// Technicians
export const technicians = {
  list: (params) => client.get('/fsm/v1/technicians', { params }),
  get: (id) => client.get(`/fsm/v1/technicians/${id}`),
};

// Vehicles
export const vehicles = {
  list: (params) => client.get('/fsm/v1/vehicles', { params }),
  get: (id) => client.get(`/fsm/v1/vehicles/${id}`),
};

export default client;
