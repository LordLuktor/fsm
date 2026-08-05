/**
 * Parse a server datetime string (MySQL format) as UTC.
 *
 * Server sends "2026-03-26 17:20:59" which is UTC, but JS new Date()
 * interprets it as local time. This appends 'Z' to force UTC parsing.
 *
 * @param {string} dateStr - MySQL datetime like "2026-03-26 17:20:59"
 * @returns {Date}
 */
export function parseServerDate(dateStr) {
  if (!dateStr) return null;
  // Already has timezone info
  if (dateStr.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(dateStr)) {
    return new Date(dateStr);
  }
  // MySQL datetime "YYYY-MM-DD HH:MM:SS" → ISO with Z
  return new Date(dateStr.replace(' ', 'T') + 'Z');
}

/**
 * Parse a date-only string for display purposes.
 *
 * "2026-03-26" parsed with new Date() is UTC midnight, which rolls back
 * a day in western timezones. Using T12:00:00 avoids this.
 *
 * @param {string} dateStr - Date like "2026-03-26"
 * @returns {Date}
 */
export function parseScheduleDate(dateStr) {
  if (!dateStr) return null;
  // If it already has a time component, use parseServerDate
  if (dateStr.includes(' ') || dateStr.includes('T')) {
    return parseServerDate(dateStr);
  }
  // Date-only: use noon to avoid timezone day-shift
  return new Date(dateStr + 'T12:00:00');
}

/**
 * Format a schedule date for display.
 * @param {string} dateStr - "YYYY-MM-DD"
 * @returns {string} Localized date string
 */
export function formatScheduleDate(dateStr) {
  const d = parseScheduleDate(dateStr);
  return d ? d.toLocaleDateString() : '';
}

/**
 * Calculate minutes elapsed since a server timestamp.
 * @param {string} dateStr - MySQL datetime
 * @returns {number} Minutes elapsed (always >= 0)
 */
export function minutesSince(dateStr) {
  const d = parseServerDate(dateStr);
  if (!d) return 0;
  return Math.max(0, Math.round((Date.now() - d.getTime()) / 60000));
}

/**
 * Format a duration in minutes as human-readable.
 * @param {number} mins
 * @returns {string} e.g. "45m" or "2h 15m"
 */
export function formatDuration(mins) {
  if (mins < 0) mins = 0;
  if (mins < 60) return `${mins}m`;
  return `${Math.floor(mins / 60)}h ${mins % 60}m`;
}
