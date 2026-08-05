import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App';
import { AuthProvider } from './context/AuthContext';
import { CustomerAuthProvider } from './context/CustomerAuthContext';
import { BrandingProvider } from './context/BrandingContext';
import './index.css';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter>
      <BrandingProvider>
        <AuthProvider>
          <CustomerAuthProvider>
            <App />
          </CustomerAuthProvider>
        </AuthProvider>
      </BrandingProvider>
    </BrowserRouter>
  </React.StrictMode>
);
