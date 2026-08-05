import { useState, useEffect, useRef, useCallback } from 'react';
import { gps, technicians } from '../api/client';
import { useAuth } from '../context/AuthContext';
import {
  Map,
  RefreshCw,
  User,
  Clock,
  Navigation,
  Maximize2,
  Minimize2,
  Layers,
  ChevronRight,
  MapPin,
  AlertTriangle,
} from 'lucide-react';
import LoadingSpinner from '../components/common/LoadingSpinner';
import PriorityBadge from '../components/common/PriorityBadge';
import { priorityBadgeHtml } from '../components/common/PriorityBadge';
import { parseServerDate, minutesSince, formatDuration } from '../utils/dates';

// Simple map component using OpenStreetMap tiles
function MapContainer({ locations, selectedTech, onSelectTech }) {
  const mapRef = useRef(null);
  const mapInstance = useRef(null);
  const markersRef = useRef({});

  useEffect(() => {
    // Dynamically load Leaflet
    if (!window.L) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(link);

      const script = document.createElement('script');
      script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
      script.onload = initMap;
      document.head.appendChild(script);
    } else {
      initMap();
    }

    return () => {
      if (mapInstance.current) {
        mapInstance.current.remove();
        mapInstance.current = null;
      }
    };
  }, []);

  const initMap = useCallback(() => {
    if (!mapRef.current || mapInstance.current) return;

    const L = window.L;

    // Default center (US)
    const defaultCenter = [39.8283, -98.5795];
    const defaultZoom = 4;

    mapInstance.current = L.map(mapRef.current).setView(defaultCenter, defaultZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
    }).addTo(mapInstance.current);

    updateMarkers();
  }, []);

  const updateMarkers = useCallback(() => {
    if (!mapInstance.current || !window.L) return;

    const L = window.L;

    // Clear existing markers
    Object.values(markersRef.current).forEach((marker) => {
      mapInstance.current.removeLayer(marker);
    });
    markersRef.current = {};

    if (locations.length === 0) return;

    const bounds = [];

    locations.forEach((loc) => {
      if (!loc.latitude || !loc.longitude) return;

      const isSelected = selectedTech === loc.technician_id;

      // Schedule-based marker color
      const scheduleColor = loc.schedule_status?.schedule_status;
      const markerBg = isSelected ? 'bg-primary-600 ring-4 ring-primary-200' :
        scheduleColor === 'green' ? 'bg-green-600' :
        scheduleColor === 'yellow' ? 'bg-yellow-500' :
        scheduleColor === 'red' ? 'bg-red-600' :
        'bg-gray-700';
      const arrowBorder = isSelected ? 'border-t-primary-600' :
        scheduleColor === 'green' ? 'border-t-green-600' :
        scheduleColor === 'yellow' ? 'border-t-yellow-500' :
        scheduleColor === 'red' ? 'border-t-red-600' :
        'border-t-gray-700';

      // Create custom icon
      const iconHtml = `
        <div class="relative">
          <div class="w-10 h-10 rounded-full ${markerBg} flex items-center justify-center text-white font-bold text-sm shadow-lg transform ${
            isSelected ? 'scale-110' : ''
          }">
            ${(loc.technician_name || 'T').charAt(0).toUpperCase()}
          </div>
          ${
            loc.heading
              ? `<div class="absolute -bottom-1 left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-6 border-l-transparent border-r-transparent ${arrowBorder}" style="transform: rotate(${loc.heading}deg)"></div>`
              : ''
          }
        </div>
      `;

      const icon = L.divIcon({
        html: iconHtml,
        className: 'custom-marker',
        iconSize: [40, 40],
        iconAnchor: [20, 20],
      });

      const marker = L.marker([loc.latitude, loc.longitude], { icon })
        .addTo(mapInstance.current)
        .on('click', () => {
          onSelectTech?.(loc.technician_id);
          // Also open popup directly for immediate feedback
          setTimeout(() => marker.openPopup(), 50);
        });

      // Add popup (HTML-escape dynamic values to prevent XSS)
      const esc = (s) => s?.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])) ?? '';

      // Calculate time on site if work order is in progress
      const wo = loc.current_work_order;
      let timeOnSite = '';
      if (wo?.started_at) {
        timeOnSite = formatDuration(minutesSince(wo.started_at));
      }

      const statusColors = {
        en_route: '#3b82f6',
        in_progress: '#f59e0b',
        assigned: '#6b7280',
        on_hold: '#ef4444',
      };

      // Schedule adherence label
      const schedStatus = loc.schedule_status;
      const schedColors = { green: '#16a34a', yellow: '#ca8a04', red: '#dc2626' };
      const schedLabels = { green: 'On Schedule', yellow: 'Running Behind', red: 'Significantly Behind' };
      const schedHtml = schedStatus?.schedule_status ? `
        <div style="display:flex;align-items:center;gap:4px;margin-top:4px;">
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${schedColors[schedStatus.schedule_status] || '#6b7280'}"></span>
          <span style="font-size:11px;color:${schedColors[schedStatus.schedule_status] || '#6b7280'};font-weight:500;">${schedLabels[schedStatus.schedule_status] || ''}</span>
          ${schedStatus.minutes_over > 0 ? `<span style="font-size:11px;color:#6b7280;">(${schedStatus.minutes_over} min)</span>` : ''}
        </div>` : '';

      // Type tags
      const typeTagsHtml = (wo?.types?.length) ? `
        <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:4px;">
          ${wo.types.map(t => `<span style="font-size:10px;padding:1px 5px;border-radius:3px;background:${t.color}20;color:${t.color};border:1px solid ${t.color}40;font-weight:500;">${esc(t.name)}</span>`).join('')}
        </div>` : '';

      const popupContent = `
        <div style="min-width:200px;font-family:system-ui,sans-serif;">
          <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
            <span style="font-weight:600;font-size:14px;">${esc(loc.technician_name) || 'Technician'}</span>
          </div>
          ${loc.updated_at ? `<div style="font-size:11px;color:#6b7280;">Updated ${esc(parseServerDate(loc.updated_at).toLocaleTimeString())}</div>` : ''}
          ${loc.speed ? `<div style="font-size:11px;color:#6b7280;">Speed: ${Math.round(loc.speed * 2.237)} mph</div>` : ''}
          ${schedHtml}
          ${wo ? `
            <div style="margin-top:6px;padding-top:6px;border-top:1px solid #e5e7eb;">
              <div style="display:flex;align-items:center;gap:4px;margin-bottom:2px;">
                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${statusColors[wo.status] || '#6b7280'}"></span>
                <span style="font-size:11px;font-weight:500;">${esc(wo.status_label)}</span>
                ${priorityBadgeHtml(wo.priority)}
                ${timeOnSite ? `<span style="font-size:11px;color:#6b7280;margin-left:auto;">${esc(timeOnSite)}</span>` : ''}
              </div>
              <div style="font-size:12px;font-weight:500;">${esc(wo.title)}</div>
              ${wo.reference ? `<div style="font-size:11px;color:#6b7280;">${esc(wo.reference)}</div>` : ''}
              ${wo.customer_name ? `<div style="font-size:11px;color:#6b7280;">${esc(wo.customer_name)}</div>` : ''}
              ${wo.address ? `<div style="font-size:11px;color:#6b7280;">${esc(wo.address)}</div>` : ''}
              ${typeTagsHtml}
            </div>
          ` : `<div style="font-size:11px;color:#9ca3af;margin-top:4px;">No active work order</div>`}
        </div>
      `;
      marker.bindPopup(popupContent, { maxWidth: 300 });

      markersRef.current[loc.technician_id] = marker;
      bounds.push([loc.latitude, loc.longitude]);
    });

    // Fit bounds if we have markers
    if (bounds.length > 0) {
      if (bounds.length === 1) {
        mapInstance.current.setView(bounds[0], 14);
      } else {
        mapInstance.current.fitBounds(bounds, { padding: [50, 50] });
      }
    }

    // Open popup for selected technician (markers get rebuilt on selection,
    // so we need to programmatically open the popup after rebuild).
    // Use setTimeout to let Leaflet finish rendering the new markers.
    if (selectedTech && markersRef.current[selectedTech]) {
      setTimeout(() => {
        markersRef.current[selectedTech]?.openPopup();
      }, 100);
    }
  }, [locations, selectedTech, onSelectTech]);

  useEffect(() => {
    updateMarkers();
  }, [locations, selectedTech, updateMarkers]);

  return (
    <div
      ref={mapRef}
      className="w-full h-full rounded-lg"
      style={{ minHeight: '400px' }}
    />
  );
}

const WO_STATUS_STYLES = {
  en_route: 'bg-blue-100 text-blue-700',
  in_progress: 'bg-amber-100 text-amber-700',
  assigned: 'bg-gray-100 text-gray-600',
  on_hold: 'bg-red-100 text-red-700',
  completed: 'bg-green-100 text-green-700',
};

function WorkOrderStatusBadge({ status, label }) {
  return (
    <span className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${WO_STATUS_STYLES[status] || 'bg-gray-100 text-gray-600'}`}>
      {label || status}
    </span>
  );
}

function TimeOnSite({ since }) {
  const [display, setDisplay] = useState('');

  useEffect(() => {
    const calc = () => formatDuration(minutesSince(since));
    setDisplay(calc());
    const interval = setInterval(() => setDisplay(calc()), 60000);
    return () => clearInterval(interval);
  }, [since]);

  return <span className="font-medium">{display}</span>;
}

function NearbyTechCard({ location, onClick }) {
  const timeSinceUpdate = location.updated_at
    ? minutesSince(location.updated_at)
    : null;

  const statusText = location.is_self ? 'You' :
    timeSinceUpdate === null ? '' :
    timeSinceUpdate < 5 ? 'Active' :
    timeSinceUpdate < 30 ? `${timeSinceUpdate}m ago` : 'Stale';

  const statusDot = location.is_self ? 'bg-primary-500' :
    timeSinceUpdate === null ? 'bg-gray-300' :
    timeSinceUpdate < 5 ? 'bg-green-500' :
    timeSinceUpdate < 30 ? 'bg-yellow-500' : 'bg-red-500';

  return (
    <div
      onClick={onClick}
      className={`p-4 rounded-lg border cursor-pointer transition-colors ${
        location.is_self
          ? 'border-primary-300 bg-primary-50'
          : 'border-gray-200 bg-white hover:bg-gray-50'
      }`}
    >
      <div className="flex items-center gap-3">
        <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
          location.is_self ? 'bg-primary-100 text-primary-600' : 'bg-gray-100 text-gray-600'
        }`}>
          <User className="h-5 w-5" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2">
            <p className="font-medium text-gray-900 text-sm truncate">
              {location.technician_name}
            </p>
            {statusText && (
              <span className="flex items-center gap-1 text-[10px] text-gray-500">
                <span className={`w-1.5 h-1.5 rounded-full ${statusDot}`} />
                {statusText}
              </span>
            )}
          </div>
          {location.phone && (
            <a
              href={`tel:${location.phone}`}
              className="text-xs text-primary-600 hover:text-primary-700"
              onClick={(e) => e.stopPropagation()}
            >
              {location.phone}
            </a>
          )}
        </div>
        {location.distance_miles !== undefined && (
          <span className="text-xs text-gray-500 whitespace-nowrap">
            {location.distance_miles} mi
          </span>
        )}
      </div>
    </div>
  );
}

const SCHEDULE_STYLES = {
  green: { dot: 'bg-green-500', text: 'text-green-700', label: 'On Schedule' },
  yellow: { dot: 'bg-yellow-500', text: 'text-yellow-700', label: 'Running Behind' },
  red: { dot: 'bg-red-500', text: 'text-red-700', label: 'Behind Schedule' },
};

function ScheduleIndicator({ scheduleStatus }) {
  if (!scheduleStatus?.schedule_status) return null;
  const style = SCHEDULE_STYLES[scheduleStatus.schedule_status];
  if (!style) return null;
  return (
    <span className={`flex items-center gap-1 text-[10px] font-medium ${style.text}`}>
      <span className={`w-2 h-2 rounded-full ${style.dot}`} />
      {style.label}
      {scheduleStatus.minutes_over > 0 && ` (${scheduleStatus.minutes_over}m)`}
    </span>
  );
}

function TypeTags({ types }) {
  if (!types?.length) return null;
  return (
    <div className="flex flex-wrap gap-1 mt-1">
      {types.map((t) => (
        <span
          key={t.id}
          className="text-[10px] px-1.5 py-0.5 rounded font-medium border"
          style={{
            backgroundColor: `${t.color}15`,
            color: t.color,
            borderColor: `${t.color}40`,
          }}
        >
          {t.name}
        </span>
      ))}
    </div>
  );
}

function TechnicianCard({ location, isSelected, onClick }) {
  const timeSinceUpdate = location.updated_at
    ? minutesSince(location.updated_at)
    : null;

  const getStatusColor = () => {
    if (!location.updated_at) return 'bg-gray-100 text-gray-600';
    if (timeSinceUpdate < 5) return 'bg-green-100 text-green-700';
    if (timeSinceUpdate < 30) return 'bg-yellow-100 text-yellow-700';
    return 'bg-red-100 text-red-700';
  };

  const getStatusText = () => {
    if (!location.updated_at) return 'No signal';
    if (timeSinceUpdate < 5) return 'Active';
    if (timeSinceUpdate < 30) return `${timeSinceUpdate}m ago`;
    return 'Stale';
  };

  const wo = location.current_work_order;
  const isUrgent = wo?.priority === 'urgent';
  const isHigh = wo?.priority === 'high';

  return (
    <div
      onClick={onClick}
      className={`p-4 rounded-lg border-2 cursor-pointer transition-all ${
        isSelected
          ? 'border-primary-500 bg-primary-50'
          : isUrgent
          ? 'border-red-300 bg-red-50/30 hover:border-red-400'
          : isHigh
          ? 'border-orange-200 hover:border-orange-300 bg-white'
          : 'border-gray-200 hover:border-gray-300 bg-white'
      }`}
    >
      <div className="flex items-center gap-3">
        <div
          className={`w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold ${
            isSelected ? 'bg-primary-600' : 'bg-gray-600'
          }`}
        >
          {(location.technician_name || 'T').charAt(0).toUpperCase()}
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-medium text-gray-900 truncate">
            {location.technician_name || 'Unknown Technician'}
          </p>
          <div className="flex items-center gap-2 mt-1">
            <span
              className={`text-xs px-2 py-0.5 rounded-full font-medium ${getStatusColor()}`}
            >
              {getStatusText()}
            </span>
            {location.speed > 0 && (
              <span className="text-xs text-gray-500 flex items-center">
                <Navigation className="h-3 w-3 mr-1" />
                {Math.round(location.speed * 2.237)} mph
              </span>
            )}
          </div>
          <ScheduleIndicator scheduleStatus={location.schedule_status} />
        </div>
        <ChevronRight className="h-5 w-5 text-gray-400" />
      </div>

      {wo ? (
        <div className="mt-3 pt-3 border-t border-gray-100">
          <div className="flex items-center justify-between mb-1">
            <div className="flex items-center gap-1.5">
              <span className="text-xs text-gray-500">Current Job</span>
              {(isUrgent || isHigh) && <PriorityBadge priority={wo.priority} size="xs" />}
            </div>
            <WorkOrderStatusBadge status={wo.status} label={wo.status_label} />
          </div>
          <p className="text-sm font-medium text-gray-900 truncate">
            {wo.title}
          </p>
          {wo.reference && (
            <p className="text-xs text-gray-500">{wo.reference}</p>
          )}
          {wo.customer_name && (
            <p className="text-xs text-gray-500 truncate">{wo.customer_name}</p>
          )}
          {wo.started_at && (
            <p className="text-xs text-gray-500 flex items-center gap-1 mt-1">
              <Clock className="h-3 w-3" />
              On site: <TimeOnSite since={wo.started_at} />
            </p>
          )}
          <TypeTags types={wo.types} />
        </div>
      ) : (
        <div className="mt-3 pt-3 border-t border-gray-100">
          <p className="text-xs text-gray-400 italic">No active work order</p>
        </div>
      )}
    </div>
  );
}

export default function GPSMapPage() {
  const { hasCapability, isTechnician, isLeadTechnician } = useAuth();
  const [locations, setLocations] = useState([]);
  const [selectedTech, setSelectedTech] = useState(null);
  const [loading, setLoading] = useState(true);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [lastUpdate, setLastUpdate] = useState(null);

  const isFullView = hasCapability('fsm_view_all_locations');
  const isGroupView = isLeadTechnician && hasCapability('fsm_view_group_locations');

  const flattenLocations = (data) =>
    (data || []).map((item) => ({
      technician_id: item.technician?.id || item.technician_id,
      technician_name: item.technician?.name || item.technician_name || 'Unknown',
      status: item.technician?.status,
      phone: item.technician?.phone,
      email: item.technician?.email,
      ...item.location,
      ...(item.location ? {} : item),
      updated_at: item.location?.recorded_at || item.recorded_at || item.updated_at,
      current_work_order: item.current_work_order || null,
      schedule_status: item.schedule_status || null,
    }));

  const loadLocations = useCallback(async () => {
    try {
      if (isFullView) {
        // Dispatchers/admins see all technicians
        const { data } = await gps.getAllLocations();
        setLocations(flattenLocations(data));
      } else if (isGroupView) {
        // Lead technicians see their group with full info
        const { data } = await gps.getGroupLocations();
        setLocations(flattenLocations(data));
      } else {
        // Technicians see nearby techs only (limited info)
        const { data } = await gps.getNearby({ limit: 10, radius: 25 });
        // Transform nearby data to match the location format the map expects
        const mapped = (data.nearby || []).map((n) => ({
          technician_id: n.technician_id,
          technician_name: n.name,
          latitude: n.latitude,
          longitude: n.longitude,
          phone: n.phone,
          distance_miles: n.distance_miles,
          updated_at: n.recorded_at,
        }));
        // Add own location as first item
        if (data.my_location) {
          mapped.unshift({
            technician_id: 'me',
            technician_name: 'You',
            latitude: data.my_location.latitude,
            longitude: data.my_location.longitude,
            is_self: true,
          });
        }
        setLocations(mapped);
      }
      setLastUpdate(new Date());
    } catch (err) {
      console.error('Failed to load locations:', err);
    } finally {
      setLoading(false);
    }
  }, [isFullView]);

  useEffect(() => {
    loadLocations();

    let interval;
    if (autoRefresh) {
      interval = setInterval(loadLocations, 30000);
    }

    return () => {
      if (interval) clearInterval(interval);
    };
  }, [autoRefresh, loadLocations]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="medium" />
      </div>
    );
  }

  return (
    <div className={`space-y-6 ${isFullscreen ? 'fixed inset-0 z-50 bg-gray-100 p-6' : ''}`}>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 flex items-center">
            <Map className="h-6 w-6 mr-2" />
            {isFullView ? 'GPS Fleet Tracking' : isGroupView ? 'My Team Locations' : 'Nearby Technicians'}
          </h1>
          <div className="flex items-center gap-4 mt-1">
            {lastUpdate && (
              <span className="text-sm text-gray-500">
                Last updated: {lastUpdate.toLocaleTimeString()}
              </span>
            )}
            {isFullView && (
              <div className="flex items-center gap-3 text-xs text-gray-500">
                <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-green-600" />On Schedule</span>
                <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-yellow-500" />Behind</span>
                <span className="flex items-center gap-1"><span className="w-2 h-2 rounded-full bg-red-600" />Significantly Behind</span>
              </div>
            )}
          </div>
        </div>

        <div className="flex items-center gap-2">
          {/* Auto-refresh toggle */}
          <button
            onClick={() => setAutoRefresh(!autoRefresh)}
            className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
              autoRefresh
                ? 'bg-green-100 text-green-700'
                : 'bg-gray-100 text-gray-600'
            }`}
          >
            <Clock className="inline h-4 w-4 mr-1.5" />
            Auto-refresh {autoRefresh ? 'ON' : 'OFF'}
          </button>

          {/* Manual refresh */}
          <button
            onClick={loadLocations}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            title="Refresh now"
          >
            <RefreshCw className="h-5 w-5 text-gray-600" />
          </button>

          {/* Fullscreen toggle */}
          <button
            onClick={() => setIsFullscreen(!isFullscreen)}
            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
            title={isFullscreen ? 'Exit fullscreen' : 'Fullscreen'}
          >
            {isFullscreen ? (
              <Minimize2 className="h-5 w-5 text-gray-600" />
            ) : (
              <Maximize2 className="h-5 w-5 text-gray-600" />
            )}
          </button>
        </div>
      </div>

      {/* Main content */}
      <div className={`grid grid-cols-1 lg:grid-cols-4 gap-6 ${isFullscreen ? 'h-[calc(100%-100px)]' : ''}`}>
        {/* Technician list */}
        <div className={`bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden ${isFullscreen ? 'h-full' : ''}`}>
          <div className="px-4 py-3 border-b border-gray-100 bg-gray-50">
            <h2 className="font-medium text-gray-900 flex items-center gap-2">
              <User className="h-4 w-4" />
              {isFullView ? 'Technicians' : isGroupView ? 'My Team' : 'Nearby'} ({locations.length})
            </h2>
          </div>
          <div className={`p-3 space-y-3 overflow-y-auto ${isFullscreen ? 'h-[calc(100%-52px)]' : 'max-h-[500px]'}`}>
            {locations.length === 0 ? (
              <div className="text-center py-8 text-gray-500">
                <MapPin className="h-12 w-12 mx-auto text-gray-300 mb-3" />
                <p>No technician locations available</p>
              </div>
            ) : (
              locations.map((loc) =>
                (isFullView || isGroupView) ? (
                  <TechnicianCard
                    key={loc.technician_id}
                    location={loc}
                    isSelected={selectedTech === loc.technician_id}
                    onClick={() =>
                      setSelectedTech(
                        selectedTech === loc.technician_id ? null : loc.technician_id
                      )
                    }
                  />
                ) : (
                  <NearbyTechCard
                    key={loc.technician_id}
                    location={loc}
                    onClick={() =>
                      setSelectedTech(
                        selectedTech === loc.technician_id ? null : loc.technician_id
                      )
                    }
                  />
                )
              )
            )}
          </div>
        </div>

        {/* Map */}
        <div className={`lg:col-span-3 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden ${isFullscreen ? 'h-full' : 'min-h-[500px]'}`}>
          <MapContainer
            locations={locations}
            selectedTech={selectedTech}
            onSelectTech={setSelectedTech}
          />
        </div>
      </div>

      {/* CSS for custom markers */}
      <style>{`
        .custom-marker {
          background: transparent !important;
          border: none !important;
        }
        .leaflet-popup-content-wrapper {
          border-radius: 8px;
        }
      `}</style>
    </div>
  );
}
