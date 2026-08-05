import { useState, useCallback } from 'react';
import { gps, dispatch } from '../api/client';

// Haversine formula for distance between two GPS coordinates
function haversineDistance(lat1, lon1, lat2, lon2) {
  const R = 3959; // Earth's radius in miles
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) ** 2;
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  return R * c;
}

// Rough travel time estimate (assumes 30 mph average in service area)
function estimateTravelTime(distanceMiles) {
  return Math.round((distanceMiles / 30) * 60); // minutes
}

export function useRouteOptimization() {
  const [suggestions, setSuggestions] = useState([]);
  const [loading, setLoading] = useState(false);

  const getSuggestions = useCallback(async (workOrder, date) => {
    if (!workOrder?.address?.latitude || !workOrder?.address?.longitude) {
      setSuggestions([]);
      return [];
    }

    setLoading(true);

    try {
      // Get all technician locations and availability in parallel
      const [locRes, availRes] = await Promise.all([
        gps.getAllLocations(),
        dispatch.getAvailability(
          date || workOrder.scheduled_date || new Date().toISOString().split('T')[0],
          workOrder.estimated_duration || 60
        ),
      ]);

      const locations = locRes.data || [];
      const availability = availRes.data || [];

      // Build a map of tech locations
      const techLocations = {};
      for (const loc of locations) {
        techLocations[loc.technician_id] = {
          latitude: loc.latitude,
          longitude: loc.longitude,
          lastUpdate: loc.recorded_at,
        };
      }

      // Calculate distance and score for each available tech
      const scored = availability.map((tech) => {
        const loc = techLocations[tech.id];
        let distance = null;
        let travelTime = null;

        if (loc) {
          distance = haversineDistance(
            loc.latitude,
            loc.longitude,
            parseFloat(workOrder.address.latitude),
            parseFloat(workOrder.address.longitude)
          );
          travelTime = estimateTravelTime(distance);
        }

        // Score: lower is better
        // Factors: distance (weighted heavily), available minutes, number of current jobs
        const distanceScore = distance !== null ? distance * 10 : 999;
        const loadScore = (tech.scheduled_minutes || 0) / 60; // hours of existing work
        const score = distanceScore + loadScore * 5;

        return {
          ...tech,
          distance: distance !== null ? Math.round(distance * 10) / 10 : null,
          travelTime,
          score,
          hasLocation: !!loc,
          lastLocationUpdate: loc?.lastUpdate,
        };
      });

      // Sort by score (best first)
      scored.sort((a, b) => a.score - b.score);

      setSuggestions(scored);
      return scored;
    } catch (err) {
      console.error('Route optimization failed:', err);
      setSuggestions([]);
      return [];
    } finally {
      setLoading(false);
    }
  }, []);

  return {
    suggestions,
    loading,
    getSuggestions,
  };
}
