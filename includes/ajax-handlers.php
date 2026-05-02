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
    if (!$post || 'video-gallery' !== $post->post_type) {
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
    if (!$post || 'video-gallery' !== $post->post_type) {
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