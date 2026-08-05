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
  register: (data) =>
    client.post('/fsm/v1/register', data),
};

// Demo endpoints
export const demo = {
  getTemplates: () =>
    client.get('/fsm/v1/demo/templates'),
  login: (template = 'plumbing', role = 'dispatcher') =>
    client.post('/fsm/v1/demo/login', { template, role }),
  logout: () =>
    client.post('/fsm/v1/demo/logout'),
  reset: () =>
    client.post('/fsm/v1/demo/reset'),
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

  // --- Optional media attachments (photos/videos for proof-of-work + content production) ---
  listMedia: (id) => client.get(`/fsm/v1/work-orders/${id}/media`),
  uploadMedia: (id, files, onProgress) => {
    const formData = new FormData();
    const fileList = Array.isArray(files) ? files : [files];
    if (fileList.length === 1) {
      formData.append('file', fileList[0]);
    } else {
      fileList.forEach((f) => formData.append('file[]', f));
    }
    return client.post(`/fsm/v1/work-orders/${id}/media`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: onProgress,
    });
  },
  deleteMedia: (id, mediaId) =>
    client.delete(`/fsm/v1/work-orders/${id}/media/${mediaId}`),
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
  getMyVehicle: () => client.get('/fsm/v1/inventory/my-vehicle'),
  getTechnicianInventory: (technicianId) =>
    client.get(`/fsm/v1/inventory/technician/${technicianId}`),
  getVehicleInventory: (vehicleId) =>
    client.get(`/fsm/v1/inventory/vehicle/${vehicleId}`),
  getAllVehiclesInventory: () =>
    client.get('/fsm/v1/inventory/vehicles'),
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
  getTransactions: (params) => client.get('/fsm/v1/inventory/transactions', { params }),
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
  getNearby: (params) => client.get('/fsm/v1/gps/nearby', { params }),
  getGroupLocations: () => client.get('/fsm/v1/gps/group'),
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

// Account
export const account = {
  changePassword: (currentPassword, newPassword) =>
    client.post('/fsm/v1/account/password', {
      current_password: currentPassword,
      new_password: newPassword,
    }),
  updateProfile: (data) => client.post('/fsm/v1/account/profile', data),
  getSessions: () => client.get('/fsm/v1/account/sessions'),
  destroyOtherSessions: () =>
    client.post('/fsm/v1/account/sessions/destroy-others'),
};

// Settings (admin)
export const settings = {
  getNotificationConfig: () => client.get('/fsm/v1/settings/notifications'),
  updateNotificationConfig: (data) =>
    client.post('/fsm/v1/settings/notifications', data),
  getCompanySettings: () => client.get('/fsm/v1/settings/company'),
  updateCompanySettings: (data) =>
    client.post('/fsm/v1/settings/company', data),
  getSmtpConfig: () => client.get('/fsm/v1/settings/smtp'),
  updateSmtpConfig: (data) => client.post('/fsm/v1/settings/smtp', data),
  testSmtp: (to) => client.post('/fsm/v1/settings/smtp/test', { to }),
};

// Team Management
export const team = {
  list: () => client.get('/fsm/v1/team'),
  get: (id) => client.get(`/fsm/v1/team/${id}`),
  add: (data) => client.post('/fsm/v1/team', data),
  update: (id, data) => client.patch(`/fsm/v1/team/${id}`, data),
  deactivate: (id) => client.delete(`/fsm/v1/team/${id}`),
};

// Work Order Types
export const workOrderTypes = {
  list: () => client.get('/fsm/v1/work-order-types/configured'),
  create: (data) => client.post('/fsm/v1/work-order-types', data),
  update: (id, data) => client.patch(`/fsm/v1/work-order-types/${id}`, data),
  delete: (id) => client.delete(`/fsm/v1/work-order-types/${id}`),
};

// Technicians
export const technicians = {
  list: (params) => client.get('/fsm/v1/technicians', { params }),
  get: (id) => client.get(`/fsm/v1/technicians/${id}`),
  create: (data) => client.post('/fsm/v1/technicians', data),
  update: (id, data) => client.patch(`/fsm/v1/technicians/${id}`, data),
};

// Vehicles
export const vehicles = {
  list: (params) => client.get('/fsm/v1/vehicles', { params }),
  get: (id) => client.get(`/fsm/v1/vehicles/${id}`),
  create: (data) => client.post('/fsm/v1/vehicles', data),
  update: (id, data) => client.patch(`/fsm/v1/vehicles/${id}`, data),
};

export default client;
