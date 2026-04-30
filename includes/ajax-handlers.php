<?php
/**
 * AJAX Handler Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filter videos by category
 */
add_action('wp_ajax_nvg_filter_videos', 'nvg_ajax_filter_videos');
add_action('wp_ajax_nopriv_nvg_filter_videos', 'nvg_ajax_filter_videos');

function nvg_ajax_filter_videos() {
    check_ajax_referer('nvg_nonce', 'nonce');
    
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $is_free = isset($_POST['is_free']) ? sanitize_text_field($_POST['is_free']) : '';
    
    $args = array(
        'post_type'      => 'video-gallery',
        'posts_per_page' => 100,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    
    // Filter by category
    if (!empty($category) && $category !== 'all') {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'video-category',
                'field'    => 'slug',
                'terms'    => $category,
            ),
        );
    }
    
    $query = new WP_Query($args);
    
    $videos = array();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // Filter by is_free
            if ($is_free === 'yes') {
                if (!nvg_is_free_video($post_id)) {
                    continue;
                }
            }

            $video_url = get_field('video_url', $post_id);
            $can_watch = nvg_user_can_watch_video($post_id);
            
            $videos[] = array(
                'id'          => $post_id,
                'title'       => get_the_title(),
                'permalink'   => get_permalink(),
                'thumbnail'   => nvg_get_video_thumbnail($post_id),
                'video_url'   => $can_watch ? $video_url : '',
                'video_id'    => $can_watch ? nvg_get_vimeo_id($video_url) : '',
                'is_free'     => nvg_is_free_video($post_id),
                'can_watch'   => $can_watch,
                'description' => get_field('short_description', $post_id),
            );
        }
    }
    
    wp_reset_postdata();
    
    wp_send_json_success($videos);
}

/**
 * Load more videos
 */
add_action('wp_ajax_nvg_load_more', 'nvg_ajax_load_more');
add_action('wp_ajax_nopriv_nvg_load_more', 'nvg_ajax_load_more');

function nvg_ajax_load_more() {
    check_ajax_referer('nvg_nonce', 'nonce');
    
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    
    $args = array(
        'post_type'      => 'video-gallery',
        'posts_per_page' => 12,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    
    if (!empty($category) && $category !== 'all') {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'video-category',
                'field'    => 'slug',
                'terms'    => $category,
            ),
        );
    }
    
    $query = new WP_Query($args);
    
    ob_start();
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            nvg_render_video_card(get_the_ID());
        }
    }
    
    $html = ob_get_clean();
    wp_reset_postdata();
    
    wp_send_json_success(array(
        'html'      => $html,
        'has_more'  => $query->max_num_pages > $page,
    ));
}

/**
 * Get video data for player
 */
add_action('wp_ajax_nvg_get_video_data', 'nvg_ajax_get_video_data');
add_action('wp_ajax_nopriv_nvg_get_video_data', 'nvg_ajax_get_video_data');

function nvg_ajax_get_video_data() {
    check_ajax_referer('nvg_nonce', 'nonce');
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }
    
    $post = get_post($post_id);
    
    if (!$post || $post->post_type !== 'video-gallery') {
        wp_send_json_error('Invalid video');
    }

    if (!nvg_user_can_watch_video($post_id)) {
        wp_send_json_error(array(
            'message' => wp_strip_all_tags(nvg_get_video_restriction_message($post_id)),
        ));
    }
    
    $video_url = get_field('video_url', $post_id);
    $video_id = nvg_get_vimeo_id($video_url);
    
    $categories = get_the_terms($post_id, 'video-category');
    $category_name = !empty($categories) ? $categories[0]->name : '';
    
    wp_send_json_success(array(
        'id'          => $post_id,
        'title'       => get_the_title($post_id),
        'description' => get_field('short_description', $post_id),
        'video_id'    => $video_id,
        'video_url'   => $video_url,
        'category'    => $category_name,
        'permalink'   => get_permalink($post_id),
    ));
}

/**
 * Mark course lesson as complete
 */
// nopriv hook removed: progress requires an authenticated user with course access.
add_action('wp_ajax_nvg_mark_lesson_complete', 'nvg_ajax_mark_lesson_complete');

function nvg_ajax_mark_lesson_complete() {
    check_ajax_referer('nvg_nonce', 'nonce');

    // Must be logged in.
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error(array('message' => 'You must be logged in to track progress.'));
    }

    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $lesson_id = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;

    if (!$course_id || !$lesson_id) {
        wp_send_json_error(array('message' => 'Invalid course or lesson ID.'));
    }

    // Verify the course post exists and is published.
    $course = get_post($course_id);
    if (!$course || $course->post_type !== 'course' || $course->post_status !== 'publish') {
        wp_send_json_error(array('message' => 'Course not found.'));
    }

    // Verify the lesson post exists and is published.
    $lesson = get_post($lesson_id);
    if (!$lesson || $lesson->post_type !== 'lesson' || $lesson->post_status !== 'publish') {
        wp_send_json_error(array('message' => 'Lesson not found.'));
    }

    // Verify the user is entitled to access this course.
    if (!nvg_user_can_access_course($course_id)) {
        wp_send_json_error(array('message' => 'You do not have access to this course.'));
    }

    // Verify the lesson actually belongs to this course (prevents cross-course injection).
    $course_lesson_ids = nvg_get_course_lessons($course_id);
    if (!in_array($lesson_id, $course_lesson_ids, true)) {
        wp_send_json_error(array('message' => 'This lesson does not belong to the specified course.'));
    }

    nvg_mark_lesson_complete($course_id, $lesson_id);

    $progress  = nvg_get_course_progress($course_id);
    $completed = nvg_get_completed_lessons($course_id);

    wp_send_json_success(array(
        'progress'  => $progress,
        'completed' => $completed,
        'message'   => __('Lesson marked as complete!', 'netflix-video-gallery'),
    ));
}

/**
 * Search courses for archive page
 */
add_action('wp_ajax_nvg_search_courses', 'nvg_ajax_search_courses');
add_action('wp_ajax_nopriv_nvg_search_courses', 'nvg_ajax_search_courses');

function nvg_ajax_search_courses() {
    check_ajax_referer('nvg_nonce', 'nonce');

    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;

    $args = array(
        'post_type'      => 'course',
        'post_status'    => 'publish',
        'posts_per_page' => 12,
        'paged'          => max(1, $paged),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if ('' !== $search) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);

    ob_start();
    if ($query->have_posts()) {
        $is_logged_in = is_user_logged_in();
        while ($query->have_posts()) {
            $query->the_post();
            nvg_render_course_card(get_the_ID(), $is_logged_in);
        }
    }
    $html = ob_get_clean();

    $pagination_html = '';
    if ($query->max_num_pages > 1) {
        $links = paginate_links(array(
            'current'   => max(1, $paged),
            'total'     => $query->max_num_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'type'      => 'array',
            'format'    => '?paged=%#%',
        ));

        if ($links) {
            $pagination_html = implode("\n", array_map('wp_kses_post', $links));
        }
    }

    wp_reset_postdata();

    wp_send_json_success(array(
        'html'       => $html,
        'pagination' => $pagination_html,
        'found'      => (int) $query->found_posts,
    ));
}

/**
 * Get individual purchase offer data for popup
 */
add_action('wp_ajax_nvg_get_purchase_offer', 'nvg_ajax_get_purchase_offer');
add_action('wp_ajax_nopriv_nvg_get_purchase_offer', 'nvg_ajax_get_purchase_offer');

function nvg_ajax_get_purchase_offer() {
    check_ajax_referer('nvg_nonce', 'nonce');

    if (!function_exists('wc_get_checkout_url')) {
        wp_send_json_error(array('message' => 'WooCommerce is required.'));
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error(array('message' => 'Invalid content ID.'));
    }

    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, array('video-gallery', 'course'), true)) {
        wp_send_json_error(array('message' => 'Invalid content type.'));
    }

    $enabled = nvg_is_individual_purchase_enabled($post_id);
    $price = nvg_get_individual_price($post_id);
    $product_id = nvg_get_individual_product_id_for_post($post_id);

    if (!$enabled || $price <= 0 || !$product_id) {
        wp_send_json_success(array(
            'enabled' => false,
        ));
    }

    $base_add_to_cart_url = get_permalink($post_id);
    if (!$base_add_to_cart_url) {
        $base_add_to_cart_url = home_url('/');
    }

    $add_to_cart_url = add_query_arg(
        array(
            'add-to-cart'    => $product_id,
            'nvg_content_id' => $post_id,
        ),
        $base_add_to_cart_url
    );

    $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    $price_html = $currency_symbol . wc_format_decimal($price, 2);

    wp_send_json_success(array(
        'enabled'      => true,
        'post_id'      => $post_id,
        'product_id'   => $product_id,
        'post_type'    => $post->post_type,
        'title'        => get_the_title($post_id),
        'price'        => $price,
        'price_html'   => $price_html,
        'add_to_cart_url' => $add_to_cart_url,
        // Backward compatibility for older JS expecting checkout_url.
        'checkout_url' => $add_to_cart_url,
    ));
}

/**
 * Add individual purchase item to cart via AJAX (no redirect)
 */
add_action('wp_ajax_nvg_add_purchase_to_cart', 'nvg_ajax_add_purchase_to_cart');
add_action('wp_ajax_nopriv_nvg_add_purchase_to_cart', 'nvg_ajax_add_purchase_to_cart');

function nvg_ajax_add_purchase_to_cart() {
    check_ajax_referer('nvg_nonce', 'nonce');

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json_error(array('message' => 'WooCommerce cart is unavailable.'));
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id) {
        wp_send_json_error(array('message' => 'Invalid content ID.'));
    }

    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, array('video-gallery', 'course'), true)) {
        wp_send_json_error(array('message' => 'Invalid content type.'));
    }

    if (!nvg_is_individual_purchase_enabled($post_id)) {
        wp_send_json_error(array('message' => 'Individual purchase is disabled for this content.'));
    }

    $price = nvg_get_individual_price($post_id);
    if ($price <= 0) {
        wp_send_json_error(array('message' => 'Invalid price for this content.'));
    }

    $product_id = nvg_get_individual_product_id_for_post($post_id);
    if (!$product_id || !nvg_is_individual_carrier_product($product_id)) {
        wp_send_json_error(array('message' => 'Invalid product mapping for this content.'));
    }

    $cart_item_data = array(
        'nvg_content_id'       => $post_id,
        'nvg_content_type'     => $post->post_type,
        'nvg_content_title'    => $post->post_title,
        'nvg_individual_price' => $price,
        'nvg_unique_key'       => md5($post_id . '|' . microtime(true)),
    );

    $added_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
    if (!$added_key) {
        wp_send_json_error(array('message' => 'Could not add this item to cart.'));
    }

    wp_send_json_success(array(
        'message'    => __('Item added to cart.', 'netflix-video-gallery'),
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_url'   => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
    ));
}