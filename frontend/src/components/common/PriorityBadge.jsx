import { AlertTriangle } from 'lucide-react';

const PRIORITY_CONFIG = {
  low: {
    bg: 'bg-gray-100',
    text: 'text-gray-600',
    border: 'border-gray-200',
    ring: '',
    animate: '',
    showIcon: false,
    label: 'Low',
  },
  normal: {
    bg: 'bg-blue-50',
    text: 'text-blue-700',
    border: 'border-blue-200',
    ring: '',
    animate: '',
    showIcon: false,
    label: 'Normal',
  },
  high: {
    bg: 'bg-orange-100',
    text: 'text-orange-700',
    border: 'border-orange-300',
    ring: 'ring-1 ring-orange-300',
    animate: '',
    showIcon: true,
    label: 'High',
  },
  urgent: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    border: 'border-red-300',
    ring: 'ring-2 ring-red-400',
    animate: 'animate-pulse',
    showIcon: true,
    label: 'Urgent',
  },
};

const SIZE_CLASSES = {
  xs: 'text-[10px] px-1.5 py-0.5 gap-0.5',
  sm: 'text-xs px-2 py-0.5 gap-1',
  lg: 'text-sm px-3 py-1 gap-1.5',
};

const ICON_SIZES = {
  xs: 'h-2.5 w-2.5',
  sm: 'h-3 w-3',
  lg: 'h-4 w-4',
};

export default function PriorityBadge({ priority, size = 'sm', showLabel = true }) {
  const config = PRIORITY_CONFIG[priority] || PRIORITY_CONFIG.normal;

  // Don't render for normal priority in xs size (too noisy in lists)
  if (priority === 'normal' && size === 'xs' && showLabel) {
    return null;
  }

  return (
    <span
      className={`inline-flex items-center font-semibold rounded-full whitespace-nowrap ${config.bg} ${config.text} ${config.border} ${config.ring} ${config.animate} ${SIZE_CLASSES[size]} border`}
    >
      {config.showIcon && (
        <AlertTriangle className={ICON_SIZES[size]} />
      )}
      {showLabel && config.label}
    </span>
  );
}

/**
 * Inline HTML version for use in Leaflet popups (raw HTML, not React).
 */
export function priorityBadgeHtml(priority) {
  if (!priority || priority === 'normal' || priority === 'low') return '';

  const styles = {
    urgent: 'background:#fef2f2;border:2px solid #ef4444;color:#dc2626;font-weight:700;',
    high: 'background:#fff7ed;border:1px solid #f97316;color:#ea580c;font-weight:600;',
  };

  const labels = { urgent: 'URGENT', high: 'HIGH' };

  const style = styles[priority];
  if (!style) return '';

  return `<span style="${style}border-radius:4px;padding:1px 6px;font-size:10px;display:inline-block;margin-left:4px;">${labels[priority]}</span>`;
}
