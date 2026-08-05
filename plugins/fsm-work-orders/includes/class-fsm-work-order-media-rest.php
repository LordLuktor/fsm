<?php
/**
 * FSM Work Order Media REST API
 *
 * REST endpoints for uploading, listing, and deleting media attachments
 * on work orders.
 *
 * @package FSM\WorkOrders
 * @since 1.1.0
 */

defined('ABSPATH') || exit;

class FSM_Work_Order_Media_REST {

    const NAMESPACE = 'fsm/v1';

    // Allowed MIME types
    const ALLOWED_PHOTO_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
    ];

    const ALLOWED_VIDEO_MIMES = [
        'video/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
        'video/x-matroska' => 'mkv',
    ];

    // Max file size: 2GB
    const MAX_FILE_SIZE = 2147483648;

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        // List media for a work order
        register_rest_route(self::NAMESPACE, '/work-orders/(?P<id>\d+)/media', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'list_media'],
                'permission_callback' => [__CLASS__, 'can_view_media'],
                'args' => [
                    'id' => [
                        'validate_callback' => function($param) { return is_numeric($param); },
                    ],
                ],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'upload_media'],
                'permission_callback' => [__CLASS__, 'can_upload_media'],
                'args' => [
                    'id' => [
                        'validate_callback' => function($param) { return is_numeric($param); },
                    ],
                ],
            ],
        ]);

        // Delete a single media item
        register_rest_route(
            self::NAMESPACE,
            '/work-orders/(?P<id>\d+)/media/(?P<media_id>\d+)',
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [__CLASS__, 'delete_media'],
                'permission_callback' => [__CLASS__, 'can_delete_media'],
                'args' => [
                    'id' => [
                        'validate_callback' => function($param) { return is_numeric($param); },
                    ],
                    'media_id' => [
                        'validate_callback' => function($param) { return is_numeric($param); },
                    ],
                ],
            ]
        );

        // Browse all media across work orders (dispatcher only, filter by date/type)
        register_rest_route(self::NAMESPACE, '/media', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'browse_all_media'],
            'permission_callback' => [__CLASS__, 'can_browse_all_media'],
            'args' => [
                'date_from' => [ 'type' => 'string' ],
                'date_to' => [ 'type' => 'string' ],
                'type' => [ 'type' => 'string', 'enum' => ['photo', 'video'] ],
                'limit' => [ 'type' => 'integer', 'default' => 500 ],
            ],
        ]);
    }

    /**
     * Permission: browse all media (dispatcher only).
     */
    public static function can_browse_all_media() {
        return current_user_can('fsm_view_work_orders');
    }

    /**
     * Permission: view media (same as view WO).
     */
    public static function can_view_media($request) {
        return FSM_Work_Order_REST_API::can_view_work_order($request);
    }

    /**
     * Permission: upload media (assigned tech or dispatcher).
     */
    public static function can_upload_media($request) {
        return FSM_Work_Order_REST_API::can_edit_work_order($request);
    }

    /**
     * Permission: delete media (uploader or dispatcher).
     */
    public static function can_delete_media($request) {
        // Dispatchers can always delete
        if (current_user_can('fsm_edit_work_orders')) {
            return true;
        }

        // Uploader can delete their own
        $media_id = (int) $request->get_param('media_id');
        $row = FSM_Work_Order_Media_DB::get($media_id);
        if (!$row) {
            return false;
        }

        return (int) $row['uploaded_by'] === get_current_user_id();
    }

    /**
     * GET /media
     * Browse all media across work orders with date/type filters.
     * Response groups results by work order.
     */
    public static function browse_all_media($request) {
        $rows = FSM_Work_Order_Media_DB::query([
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
            'type' => $request->get_param('type'),
            'limit' => (int) ($request->get_param('limit') ?: 500),
        ]);

        $groups = [];
        foreach ($rows as $row) {
            $wo_id = (int) $row['work_order_id'];
            if (!isset($groups[$wo_id])) {
                $wo_post = get_post($wo_id);
                if (!$wo_post || $wo_post->post_type !== 'fsm_work_order') {
                    continue;
                }

                $groups[$wo_id] = [
                    'work_order' => [
                        'id' => $wo_id,
                        'reference' => get_post_meta($wo_id, '_fsm_wo_reference', true),
                        'title' => $wo_post->post_title,
                        'status' => get_post_meta($wo_id, '_fsm_wo_status', true) ?: 'new',
                        'customer' => [
                            'name' => get_post_meta($wo_id, '_fsm_wo_customer_name', true),
                            'phone' => get_post_meta($wo_id, '_fsm_wo_customer_phone', true),
                        ],
                        'address' => [
                            'street' => get_post_meta($wo_id, '_fsm_wo_address_street', true),
                            'city' => get_post_meta($wo_id, '_fsm_wo_address_city', true),
                        ],
                        'scheduled_date' => get_post_meta($wo_id, '_fsm_wo_scheduled_date', true),
                    ],
                    'media' => [],
                ];
            }
            $groups[$wo_id]['media'][] = FSM_Work_Order_Media_DB::format_for_api($row);
        }

        return rest_ensure_response([
            'total_groups' => count($groups),
            'total_media' => array_sum(array_map(function($g) { return count($g['media']); }, $groups)),
            'groups' => array_values($groups),
        ]);
    }

    /**
     * GET /work-orders/{id}/media
     * List media for a work order.
     */
    public static function list_media($request) {
        $work_order_id = (int) $request->get_param('id');

        // Verify WO exists
        if (get_post_type($work_order_id) !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-work-orders'), 404);
        }

        $rows = FSM_Work_Order_Media_DB::get_by_work_order($work_order_id);
        $items = array_map([FSM_Work_Order_Media_DB::class, 'format_for_api'], $rows);

        return rest_ensure_response($items);
    }

    /**
     * POST /work-orders/{id}/media
     * Upload one or more files via multipart form data.
     */
    public static function upload_media($request) {
        $work_order_id = (int) $request->get_param('id');

        if (get_post_type($work_order_id) !== 'fsm_work_order') {
            return FSM_REST_API::error('not_found', __('Work order not found.', 'fsm-work-orders'), 404);
        }

        $files = $request->get_file_params();
        if (empty($files) || empty($files['file'])) {
            return FSM_REST_API::error('no_file', __('No file uploaded.', 'fsm-work-orders'), 400);
        }

        // Normalize: handle both single file and array uploads
        $file_array = $files['file'];
        $uploads = [];

        if (is_array($file_array['name'])) {
            // Multi-file upload
            $count = count($file_array['name']);
            for ($i = 0; $i < $count; $i++) {
                $uploads[] = [
                    'name' => $file_array['name'][$i],
                    'type' => $file_array['type'][$i],
                    'tmp_name' => $file_array['tmp_name'][$i],
                    'error' => $file_array['error'][$i],
                    'size' => $file_array['size'][$i],
                ];
            }
        } else {
            // Single file upload
            $uploads[] = $file_array;
        }

        $results = [];
        $errors = [];

        foreach ($uploads as $upload) {
            $result = self::process_upload($upload, $work_order_id);
            if (is_wp_error($result)) {
                $errors[] = [
                    'filename' => $upload['name'] ?? 'unknown',
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results[] = $result;
            }
        }

        if (empty($results) && !empty($errors)) {
            return FSM_REST_API::error('upload_failed', __('All uploads failed.', 'fsm-work-orders'), 400);
        }

        return rest_ensure_response([
            'uploaded' => $results,
            'errors' => $errors,
        ]);
    }

    /**
     * Process a single file upload.
     *
     * @return array|WP_Error Formatted media item or error.
     */
    private static function process_upload($file, $work_order_id) {
        // Validate size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return new WP_Error('file_too_large', __('File exceeds maximum size of 2GB.', 'fsm-work-orders'));
        }

        // Validate MIME type
        $mime_type = $file['type'];
        $is_photo = isset(self::ALLOWED_PHOTO_MIMES[$mime_type]);
        $is_video = isset(self::ALLOWED_VIDEO_MIMES[$mime_type]);

        if (!$is_photo && !$is_video) {
            return new WP_Error(
                'invalid_mime',
                sprintf(
                    __('File type %s is not allowed. Use JPG/PNG/WebP for photos or MP4/MOV/WebM for videos.', 'fsm-work-orders'),
                    $mime_type
                )
            );
        }

        $media_type = $is_photo ? 'photo' : 'video';
        $extension = $is_photo
            ? self::ALLOWED_PHOTO_MIMES[$mime_type]
            : self::ALLOWED_VIDEO_MIMES[$mime_type];

        // Build target directory
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/fsm-work-order-media/' . $work_order_id;
        $target_url_base = $upload_dir['baseurl'] . '/fsm-work-order-media/' . $work_order_id;

        if (!file_exists($target_dir)) {
            if (!wp_mkdir_p($target_dir)) {
                return new WP_Error('mkdir_failed', __('Failed to create upload directory.', 'fsm-work-orders'));
            }
        }

        // Generate unique filename
        $uuid = wp_generate_uuid4();
        $filename = $uuid . '.' . $extension;
        $target_path = $target_dir . '/' . $filename;
        $target_url = $target_url_base . '/' . $filename;

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            return new WP_Error('move_failed', __('Failed to save uploaded file.', 'fsm-work-orders'));
        }

        // Extract dimensions for photos
        $width = null;
        $height = null;
        if ($is_photo) {
            $imagesize = @getimagesize($target_path);
            if ($imagesize) {
                $width = $imagesize[0];
                $height = $imagesize[1];
            }
        }

        // Insert DB record
        $media_id = FSM_Work_Order_Media_DB::insert([
            'work_order_id' => $work_order_id,
            'media_type' => $media_type,
            'mime_type' => $mime_type,
            'original_filename' => sanitize_file_name($file['name']),
            'file_path' => $target_path,
            'file_url' => $target_url,
            'file_size' => $file['size'],
            'width' => $width,
            'height' => $height,
            'uploaded_by' => get_current_user_id(),
        ]);

        if (!$media_id) {
            @unlink($target_path);
            return new WP_Error('db_insert_failed', __('Failed to save media record.', 'fsm-work-orders'));
        }

        $row = FSM_Work_Order_Media_DB::get($media_id);
        return FSM_Work_Order_Media_DB::format_for_api($row);
    }

    /**
     * DELETE /work-orders/{id}/media/{media_id}
     */
    public static function delete_media($request) {
        $media_id = (int) $request->get_param('media_id');
        $row = FSM_Work_Order_Media_DB::get($media_id);

        if (!$row) {
            return FSM_REST_API::error('not_found', __('Media not found.', 'fsm-work-orders'), 404);
        }

        if (FSM_Work_Order_Media_DB::delete($media_id)) {
            return rest_ensure_response(['deleted' => true, 'id' => $media_id]);
        }

        return FSM_REST_API::error('delete_failed', __('Failed to delete media.', 'fsm-work-orders'), 500);
    }
}
