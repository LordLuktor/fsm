<?php
/**
 * FSM Work Order Notifications
 *
 * Sends email notifications to customers when work order status changes.
 * Hooks into the fsm_work_order_status_changed action fired by the status API.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FSM_Work_Order_Notifications {

    public function __construct() {
        add_action( 'fsm_work_order_status_changed', array( $this, 'handle_status_change' ), 10, 3 );
    }

    /**
     * Handle work order status change notification.
     *
     * @param int    $work_order_id
     * @param string $new_status
     * @param string $old_status
     */
    public function handle_status_change( $work_order_id, $new_status, $old_status ) {
        // Check notification settings
        $settings = get_option( 'fsm_notification_settings', array() );
        $enabled  = $settings['enabled'] ?? true;

        if ( ! $enabled ) {
            return;
        }

        // Check if this specific status transition should send email
        $status_key = 'send_on_' . $new_status;
        if ( isset( $settings[ $status_key ] ) && ! $settings[ $status_key ] ) {
            return;
        }

        $customer_email = get_post_meta( $work_order_id, '_fsm_wo_customer_email', true );
        if ( empty( $customer_email ) || ! is_email( $customer_email ) ) {
            return;
        }

        $customer_name  = get_post_meta( $work_order_id, '_fsm_wo_customer_name', true );
        $reference      = get_post_meta( $work_order_id, '_fsm_wo_reference', true );
        $title          = get_the_title( $work_order_id );
        $scheduled_date = get_post_meta( $work_order_id, '_fsm_wo_scheduled_date', true );
        $scheduled_time = get_post_meta( $work_order_id, '_fsm_wo_scheduled_time_start', true );

        $subject = '';
        $message = '';

        switch ( $new_status ) {
            case 'assigned':
                $tech_id   = get_post_meta( $work_order_id, '_fsm_wo_primary_technician_id', true );
                $tech_name = $tech_id ? get_the_title( $tech_id ) : 'a technician';

                $subject = sprintf( '[%s] Technician Assigned — %s', $reference, $title );
                $message = $this->build_email(
                    $customer_name,
                    'Your service request has been assigned.',
                    sprintf(
                        '%s has been assigned to your job "%s".',
                        esc_html( $tech_name ),
                        esc_html( $title )
                    ),
                    $scheduled_date
                        ? sprintf( 'Scheduled for %s%s.', date( 'l, F j, Y', strtotime( $scheduled_date ) ), $scheduled_time ? ' at ' . $scheduled_time : '' )
                        : 'We will notify you when the technician is on the way.'
                );
                break;

            case 'en_route':
                $subject = sprintf( '[%s] Technician On the Way — %s', $reference, $title );
                $message = $this->build_email(
                    $customer_name,
                    'Your technician is on the way!',
                    sprintf( 'A technician is now heading to your location for "%s".', esc_html( $title ) ),
                    'Please ensure access to the service area is available.'
                );
                break;

            case 'in_progress':
                $subject = sprintf( '[%s] Work In Progress — %s', $reference, $title );
                $message = $this->build_email(
                    $customer_name,
                    'Work has started on your service request.',
                    sprintf( 'The technician has begun work on "%s".', esc_html( $title ) ),
                    'We will notify you when the job is complete.'
                );
                break;

            case 'completed':
                $subject = sprintf( '[%s] Service Completed — %s', $reference, $title );
                $message = $this->build_email(
                    $customer_name,
                    'Your service has been completed!',
                    sprintf( '"%s" has been marked as completed.', esc_html( $title ) ),
                    'Thank you for choosing our services. If you have any questions about the work performed, please don\'t hesitate to contact us.'
                );
                break;

            case 'on_hold':
                $subject = sprintf( '[%s] Service On Hold — %s', $reference, $title );
                $message = $this->build_email(
                    $customer_name,
                    'Your service request has been placed on hold.',
                    sprintf( '"%s" is temporarily on hold.', esc_html( $title ) ),
                    'We will resume work as soon as possible and keep you updated.'
                );
                break;

            case 'cancelled':
                $subject = sprintf( '[%s] Service Cancelled — %s', $reference, $title );
                $message = $this->build_email(
                    $customer_name,
                    'Your service request has been cancelled.',
                    sprintf( '"%s" has been cancelled.', esc_html( $title ) ),
                    'If this was unexpected, please contact us immediately.'
                );
                break;

            default:
                return;
        }

        $from_name  = ! empty( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );
        $from_email = ! empty( $settings['from_email'] ) ? $settings['from_email'] : 'noreply@' . wp_parse_url( home_url(), PHP_URL_HOST );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', sanitize_text_field( $from_name ), sanitize_email( $from_email ) ),
        );

        if ( ! empty( $settings['reply_to'] ) && is_email( $settings['reply_to'] ) ) {
            $headers[] = 'Reply-To: ' . sanitize_email( $settings['reply_to'] );
        }

        wp_mail( sanitize_email( $customer_email ), $subject, $message, $headers );
    }

    /**
     * Build a simple HTML email template.
     */
    private function build_email( $customer_name, $headline, $body, $footer_note ) {
        $name = ! empty( $customer_name ) ? esc_html( $customer_name ) : 'Valued Customer';

        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"></head>
            <body style="font-family: system-ui, -apple-system, sans-serif; color: #1f2937; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: #2563eb; color: white; padding: 20px 24px; border-radius: 12px 12px 0 0;">
                    <h1 style="margin: 0; font-size: 18px;">%s</h1>
                </div>
                <div style="background: #ffffff; border: 1px solid #e5e7eb; border-top: none; padding: 24px; border-radius: 0 0 12px 12px;">
                    <p>Hi %s,</p>
                    <p>%s</p>
                    <p style="color: #6b7280; font-size: 14px;">%s</p>
                    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
                    <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                        This is an automated notification from FSM — Field Service Management.
                    </p>
                </div>
            </body>
            </html>',
            $headline,
            $name,
            $body,
            $footer_note
        );
    }
}

new FSM_Work_Order_Notifications();
