import axios from 'axios';

const API_URL = import.meta.env.VITE_API_URL || 'https://fsm-api.steinmetz.ltd/wp-json';

const portalClient = axios.create({
  baseURL: API_URL,
  headers: { 'Content-Type': 'application/json' },
});

portalClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('fsm_customer_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

portalClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('fsm_customer_token');
      localStorage.removeItem('fsm_customer_user');
      // Redirect to portal login (preserve current path context)
      const path = window.location.pathname;
      if (path.startsWith('/portal/')) {
        const slug = path.split('/')[2];
        window.location.href = `/portal/${slug}/login`;
      } else {
        window.location.href = '/portal/login';
      }
    }
    return Promise.reject(error);
  }
);

export const portalAuth = {
  register: (data) => portalClient.post('/fsm/v1/customer-portal/register', data),
  login: (email, password) =>
    portalClient.post('/fsm/v1/customer-portal/login', { email, password }),
  forgotPassword: (email) =>
    portalClient.post('/fsm/v1/customer-portal/forgot-password', { email }),
  resetPassword: (login, key, password) =>
    portalClient.post('/fsm/v1/customer-portal/reset-password', { login, key, password }),
};

export const portalProfile = {
  get: () => portalClient.get('/fsm/v1/customer-portal/me'),
  update: (data) => portalClient.post('/fsm/v1/customer-portal/me', data),
};

export const portalWorkOrders = {
  list: (params) => portalClient.get('/fsm/v1/customer-portal/work-orders', { params }),
  get: (id) => portalClient.get(`/fsm/v1/customer-portal/work-orders/${id}`),
};

export const portalInvoices = {
  list: (params) => portalClient.get('/fsm/v1/customer-portal/invoices', { params }),
  get: (id) => portalClient.get(`/fsm/v1/customer-portal/invoices/${id}`),
};

export const portalService = {
  request: (data) => portalClient.post('/fsm/v1/customer-portal/service-request', data),
};

export const portalBranding = {
  getByHostname: (hostname) =>
    axios.get(`${API_URL}/fsm/v1/portal-branding`, { params: { hostname } }),
  getBySlug: (slug) =>
    axios.get(`${API_URL}/fsm/v1/portal-branding/slug/${slug}`),
};

export default portalClient;
