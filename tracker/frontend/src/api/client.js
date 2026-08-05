import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'https://fsm-api.steinmetz.ltd/wp-json';

const client = axios.create({
  baseURL: API_URL,
  headers: { 'Content-Type': 'application/json' },
});

// Add auth token to requests
client.interceptors.request.use((config) => {
  const token = localStorage.getItem('tracker_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Handle 401 responses
client.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('tracker_token');
      localStorage.removeItem('tracker_user');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

const ns = '/tracker/v1';

// Auth
export const auth = {
  register: (data) => client.post(`${ns}/auth/register`, data),
  login: (email, password) => client.post(`${ns}/auth/login`, { email, password }),
  getMe: () => client.get(`${ns}/me`),
};

// Family
export const family = {
  get: () => client.get(`${ns}/family`),
  getMembers: () => client.get(`${ns}/family/members`),
  updateMember: (id, data) => client.patch(`${ns}/family/members/${id}`, data),
  removeMember: (id) => client.delete(`${ns}/family/members/${id}`),
  invite: (email) => client.post(`${ns}/family/invite`, { email }),
  getInviteCode: () => client.get(`${ns}/family/invite-code`),
  regenerateInviteCode: () => client.post(`${ns}/family/invite-code/regenerate`),
  join: (code) => client.post(`${ns}/family/join/${code}`),
};

// GPS
export const gps = {
  updateLocation: (data) => client.post(`${ns}/gps/update`, data),
  getFamilyLocations: () => client.get(`${ns}/gps/family`),
  getMemberLocation: (id) => client.get(`${ns}/gps/member/${id}`),
  getMemberHistory: (memberId, dateFrom, dateTo) =>
    client.get(`${ns}/gps/history/${memberId}`, { params: { date_from: dateFrom, date_to: dateTo } }),
};

// Geofences
export const geofences = {
  list: () => client.get(`${ns}/geofences`),
  create: (data) => client.post(`${ns}/geofences`, data),
  update: (id, data) => client.patch(`${ns}/geofences/${id}`, data),
  delete: (id) => client.delete(`${ns}/geofences/${id}`),
};

// Alerts
export const alerts = {
  list: (limit = 50, offset = 0) => client.get(`${ns}/alerts`, { params: { limit, offset } }),
  getUnreadCount: () => client.get(`${ns}/alerts/unread-count`),
  markRead: (id) => client.patch(`${ns}/alerts/${id}/read`),
  markAllRead: () => client.post(`${ns}/alerts/read-all`),
};

// SOS
export const sos = {
  trigger: (data) => client.post(`${ns}/sos`, data),
};

// Sharing
export const sharing = {
  list: () => client.get(`${ns}/share`),
  create: (data) => client.post(`${ns}/share/create`, data),
  revoke: (id) => client.delete(`${ns}/share/${id}`),
  view: (token) => client.get(`${ns}/share/view/${token}`),
};

// Places
export const places = {
  list: () => client.get(`${ns}/places`),
  create: (data) => client.post(`${ns}/places`, data),
  delete: (id) => client.delete(`${ns}/places/${id}`),
};

// Subscription
export const subscription = {
  get: () => client.get(`${ns}/subscription`),
  getFeatures: () => client.get(`${ns}/subscription/features`),
  createCheckout: (plan = 'premium') => client.post(`${ns}/subscription/checkout`, { plan }),
  createPortal: () => client.post(`${ns}/subscription/portal`),
};

// PTT (Push-to-Talk)
export const ptt = {
  getConnection: () => client.get(`${ns}/ptt/connection`),
  getStatus: () => client.get(`${ns}/ptt/status`),
};

export default client;
