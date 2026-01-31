-- =============================================================================
-- FSM - Field Service Management System
-- Database Initialization Script
-- =============================================================================
-- This script runs on first container start only.
-- WordPress core tables are created automatically by WordPress.
-- Custom tables are created by plugin activation hooks.
-- =============================================================================

-- Set character encoding
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Grant permissions (redundant but explicit)
GRANT ALL PRIVILEGES ON fsm_wordpress.* TO 'fsm_user'@'%';
FLUSH PRIVILEGES;

-- =============================================================================
-- Note: The following custom tables are created by FSM plugins on activation:
-- =============================================================================
-- fsm_inventory          - Inventory items (created by fsm-inventory plugin)
-- fsm_inventory_log      - Inventory transaction log (created by fsm-inventory plugin)
-- fsm_gps_locations      - GPS location snapshots (created by fsm-gps plugin)
-- fsm_ptt_channels       - PTT channel mappings (created by fsm-ptt plugin)
-- =============================================================================
