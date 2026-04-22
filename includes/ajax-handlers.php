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
            
            $videos[] = array(
                'id'          => $post_id,
                'title'       => get_the_title(),
                'permalink'   => get_permalink(),
                'thumbnail'   => nvg_get_video_thumbnail($post_id),
                'video_url'   => get_field('video_url', $post_id),
                'video_id'    => nvg_get_vimeo_id(get_field('video_url', $post_id)),
                'is_free'     => nvg_is_free_video($post_id),
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