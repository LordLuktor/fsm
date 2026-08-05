<?php
/**
 * FSM Billing Generator
 *
 * Auto-generates a draft invoice when a work order is marked completed:
 * one labor line item from the recorded time + org labor rate, plus one
 * line item per part consumed against that work order.
 *
 * @package FSM\Billing
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * FSM Billing Generator class.
 */
class FSM_Billing_Generator {

    /**
     * Handle a work order status change. Only acts on transitions into 'completed'.
     *
     * @param int    $work_order_id Work order ID.
     * @param string $new_status    New status.
     * @param string $old_status    Previous status.
     */
    public static function on_work_order_completed($work_order_id, $new_status, $old_status) {
        if ($new_status !== 'completed') {
            return;
        }

        // Don't generate a second draft if one already exists for this WO
        // (e.g. a status was corrected directly in the DB).
        if (FSM_Billing_DB::get_invoice_by_work_order($work_order_id)) {
            return;
        }

        $org_id = (int) get_post_meta($work_order_id, '_fsm_organization_id', true);
        $tax_rate = $org_id ? FSM_Organizations::get_tax_rate($org_id) : (float) get_option('fsm_tax_rate', 0);

        $invoice_id = FSM_Billing_DB::create_invoice([
            'organization_id' => $org_id,
            'work_order_id' => $work_order_id,
            'customer_name' => get_post_meta($work_order_id, '_fsm_wo_customer_name', true),
            'customer_email' => get_post_meta($work_order_id, '_fsm_wo_customer_email', true),
            'status' => 'draft',
            'tax_rate' => $tax_rate,
            'created_by' => 0,
        ]);

        if (!$invoice_id) {
            return;
        }

        $sort_order = 0;

        $labor_item = self::build_labor_line_item($work_order_id, $org_id);
        if ($labor_item) {
            $labor_item['invoice_id'] = $invoice_id;
            $labor_item['organization_id'] = $org_id;
            $labor_item['sort_order'] = $sort_order++;
            FSM_Billing_DB::add_line_item($labor_item);
        }

        foreach (self::build_parts_line_items($work_order_id) as $part_item) {
            $part_item['invoice_id'] = $invoice_id;
            $part_item['organization_id'] = $org_id;
            $part_item['sort_order'] = $sort_order++;
            FSM_Billing_DB::add_line_item($part_item);
        }

        FSM_Billing_DB::recalculate_totals($invoice_id);

        /**
         * Fires after a draft invoice is auto-generated for a completed work order.
         *
         * @param int $invoice_id    Newly created invoice ID.
         * @param int $work_order_id Source work order ID.
         */
        do_action('fsm_invoice_generated', $invoice_id, $work_order_id);
    }

    /**
     * Build the labor line item from recorded start/completion timestamps.
     *
     * @param int $work_order_id Work order ID.
     * @param int $org_id        Organization ID.
     * @return array|null Line item data (without invoice_id/organization_id/sort_order), or null if no time was recorded.
     */
    private static function build_labor_line_item($work_order_id, $org_id) {
        $started = get_post_meta($work_order_id, '_fsm_wo_started_at', true);
        $completed = get_post_meta($work_order_id, '_fsm_wo_completed_at', true);

        if (!$started || !$completed) {
            return null;
        }

        $seconds = strtotime($completed) - strtotime($started);
        if ($seconds <= 0) {
            return null;
        }

        $hours = round($seconds / 3600, 2);
        $rate = $org_id
            ? FSM_Organizations::get_labor_rate($org_id)
            : (float) get_option('fsm_labor_rate', 85);

        return [
            'type' => 'labor',
            'description' => __('Labor', 'fsm-billing'),
            'quantity' => $hours,
            'unit_price' => $rate,
            'taxable' => 1,
        ];
    }

    /**
     * Build one line item per part consumed against this work order.
     *
     * @param int $work_order_id Work order ID.
     * @return array[] List of line item data arrays.
     */
    private static function build_parts_line_items($work_order_id) {
        if (!class_exists('FSM_Inventory_DB')) {
            return [];
        }

        $transactions = FSM_Inventory_DB::get_transactions([
            'work_order_id' => $work_order_id,
            'type' => 'consume',
            'limit' => 200,
        ]);

        $items = [];
        foreach ($transactions as $txn) {
            $inventory_item = FSM_Inventory_DB::get_item((int) $txn->inventory_item_id);
            if (!$inventory_item) {
                continue;
            }

            $quantity = abs((float) $txn->quantity);
            if ($quantity <= 0) {
                continue;
            }

            $price = ($inventory_item->retail_price !== null && $inventory_item->retail_price !== '')
                ? (float) $inventory_item->retail_price
                : (float) $inventory_item->unit_cost;

            $items[] = [
                'type' => 'part',
                'description' => $inventory_item->name,
                'inventory_item_id' => $inventory_item->id,
                'quantity' => $quantity,
                'unit_price' => $price,
                'taxable' => 1,
            ];
        }

        return $items;
    }
}
