<?php
/**
 * Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extract Vimeo ID from URL
 */
function nvg_get_vimeo_id($url) {
    if (empty($url)) {
        return false;
    }
    
    // Pattern to match Vimeo URLs
    $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:player\.)?vimeo\.com\/(?:video\/|channels\/[\w]+\/|groups\/[\w]+\/videos\/|album\/\d+\/video\/)?(\d+)(?:$|\/|\?)/';
    
    preg_match($pattern, $url, $matches);
    
    return isset($matches[1]) ? $matches[1] : false;
}

/**
 * Get Vimeo embed URL
 */
function nvg_get_vimeo_embed_url($url, $autoplay = false) {
    $video_id = nvg_get_vimeo_id($url);
    
    if (!$video_id) {
        return false;
    }
    
    $params = array(
        'title' => 0,
        'byline' => 0,
        'portrait' => 0,
    );
    
    if ($autoplay) {
        $params['autoplay'] = 1;
        $params['muted'] = 1;
    }
    
    return 'https://player.vimeo.com/video/' . $video_id . '?' . http_build_query($params);
}

/**
 * Get video thumbnail
 */
function nvg_get_video_thumbnail($post_id, $size = 'large') {
    if (has_post_thumbnail($post_id)) {
        return get_the_post_thumbnail_url($post_id, $size);
    }
    
    // Fallback to Vimeo thumbnail
    $video_url = get_field('video_url', $post_id);
    $video_id = nvg_get_vimeo_id($video_url);
    
    if ($video_id) {
        $vimeo_data = nvg_get_vimeo_thumbnail($video_id);
        return $vimeo_data ? $vimeo_data : NVG_PLUGIN_URL . 'assets/images/placeholder.jpg';
    }
    
    return NVG_PLUGIN_URL . 'assets/images/placeholder.jpg';
}

/**
 * Get Vimeo thumbnail via API
 */
function nvg_get_vimeo_thumbnail($video_id) {
    $transient_key = 'vimeo_thumb_' . $video_id;
    $cached = get_transient($transient_key);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $oembed_url = 'https://vimeo.com/api/oembed.json?url=' . rawurlencode( 'https://vimeo.com/' . $video_id );
    $response   = wp_remote_get( $oembed_url );

    if (is_wp_error($response)) {
        return false;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if (!empty($data['thumbnail_url'])) {
        $thumbnail = $data['thumbnail_url'];
        set_transient($transient_key, $thumbnail, WEEK_IN_SECONDS);
        return $thumbnail;
    }
    
    return false;
}

/**
 * Check if video is free
 */
function nvg_is_free_video($post_id) {
    $is_free = get_field('is_free', $post_id);
    return ($is_free === 'Yes' || $is_free === true || $is_free === '1');
}

/**
 * Check if video is featured
 */
function nvg_is_featured_video($post_id) {
    $featured = get_field('featured', $post_id);
    return ($featured === 'Yes' || $featured === true || $featured === '1');
}

/**
 * Check whether current user can watch a given video
 */
function nvg_user_can_watch_video($post_id) {
    $post_id = absint($post_id);

    if (!$post_id) {
        return false;
    }

    // Free videos should always remain publicly accessible.
    if (nvg_is_free_video($post_id)) {
        return true;
    }

    if (!function_exists('wc_memberships_is_post_content_restricted') || !function_exists('wc_memberships_user_can')) {
        return true;
    }

    if (!wc_memberships_is_post_content_restricted($post_id)) {
        return true;
    }

    return wc_memberships_user_can(get_current_user_id(), 'view', array('post' => $post_id));
}

/**
 * Get the restriction message shown when a user cannot watch a paid video
 */
function nvg_get_video_restriction_message($post_id) {
    $post_id = absint($post_id);

    if (!$post_id) {
        return esc_html__('This video is restricted.', 'netflix-video-gallery');
    }

    if (function_exists('wc_memberships_get_restricted_content_message') && function_exists('wc_memberships_is_post_content_restricted')) {
        if (wc_memberships_is_post_content_restricted($post_id)) {
            $message = wc_memberships_get_restricted_content_message(get_post($post_id));
            if (!empty($message)) {
                return $message;
            }
        }
    }

    if (!is_user_logged_in()) {
        $login_url = wp_login_url(get_permalink($post_id));

        return sprintf(
            '<p>%s <a href="%s">%s</a></p>',
            esc_html__('This video is for members only.', 'netflix-video-gallery'),
            esc_url($login_url),
            esc_html__('Log in', 'netflix-video-gallery')
        );
    }

    return esc_html__('This video is available to members with access to this content.', 'netflix-video-gallery');
}

/**
 * Get related videos
 */
function nvg_get_related_videos($post_id, $limit = 6) {
    $terms = get_the_terms($post_id, 'video-category');
    
    if (!$terms || is_wp_error($terms)) {
        return array();
    }
    
    $term_ids = wp_list_pluck($terms, 'term_id');
    
    $args = array(
        'post_type'      => 'video-gallery',
        'posts_per_page' => $limit,
        'post__not_in'   => array($post_id),
        'tax_query'      => array(
            array(
                'taxonomy' => 'video-category',
                'field'    => 'term_id',
                'terms'    => $term_ids,
            ),
        ),
    );
    
    return new WP_Query($args);
}

/**
 * Render video card
 */
function nvg_render_video_card($post_id, $lazy = true) {
    $video_url = get_field('video_url', $post_id);
    $video_id = nvg_get_vimeo_id($video_url);
    $thumbnail = nvg_get_video_thumbnail($post_id);
    $is_free = nvg_is_free_video($post_id);
    $short_desc = get_field('short_description', $post_id);
    $permalink = get_permalink($post_id);
    
    $img_attrs = $lazy ? 'loading="lazy"' : '';
    ?>
    <div class="nvg-video-card" data-video-id="<?php echo esc_attr($video_id); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
        <a href="<?php echo esc_url($permalink); ?>" class="nvg-card-link">
            <div class="nvg-card-thumbnail">
                <img src="<?php echo esc_url($thumbnail); ?>" 
                     alt="<?php echo esc_attr(get_the_title($post_id)); ?>" 
                     <?php echo $img_attrs; ?>>
                <?php if ($is_free): ?>
                    <span class="nvg-free-badge">FREE</span>
                <?php endif; ?>
                <div class="nvg-card-overlay">
                    <div class="nvg-card-content">
                        <h3 class="nvg-card-title"><?php echo esc_html(get_the_title($post_id)); ?></h3>
                        <?php if ($short_desc): ?>
                            <p class="nvg-card-description"><?php echo esc_html(wp_trim_words($short_desc, 10, '...')); ?></p>
                        <?php endif; ?>
                        <button class="nvg-play-button">
                            <svg viewBox="0 0 24 24" width="50" height="50">
                                <path fill="currentColor" d="M8 5v14l11-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php
}