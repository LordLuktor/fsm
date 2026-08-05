import { createContext, useContext, useState, useEffect } from 'react';
import { portalBranding } from '../api/portalClient';

const BrandingContext = createContext(null);

const FSM_HOST = 'fsm.steinmetz.ltd';

export function BrandingProvider({ children }) {
  const [branding, setBranding] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isCustomDomain, setIsCustomDomain] = useState(false);
  const [isPortalRoute, setIsPortalRoute] = useState(false);

  useEffect(() => {
    const hostname = window.location.hostname;
    const path = window.location.pathname;

    const onPortalRoute = path.startsWith('/portal');
    setIsPortalRoute(onPortalRoute);

    if (hostname !== FSM_HOST && hostname !== 'localhost' && hostname !== '127.0.0.1') {
      // Custom domain — resolve branding from hostname
      setIsCustomDomain(true);
      portalBranding
        .getByHostname(hostname)
        .then(({ data }) => {
          setBranding(data);
          applyBranding(data);
        })
        .catch(() => {
          setBranding(null);
        })
        .finally(() => setLoading(false));
    } else if (onPortalRoute) {
      // On FSM host but portal route — try to get slug from path
      const segments = path.split('/');
      const slug = segments[2]; // /portal/:slug/...
      if (slug && !['login', 'register', 'forgot-password'].includes(slug)) {
        portalBranding
          .getBySlug(slug)
          .then(({ data }) => {
            setBranding(data);
            applyBranding(data);
          })
          .catch(() => setBranding(null))
          .finally(() => setLoading(false));
      } else {
        setLoading(false);
      }
    } else {
      setLoading(false);
    }
  }, []);

  const applyBranding = (data) => {
    if (!data?.primary_color) return;
    const root = document.documentElement;
    root.style.setProperty('--portal-primary', data.primary_color);

    // Generate lighter/darker shades
    const hex = data.primary_color.replace('#', '');
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);

    root.style.setProperty('--portal-primary-light', `rgba(${r},${g},${b},0.1)`);
    root.style.setProperty('--portal-primary-dark', `rgb(${Math.max(0, r - 30)},${Math.max(0, g - 30)},${Math.max(0, b - 30)})`);
  };

  return (
    <BrandingContext.Provider
      value={{ branding, loading, isCustomDomain, isPortalRoute }}
    >
      {children}
    </BrandingContext.Provider>
  );
}

export function useBranding() {
  const ctx = useContext(BrandingContext);
  if (!ctx) throw new Error('useBranding must be inside BrandingProvider');
  return ctx;
}
