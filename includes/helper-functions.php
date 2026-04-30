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
 * Individual purchase product/subscription configuration
 */
function nvg_get_commerce_config() {
    $defaults = array(
        'single_course_product_id' => 0,
        'single_video_product_id'  => 0,
        'monthly_product_id'       => 0,
        'yearly_product_id'        => 0,
        'subscription_product_ids' => array(),
    );

    $saved = get_option('nvg_commerce_settings', array());
    if (!is_array($saved)) {
        $saved = array();
    }

    $settings = wp_parse_args($saved, $defaults);

    $subscription_ids = array();

    if (!empty($settings['subscription_product_ids'])) {
        if (is_array($settings['subscription_product_ids'])) {
            $subscription_ids = $settings['subscription_product_ids'];
        } else {
            $subscription_ids = preg_split('/[\s,]+/', (string) $settings['subscription_product_ids']);
        }
    }

    $subscription_ids = array_values(array_unique(array_filter(array_map('absint', (array) $subscription_ids))));

    // Backward compatibility: if no list is provided, infer from legacy monthly/yearly fields.
    if (empty($subscription_ids)) {
        $legacy = array(
            absint($settings['monthly_product_id']),
            absint($settings['yearly_product_id']),
        );
        $subscription_ids = array_values(array_unique(array_filter($legacy)));
    }

    $monthly_id = isset($subscription_ids[0]) ? absint($subscription_ids[0]) : absint($settings['monthly_product_id']);
    $yearly_id = isset($subscription_ids[1]) ? absint($subscription_ids[1]) : absint($settings['yearly_product_id']);

    return array(
        'single_course_product_id' => absint($settings['single_course_product_id']),
        'single_video_product_id'  => absint($settings['single_video_product_id']),
        'monthly_product_id'       => $monthly_id,
        'yearly_product_id'        => $yearly_id,
        'subscription_product_ids' => $subscription_ids,
    );
}

/**
 * Get sanitized subscription product IDs from commerce config
 */
function nvg_get_subscription_product_ids() {
    $config = nvg_get_commerce_config();
    $ids = isset($config['subscription_product_ids']) ? (array) $config['subscription_product_ids'] : array();
    return array_values(array_unique(array_filter(array_map('absint', $ids))));
}

/**
 * Build subscription plan data from configured WooCommerce products
 */
function nvg_get_subscription_plans() {
    $plans = array();

    if (!function_exists('wc_get_product') || !function_exists('wc_get_checkout_url')) {
        return $plans;
    }

    foreach (nvg_get_subscription_product_ids() as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            continue;
        }

        $raw_description = $product->get_short_description();
        if ('' === trim((string) $raw_description)) {
            $raw_description = $product->get_description();
        }

        $description_text = trim(wp_strip_all_tags((string) $raw_description));
        $feature_lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $description_text)));

        if (empty($feature_lines) && '' !== $description_text) {
            $feature_lines = array($description_text);
        }

        $plans[] = array(
            'product_id'   => $product_id,
            'title'        => $product->get_name(),
            'price_html'   => $product->get_price_html(),
            'features'     => $feature_lines,
            'checkout_url' => add_query_arg('add-to-cart', $product_id, wc_get_checkout_url()),
            'button_text'  => sprintf(__('Choose %s', 'netflix-video-gallery'), $product->get_name()),
        );
    }

    return $plans;
}

/**
 * Check whether a user has access via any configured subscription product.
 */
function nvg_user_has_subscription_access($user_id = 0) {
    $user_id = $user_id ? absint($user_id) : get_current_user_id();
    if (!$user_id) {
        return false;
    }

    $subscription_ids = nvg_get_subscription_product_ids();
    if (empty($subscription_ids)) {
        return false;
    }

    // Preferred path when WooCommerce Subscriptions is available.
    if (function_exists('wcs_user_has_subscription')) {
        foreach ($subscription_ids as $product_id) {
            if (wcs_user_has_subscription($user_id, $product_id, 'active')) {
                return true;
            }
        }
    }

    // Fallback: treat prior product purchase as access when subscriptions API is unavailable.
    if (function_exists('wc_customer_bought_product')) {
        $user = get_user_by('id', $user_id);
        $email = $user && !empty($user->user_email) ? $user->user_email : '';

        foreach ($subscription_ids as $product_id) {
            if (wc_customer_bought_product($email, $user_id, $product_id)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Check if individual purchase is enabled for content
 */
function nvg_is_individual_purchase_enabled($post_id) {
    $enabled = get_field('enable_individual_purchase', absint($post_id));
    return ($enabled === true || $enabled === '1' || $enabled === 1 || $enabled === 'true');
}

/**
 * Get individual price for content
 */
function nvg_get_individual_price($post_id) {
    $price = get_field('individual_price', absint($post_id));
    $price = is_numeric($price) ? (float) $price : 0;
    return max(0, $price);
}

/**
 * Resolve carrier product for content type
 */
function nvg_get_individual_product_id_for_post($post_id) {
    $post = get_post(absint($post_id));
    if (!$post) {
        return 0;
    }

    $config = nvg_get_commerce_config();

    if ($post->post_type === 'course') {
        return absint($config['single_course_product_id']);
    }

    if ($post->post_type === 'video-gallery') {
        return absint($config['single_video_product_id']);
    }

    return 0;
}

/**
 * Check if a user has purchased access to specific content
 */
function nvg_user_has_individual_access($post_id, $user_id = 0) {
    $post_id = absint($post_id);
    $user_id = $user_id ? absint($user_id) : get_current_user_id();

    if (!$post_id || !$user_id) {
        return false;
    }

    $entitlements = get_user_meta($user_id, 'nvg_purchased_access_items', true);
    if (!is_array($entitlements)) {
        return false;
    }

    return in_array($post_id, array_map('absint', $entitlements), true);
}

/**
 * Grant individual access entitlement to user
 */
function nvg_grant_individual_access($user_id, $post_id) {
    $user_id = absint($user_id);
    $post_id = absint($post_id);

    if (!$user_id || !$post_id) {
        return;
    }

    $entitlements = get_user_meta($user_id, 'nvg_purchased_access_items', true);
    if (!is_array($entitlements)) {
        $entitlements = array();
    }

    $entitlements[] = $post_id;
    $entitlements = array_values(array_unique(array_map('absint', $entitlements)));

    update_user_meta($user_id, 'nvg_purchased_access_items', $entitlements);
}

/**
 * Get purchased content IDs for a user
 */
function nvg_get_user_purchased_content_ids($user_id = 0) {
    $user_id = $user_id ? absint($user_id) : get_current_user_id();

    if (!$user_id) {
        return array();
    }

    $entitlements = get_user_meta($user_id, 'nvg_purchased_access_items', true);
    if (!is_array($entitlements)) {
        return array();
    }

    $entitlements = array_values(array_unique(array_map('absint', $entitlements)));
    $entitlements = array_filter($entitlements);

    return array_reverse($entitlements);
}

/**
 * Get purchased posts for a user, optionally filtered by post type
 */
function nvg_get_user_purchased_posts($post_type = '', $user_id = 0) {
    $purchased_ids = nvg_get_user_purchased_content_ids($user_id);

    if (empty($purchased_ids)) {
        return array();
    }

    $query_args = array(
        'post_type'      => $post_type ? $post_type : array('video-gallery', 'course'),
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'post__in'       => $purchased_ids,
        'orderby'        => 'post__in',
    );

    $query = new \WP_Query($query_args);

    return $query->posts;
}

/**
 * Grant purchased entitlements from a WooCommerce order
 */
function nvg_grant_order_entitlements($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }

    $order = wc_get_order(absint($order_id));
    if (!$order) {
        return;
    }

    $user_id = absint($order->get_user_id());
    if (!$user_id) {
        return;
    }

    foreach ($order->get_items() as $item) {
        $content_id = absint($item->get_meta('_nvg_content_id'));
        if ($content_id) {
            nvg_grant_individual_access($user_id, $content_id);
        }
    }
}

add_action('woocommerce_order_status_processing', 'nvg_grant_order_entitlements');
add_action('woocommerce_order_status_completed', 'nvg_grant_order_entitlements');

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

    // Individually purchased content is always accessible.
    if (nvg_user_has_individual_access($post_id)) {
        return true;
    }

    // If Memberships restricts this post, rely on Memberships per-post access.
    if (function_exists('wc_memberships_is_post_content_restricted') && function_exists('wc_memberships_user_can') && wc_memberships_is_post_content_restricted($post_id)) {
        return wc_memberships_user_can(get_current_user_id(), 'view', array('post' => $post_id));
    }

    // For non-restricted content, allow active subscribers to watch premium items.
    if (nvg_user_has_subscription_access()) {
        return true;
    }

    // Default deny for paid content when no entitlement is present.
    return false;
}

/**
 * Check whether current user can access a course
 */
function nvg_user_can_access_course($course_id) {
    $course_id = absint($course_id);

    if (!$course_id) {
        return false;
    }

    // Individually purchased courses are always accessible.
    if (nvg_user_has_individual_access($course_id)) {
        return true;
    }

    // If Memberships restricts this course, rely on Memberships per-post access.
    if (function_exists('wc_memberships_is_post_content_restricted') && function_exists('wc_memberships_user_can') && wc_memberships_is_post_content_restricted($course_id)) {
        return wc_memberships_user_can(get_current_user_id(), 'view', array('post' => $course_id));
    }

    // For non-restricted courses, allow active subscribers.
    if (nvg_user_has_subscription_access()) {
        return true;
    }

    // Default deny when no entitlement is present.
    return false;
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
 * Suppress WooCommerce Memberships restriction message when user has individual entitlement.
 */
function nvg_filter_memberships_restriction_message($message, $post = null) {
    $post_id = 0;

    if (is_numeric($post)) {
        $post_id = absint($post);
    } elseif (is_object($post) && isset($post->ID)) {
        $post_id = absint($post->ID);
    }

    if (!$post_id) {
        return $message;
    }

    $post_type = get_post_type($post_id);
    if (!in_array($post_type, array('video-gallery', 'course'), true)) {
        return $message;
    }

    if (!is_user_logged_in()) {
        return $message;
    }

    if (nvg_user_has_individual_access($post_id)) {
        return '';
    }

    return $message;
}
add_filter('wc_memberships_restricted_content_message', 'nvg_filter_memberships_restriction_message', 10, 2);

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
    
    return new \WP_Query($args);
}

/**
 * Render video card
 */
function nvg_render_video_card($post_id, $lazy = true) {
    $video_url = get_field('video_url', $post_id);
    $video_id = nvg_get_vimeo_id($video_url);
    $thumbnail = nvg_get_video_thumbnail($post_id);
    $is_free = nvg_is_free_video($post_id);
    $can_watch = nvg_user_can_watch_video($post_id);
    $short_desc = get_field('short_description', $post_id);
    $permalink = get_permalink($post_id);
    
    $img_attrs = $lazy ? 'loading="lazy"' : '';
    ?>
    <div class="nvg-video-card" data-video-id="<?php echo esc_attr($video_id); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" data-is-free="<?php echo esc_attr($is_free ? '1' : '0'); ?>" data-can-watch="<?php echo esc_attr($can_watch ? '1' : '0'); ?>">
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
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php
}

/**
 * Get membership popup settings
 */
function nvg_get_membership_popup_settings() {
    $default_account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');

    $defaults = array(
        'popup_title'          => __('Unlock Premium Videos', 'netflix-video-gallery'),
        'popup_description'    => __('Choose a plan and get instant access to all members-only content.', 'netflix-video-gallery'),
        'single_buy_btn_text'  => __('Add To Card', 'netflix-video-gallery'),
        'account_text'         => __('Already a member?', 'netflix-video-gallery'),
        'account_link_text'    => __('Go to My Account', 'netflix-video-gallery'),
        'account_url'          => $default_account_url ? $default_account_url : home_url('/my-account/'),
    );

    $saved = get_option('nvg_popup_settings', array());
    if (!is_array($saved)) {
        $saved = array();
    }

    return wp_parse_args($saved, $defaults);
}

/**
 * Get the My Library URL
 */
function nvg_get_my_library_url() {
    if (function_exists('wc_get_account_endpoint_url')) {
        return wc_get_account_endpoint_url('my-library');
    }

    $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');

    return trailingslashit($account_url) . 'my-library/';
}

/**
 * Resolve the popup footer link based on the viewer session
 */
function nvg_get_popup_account_link_data() {
    $settings = nvg_get_membership_popup_settings();

    if (is_user_logged_in()) {
        return array(
            'text' => __('Already purchased something?', 'netflix-video-gallery'),
            'label' => __('Go to My Library', 'netflix-video-gallery'),
            'url' => nvg_get_my_library_url(),
        );
    }

    return array(
        'text' => $settings['account_text'],
        'label' => $settings['account_link_text'],
        'url' => $settings['account_url'],
    );
}

/**
 * Sanitize membership popup settings
 */
function nvg_sanitize_membership_popup_settings($input) {
    $input = is_array($input) ? $input : array();

    return array(
        'popup_title'          => sanitize_text_field($input['popup_title'] ?? ''),
        'popup_description'    => sanitize_textarea_field($input['popup_description'] ?? ''),
        'single_buy_btn_text'  => sanitize_text_field($input['single_buy_btn_text'] ?? ''),
        'account_text'         => sanitize_text_field($input['account_text'] ?? ''),
        'account_link_text'    => sanitize_text_field($input['account_link_text'] ?? ''),
        'account_url'          => esc_url_raw($input['account_url'] ?? ''),
    );
}

/**
 * Sanitize commerce settings
 */
function nvg_sanitize_commerce_settings($input) {
    $input = is_array($input) ? $input : array();

    $subscription_ids_raw = $input['subscription_product_ids'] ?? ($input['subscription_product_ids_text'] ?? array());

    if (!is_array($subscription_ids_raw)) {
        $subscription_ids_raw = preg_split('/[\s,]+/', (string) $subscription_ids_raw);
    }

    $subscription_ids = array_values(array_unique(array_filter(array_map('absint', $subscription_ids_raw))));

    return array(
        'single_course_product_id' => absint($input['single_course_product_id'] ?? 0),
        'single_video_product_id'  => absint($input['single_video_product_id'] ?? 0),
        'monthly_product_id'       => absint($input['monthly_product_id'] ?? 0),
        'yearly_product_id'        => absint($input['yearly_product_id'] ?? 0),
        'subscription_product_ids' => $subscription_ids,
    );
}

/**
 * Register popup settings
 */
function nvg_register_membership_popup_settings() {
    register_setting(
        'nvg_popup_settings_group',
        'nvg_popup_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'nvg_sanitize_membership_popup_settings',
            'default'           => array(),
        )
    );

    register_setting(
        'nvg_commerce_settings_group',
        'nvg_commerce_settings',
        array(
            'type'              => 'array',
            'sanitize_callback' => 'nvg_sanitize_commerce_settings',
            'default'           => array(),
        )
    );
}
add_action('admin_init', 'nvg_register_membership_popup_settings');

/**
 * Add popup settings page under Video Gallery menu
 */
function nvg_add_membership_popup_settings_page() {
    add_submenu_page(
        'edit.php?post_type=video-gallery',
        __('Membership Settings', 'netflix-video-gallery'),
        __('Membership Settings', 'netflix-video-gallery'),
        'manage_options',
        'nvg-membership-popup',
        'nvg_render_membership_popup_settings_page'
    );
}
add_action('admin_menu', 'nvg_add_membership_popup_settings_page');

/**
 * Render popup settings page
 */
function nvg_render_membership_popup_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = nvg_get_membership_popup_settings();
    $commerce_settings = nvg_get_commerce_config();
    $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'commerce';
    $allowed_tabs = array('commerce', 'popup', 'guest-link');

    if (!in_array($tab, $allowed_tabs, true)) {
        $tab = 'commerce';
    }

    $base_url = admin_url('edit.php?post_type=video-gallery&page=nvg-membership-popup');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Membership Settings', 'netflix-video-gallery'); ?></h1>

        <nav class="nav-tab-wrapper" aria-label="Membership Settings Tabs">
            <a href="<?php echo esc_url(add_query_arg('tab', 'commerce', $base_url)); ?>" class="nav-tab <?php echo 'commerce' === $tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Commerce', 'netflix-video-gallery'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'popup', $base_url)); ?>" class="nav-tab <?php echo 'popup' === $tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Popup Content', 'netflix-video-gallery'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('tab', 'guest-link', $base_url)); ?>" class="nav-tab <?php echo 'guest-link' === $tab ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Guest Account Link', 'netflix-video-gallery'); ?>
            </a>
        </nav>

        <?php if ('commerce' === $tab) : ?>
            <form method="post" action="options.php">
                <?php settings_fields('nvg_commerce_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="nvg_single_video_product_id"><?php esc_html_e('Single Video Product ID', 'netflix-video-gallery'); ?></label></th>
                        <td>
                            <input name="nvg_commerce_settings[single_video_product_id]" id="nvg_single_video_product_id" type="number" min="0" class="small-text" value="<?php echo esc_attr($commerce_settings['single_video_product_id']); ?>">
                            <p class="description"><?php esc_html_e('WooCommerce product used as the carrier for individual video purchases.', 'netflix-video-gallery'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nvg_single_course_product_id"><?php esc_html_e('Single Course Product ID', 'netflix-video-gallery'); ?></label></th>
                        <td>
                            <input name="nvg_commerce_settings[single_course_product_id]" id="nvg_single_course_product_id" type="number" min="0" class="small-text" value="<?php echo esc_attr($commerce_settings['single_course_product_id']); ?>">
                            <p class="description"><?php esc_html_e('WooCommerce product used as the carrier for individual course purchases.', 'netflix-video-gallery'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nvg_subscription_product_ids"><?php esc_html_e('Subscription Product IDs', 'netflix-video-gallery'); ?></label></th>
                        <td>
                            <textarea name="nvg_commerce_settings[subscription_product_ids_text]" id="nvg_subscription_product_ids" rows="5" class="large-text"><?php echo esc_textarea(implode("\n", nvg_get_subscription_product_ids())); ?></textarea>
                            <p class="description"><?php esc_html_e('Add one WooCommerce subscription product ID per line. You can add 1, 2, or many plans.', 'netflix-video-gallery'); ?></p>
                            <p class="description"><?php esc_html_e('Popup content for each plan is pulled from that product: Name, Price, and Short Description (as feature lines).', 'netflix-video-gallery'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Commerce Settings', 'netflix-video-gallery')); ?>
            </form>
        <?php elseif ('popup' === $tab) : ?>
            <form method="post" action="options.php">
                <?php settings_fields('nvg_popup_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="nvg_popup_title"><?php esc_html_e('Popup Title', 'netflix-video-gallery'); ?></label></th>
                        <td><input name="nvg_popup_settings[popup_title]" id="nvg_popup_title" type="text" class="regular-text" value="<?php echo esc_attr($settings['popup_title']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nvg_popup_description"><?php esc_html_e('Popup Description', 'netflix-video-gallery'); ?></label></th>
                        <td><textarea name="nvg_popup_settings[popup_description]" id="nvg_popup_description" class="large-text" rows="3"><?php echo esc_textarea($settings['popup_description']); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nvg_single_buy_btn_text"><?php esc_html_e('Individual Button Text', 'netflix-video-gallery'); ?></label></th>
                        <td>
                            <input name="nvg_popup_settings[single_buy_btn_text]" id="nvg_single_buy_btn_text" type="text" class="regular-text" value="<?php echo esc_attr($settings['single_buy_btn_text']); ?>">
                            <p class="description"><?php esc_html_e('Text shown on the single purchase button in the popup (default: Add To Card).', 'netflix-video-gallery'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Popup Content', 'netflix-video-gallery')); ?>
            </form>
        <?php else : ?>
            <form method="post" action="options.php">
                <?php settings_fields('nvg_popup_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="nvg_account_text"><?php esc_html_e('Guest Line Text', 'netflix-video-gallery'); ?></label></th>
                        <td><input name="nvg_popup_settings[account_text]" id="nvg_account_text" type="text" class="regular-text" value="<?php echo esc_attr($settings['account_text']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nvg_account_link_text"><?php esc_html_e('Guest Link Label', 'netflix-video-gallery'); ?></label></th>
                        <td><input name="nvg_popup_settings[account_link_text]" id="nvg_account_link_text" type="text" class="regular-text" value="<?php echo esc_attr($settings['account_link_text']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nvg_account_url"><?php esc_html_e('Guest My Account URL', 'netflix-video-gallery'); ?></label></th>
                        <td>
                            <input name="nvg_popup_settings[account_url]" id="nvg_account_url" type="url" class="large-text" value="<?php echo esc_attr($settings['account_url']); ?>">
                            <p class="description"><?php esc_html_e('Logged-in users automatically see My Library in the popup footer.', 'netflix-video-gallery'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Guest Link Settings', 'netflix-video-gallery')); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render paywall popup in footer on plugin pages
 */
function nvg_render_membership_popup_modal() {
    if (!is_post_type_archive('video-gallery') && !is_tax('video-category') && !is_singular('video-gallery') && !is_singular('course')) {
        return;
    }

    $settings = nvg_get_membership_popup_settings();
    $account_link = nvg_get_popup_account_link_data();
    $subscription_plans = nvg_get_subscription_plans();
    $single_buy_btn_text = trim((string) ($settings['single_buy_btn_text'] ?? ''));
    if ('' === $single_buy_btn_text) {
        $single_buy_btn_text = __('Add To Card', 'netflix-video-gallery');
    }
    ?>
    <div class="nvg-paywall-modal" id="nvg-paywall-modal" aria-hidden="true">
        <div class="nvg-paywall-backdrop" data-nvg-paywall-close></div>
        <div class="nvg-paywall-dialog" role="dialog" aria-modal="true" aria-labelledby="nvg-paywall-title">
            <button type="button" class="nvg-paywall-close" data-nvg-paywall-close aria-label="<?php esc_attr_e('Close popup', 'netflix-video-gallery'); ?>">&times;</button>

            <h2 class="nvg-paywall-title" id="nvg-paywall-title"><?php echo esc_html($settings['popup_title']); ?></h2>
            <p class="nvg-paywall-description"><?php echo esc_html($settings['popup_description']); ?></p>

            <div class="nvg-paywall-single-offer" id="nvg-paywall-single-offer" hidden>
                <div class="nvg-paywall-single-offer-text">
                    <strong id="nvg-paywall-single-label"><?php esc_html_e('Buy This Item', 'netflix-video-gallery'); ?></strong>
                    <span id="nvg-paywall-single-price"></span>
                </div>
                <a id="nvg-paywall-single-buy" class="nvg-btn nvg-btn-secondary" href="#">
                    <?php echo esc_html($single_buy_btn_text); ?>
                </a>
            </div>

            <div class="nvg-paywall-plans">
                <?php if (!empty($subscription_plans)) : ?>
                    <?php foreach ($subscription_plans as $index => $plan) : ?>
                        <div class="nvg-paywall-plan <?php echo 0 === $index ? 'nvg-paywall-plan-featured' : ''; ?>">
                            <h3><?php echo esc_html($plan['title']); ?></h3>
                            <div class="nvg-paywall-price"><?php echo wp_kses_post($plan['price_html']); ?></div>
                            <?php if (!empty($plan['features'])) : ?>
                                <ul class="nvg-paywall-features">
                                    <?php foreach ((array) $plan['features'] as $feature) : ?>
                                        <li><?php echo esc_html($feature); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <a class="nvg-btn nvg-btn-primary nvg-paywall-btn" href="<?php echo esc_url($plan['checkout_url']); ?>">
                                <?php echo esc_html($plan['button_text']); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="nvg-paywall-plan">
                        <h3><?php esc_html_e('No Plans Configured', 'netflix-video-gallery'); ?></h3>
                        <p><?php esc_html_e('Add subscription product IDs in Membership Settings > Commerce to show plans here.', 'netflix-video-gallery'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <p class="nvg-paywall-account">
                <?php echo esc_html($account_link['text']); ?>
                <a href="<?php echo esc_url($account_link['url']); ?>"><?php echo esc_html($account_link['label']); ?></a>
            </p>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'nvg_render_membership_popup_modal');

/**
 * Register My Library account endpoint
 */
function nvg_register_my_library_endpoint() {
    add_rewrite_endpoint('my-library', EP_ROOT | EP_PAGES);
}
add_action('init', 'nvg_register_my_library_endpoint');

/**
 * Flush rewrite rules once after the endpoint is added or updated
 */
function nvg_maybe_flush_my_library_endpoint() {
    $stored_version = get_option('nvg_my_library_endpoint_version', '');

    if ($stored_version === NVG_VERSION) {
        return;
    }

    nvg_register_my_library_endpoint();
    flush_rewrite_rules(false);
    update_option('nvg_my_library_endpoint_version', NVG_VERSION);
}
add_action('init', 'nvg_maybe_flush_my_library_endpoint', 20);

/**
 * Add My Library to WooCommerce account menu
 */
function nvg_add_my_library_account_menu_item($items) {
    $new_items = array();
    $inserted = false;

    foreach ($items as $key => $label) {
        $new_items[$key] = $label;

        if (!$inserted && 'dashboard' === $key) {
            $new_items['my-library'] = __('My Library', 'netflix-video-gallery');
            $inserted = true;
        }
    }

    if (!$inserted) {
        $new_items['my-library'] = __('My Library', 'netflix-video-gallery');
    }

    return $new_items;
}
add_filter('woocommerce_account_menu_items', 'nvg_add_my_library_account_menu_item');

/**
 * Render a purchased-content section inside the library page
 */
function nvg_render_purchased_library_section($posts, $section_title, $type) {
    if (empty($posts)) {
        return;
    }
    ?>
    <section class="nvg-library-section nvg-library-section-<?php echo esc_attr($type); ?>">
        <div class="nvg-library-section-head">
            <h2 class="nvg-library-section-title"><?php echo esc_html($section_title); ?></h2>
            <span class="nvg-library-section-count"><?php echo esc_html(count($posts)); ?></span>
        </div>
        <div class="nvg-library-section-panel">
            <div class="nvg-library-grid <?php echo 'course' === $type ? 'nvg-library-course-grid' : 'nvg-library-video-grid'; ?>">
                <?php foreach ($posts as $post) : ?>
                    <?php if ('course' === $type) : ?>
                        <?php nvg_render_course_card($post->ID, true); ?>
                    <?php else : ?>
                        <?php nvg_render_video_card($post->ID); ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

/**
 * Render the My Library account endpoint content
 */
function nvg_render_my_library_account_content() {
    if (!is_user_logged_in()) {
        echo '<p>' . esc_html__('Please log in to view your library.', 'netflix-video-gallery') . '</p>';
        return;
    }

    $purchased_videos = nvg_get_user_purchased_posts('video-gallery');
    $purchased_courses = nvg_get_user_purchased_posts('course');

    ?>
    <div class="nvg-library-page">
        <div class="nvg-library-intro">
            <h2><?php esc_html_e('My Library', 'netflix-video-gallery'); ?></h2>
            <p><?php esc_html_e('Videos and courses you bought individually appear here.', 'netflix-video-gallery'); ?></p>
        </div>

        <?php if (empty($purchased_videos) && empty($purchased_courses)) : ?>
            <div class="nvg-no-video nvg-library-empty">
                <p><?php esc_html_e('You have not purchased any individual videos or courses yet.', 'netflix-video-gallery'); ?></p>
            </div>
        <?php else : ?>
            <?php nvg_render_purchased_library_section($purchased_videos, __('Purchased Videos', 'netflix-video-gallery'), 'video'); ?>
            <?php nvg_render_purchased_library_section($purchased_courses, __('Purchased Courses', 'netflix-video-gallery'), 'course'); ?>
        <?php endif; ?>
    </div>
    <?php
}
add_action('woocommerce_account_my-library_endpoint', 'nvg_render_my_library_account_content');

/**
 * Check if a product is one of the individual-access carrier products
 */
function nvg_is_individual_carrier_product($product_id) {
    $product_id = absint($product_id);
    $config = nvg_get_commerce_config();

    return in_array($product_id, array(
        absint($config['single_course_product_id']),
        absint($config['single_video_product_id']),
    ), true);
}

/**
 * Store custom content access data when adding carrier products to cart
 */
function nvg_add_individual_item_to_cart_data($cart_item_data, $product_id) {
    $product_id = absint($product_id);

    if (!nvg_is_individual_carrier_product($product_id)) {
        return $cart_item_data;
    }

    $content_id = isset($_REQUEST['nvg_content_id']) ? absint($_REQUEST['nvg_content_id']) : 0;
    if (!$content_id) {
        return $cart_item_data;
    }

    $content = get_post($content_id);
    if (!$content || !in_array($content->post_type, array('video-gallery', 'course'), true)) {
        return $cart_item_data;
    }

    if (!nvg_is_individual_purchase_enabled($content_id)) {
        return $cart_item_data;
    }

    $expected_product_id = nvg_get_individual_product_id_for_post($content_id);
    if ($expected_product_id !== $product_id) {
        return $cart_item_data;
    }

    $price = nvg_get_individual_price($content_id);
    if ($price <= 0) {
        return $cart_item_data;
    }

    $cart_item_data['nvg_content_id'] = $content_id;
    $cart_item_data['nvg_content_type'] = $content->post_type;
    $cart_item_data['nvg_content_title'] = $content->post_title;
    $cart_item_data['nvg_individual_price'] = $price;
    $cart_item_data['nvg_unique_key'] = md5($content_id . '|' . microtime(true));

    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'nvg_add_individual_item_to_cart_data', 10, 2);

/**
 * Override carrier product price with content-specific price
 */
function nvg_set_individual_cart_item_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }

    if (!$cart || empty($cart->get_cart())) {
        return;
    }

    foreach ($cart->get_cart() as $cart_item) {
        if (empty($cart_item['nvg_content_id']) || !isset($cart_item['nvg_individual_price'])) {
            continue;
        }

        $price = max(0, (float) $cart_item['nvg_individual_price']);
        if ($price > 0 && isset($cart_item['data']) && is_object($cart_item['data'])) {
            $cart_item['data']->set_price($price);
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'nvg_set_individual_cart_item_price', 20);

/**
 * Show purchased content info in cart/checkout rows
 */
function nvg_display_individual_item_data($item_data, $cart_item) {
    if (empty($cart_item['nvg_content_id']) || empty($cart_item['nvg_content_title'])) {
        return $item_data;
    }

    $label = ($cart_item['nvg_content_type'] ?? '') === 'course'
        ? __('Course Access', 'netflix-video-gallery')
        : __('Video Access', 'netflix-video-gallery');

    $item_data[] = array(
        'name'  => $label,
        'value' => sanitize_text_field($cart_item['nvg_content_title']),
    );

    return $item_data;
}
add_filter('woocommerce_get_item_data', 'nvg_display_individual_item_data', 10, 2);

/**
 * Persist purchased content metadata to order line items
 */
function nvg_add_order_line_item_meta($item, $cart_item_key, $values) {
    if (empty($values['nvg_content_id'])) {
        return;
    }

    $item->add_meta_data('_nvg_content_id', absint($values['nvg_content_id']), true);
    $item->add_meta_data('_nvg_content_type', sanitize_text_field($values['nvg_content_type'] ?? ''), true);
    $item->add_meta_data('_nvg_content_title', sanitize_text_field($values['nvg_content_title'] ?? ''), true);
}
add_action('woocommerce_checkout_create_order_line_item', 'nvg_add_order_line_item_meta', 10, 3);

/**
 * Render subscription upsell on checkout when cart contains individual access items
 */
function nvg_render_checkout_subscription_upsell() {
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $has_individual_item = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (!empty($cart_item['nvg_content_id'])) {
            $has_individual_item = true;
            break;
        }
    }

    if (!$has_individual_item) {
        return;
    }

    $plans = nvg_get_subscription_plans();
    if (empty($plans)) {
        return;
    }
    ?>
    <div class="nvg-checkout-upsell">
        <h3><?php esc_html_e('Upgrade to Subscription and Save More', 'netflix-video-gallery'); ?></h3>
        <p><?php esc_html_e('Get full library access instead of buying one item at a time.', 'netflix-video-gallery'); ?></p>
        <div class="nvg-checkout-upsell-actions">
            <?php foreach ($plans as $plan_index => $plan) : ?>
                <?php
                $upgrade_url = add_query_arg(
                    array(
                        'nvg_upgrade_sub_id' => absint($plan['product_id']),
                        'nvg_upgrade_nonce'  => wp_create_nonce('nvg_upgrade_subscription'),
                    ),
                    wc_get_checkout_url()
                );
                ?>
                <a class="button <?php echo 0 === $plan_index ? 'alt' : ''; ?>" href="<?php echo esc_url($upgrade_url); ?>">
                    <?php echo esc_html(sprintf(__('Switch to %s', 'netflix-video-gallery'), $plan['title'])); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
add_action('woocommerce_before_checkout_form', 'nvg_render_checkout_subscription_upsell', 6);

/**
 * Replace individual items with selected subscription from checkout upsell links
 */
function nvg_handle_checkout_subscription_upgrade() {
    if (!function_exists('is_checkout') || !is_checkout() || is_admin()) {
        return;
    }

    $target_product_id = isset($_GET['nvg_upgrade_sub_id']) ? absint($_GET['nvg_upgrade_sub_id']) : 0;
    if (!$target_product_id) {
        return;
    }

    $upgrade_nonce = isset($_GET['nvg_upgrade_nonce']) ? sanitize_text_field(wp_unslash($_GET['nvg_upgrade_nonce'])) : '';
    if (empty($upgrade_nonce) || !wp_verify_nonce($upgrade_nonce, 'nvg_upgrade_subscription')) {
        return;
    }

    if (!function_exists('WC') || !WC()->cart) {
        return;
    }

    $allowed_plan_ids = nvg_get_subscription_product_ids();
    if (!in_array($target_product_id, $allowed_plan_ids, true)) {
        return;
    }

    $removed_any = false;
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (!empty($cart_item['nvg_content_id'])) {
            WC()->cart->remove_cart_item($cart_item_key);
            $removed_any = true;
        }
    }

    if ($removed_any) {
        WC()->cart->add_to_cart($target_product_id, 1);
        wc_add_notice(__('Your single-item purchase has been replaced with the selected subscription plan.', 'netflix-video-gallery'), 'success');
    }

    wp_safe_redirect(wc_get_checkout_url());
    exit;
}
add_action('template_redirect', 'nvg_handle_checkout_subscription_upgrade');

/**
 * Check whether current checkout cart contains only virtual products.
 */
function nvg_is_virtual_only_checkout_cart() {
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }

    $items = WC()->cart->get_cart();
    if (empty($items)) {
        return false;
    }

    foreach ($items as $cart_item) {
        if (empty($cart_item['data']) || !is_object($cart_item['data']) || !method_exists($cart_item['data'], 'is_virtual')) {
            return false;
        }

        if (!$cart_item['data']->is_virtual()) {
            return false;
        }
    }

    return true;
}

/**
 * Simplify checkout fields for virtual-only orders.
 */
function nvg_simplify_virtual_checkout_fields($fields) {
    if (!is_checkout() || nvg_is_virtual_only_checkout_cart() === false) {
        return $fields;
    }

    $allowed_billing_fields = array('billing_first_name', 'billing_last_name', 'billing_email');

    if (isset($fields['billing']) && is_array($fields['billing'])) {
        foreach (array_keys($fields['billing']) as $billing_key) {
            if (!in_array($billing_key, $allowed_billing_fields, true)) {
                unset($fields['billing'][$billing_key]);
            }
        }
    }

    // Remove shipping and order note sections for virtual content checkout.
    if (isset($fields['shipping'])) {
        $fields['shipping'] = array();
    }

    if (isset($fields['order']['order_comments'])) {
        unset($fields['order']['order_comments']);
    }

    // Keep account creation focused on password when available.
    if (isset($fields['account']) && is_array($fields['account'])) {
        foreach (array_keys($fields['account']) as $account_key) {
            if ('account_password' !== $account_key) {
                unset($fields['account'][$account_key]);
            }
        }
    }

    return $fields;
}
add_filter('woocommerce_checkout_fields', 'nvg_simplify_virtual_checkout_fields', 20);

/**
 * Keep only allowed billing fields on virtual-only checkout.
 */
function nvg_filter_virtual_checkout_billing_fields($billing_fields) {
    if (!is_checkout() || !is_array($billing_fields) || nvg_is_virtual_only_checkout_cart() === false) {
        return $billing_fields;
    }

    $allowed_billing_fields = array('billing_first_name', 'billing_last_name', 'billing_email');

    foreach (array_keys($billing_fields) as $billing_key) {
        if (!in_array($billing_key, $allowed_billing_fields, true)) {
            unset($billing_fields[$billing_key]);
        }
    }

    return $billing_fields;
}
add_filter('woocommerce_billing_fields', 'nvg_filter_virtual_checkout_billing_fields', 9999);

/**
 * Remove all shipping fields for virtual-only checkout.
 */
function nvg_filter_virtual_checkout_shipping_fields($shipping_fields) {
    if (!is_checkout() || nvg_is_virtual_only_checkout_cart() === false) {
        return $shipping_fields;
    }

    return array();
}
add_filter('woocommerce_shipping_fields', 'nvg_filter_virtual_checkout_shipping_fields', 9999);

/**
 * Ensure address defaults are not enforced for virtual-only checkout.
 */
function nvg_filter_virtual_default_address_fields($address_fields) {
    if (!is_checkout() || !is_array($address_fields) || nvg_is_virtual_only_checkout_cart() === false) {
        return $address_fields;
    }

    foreach (array_keys($address_fields) as $address_key) {
        unset($address_fields[$address_key]);
    }

    return $address_fields;
}
add_filter('woocommerce_default_address_fields', 'nvg_filter_virtual_default_address_fields', 9999);

/**
 * Remove optional shipping/order notes for virtual-only checkout.
 */
function nvg_disable_virtual_checkout_shipping_and_notes($enabled) {
    if (nvg_is_virtual_only_checkout_cart()) {
        return false;
    }

    return $enabled;
}
add_filter('woocommerce_cart_needs_shipping_address', 'nvg_disable_virtual_checkout_shipping_and_notes', 20);
add_filter('woocommerce_enable_order_notes_field', 'nvg_disable_virtual_checkout_shipping_and_notes', 20);

/**
 * Add body class on checkout page when cart has only virtual products.
 * Used by the Block Checkout CSS approach below (classic filters don't apply to Block Checkout).
 */
function nvg_add_virtual_cart_body_class( $classes ) {
    if ( is_checkout() && nvg_is_virtual_only_checkout_cart() ) {
        $classes[] = 'nvg-virtual-only-cart';
    }
    return $classes;
}
add_filter( 'body_class', 'nvg_add_virtual_cart_body_class' );

/**
 * Inject CSS to hide address/shipping fields in WooCommerce Block Checkout
 * when the cart contains only virtual products.
 */
function nvg_block_checkout_virtual_css() {
    if ( ! is_checkout() || ! nvg_is_virtual_only_checkout_cart() ) {
        return;
    }
    echo '<style id="nvg-virtual-checkout-block-css">
/* Hide billing/shipping fields in WooCommerce Block Checkout for virtual-only carts */
.nvg-virtual-only-cart .wc-block-components-address-form__first_name,
.nvg-virtual-only-cart .wc-block-components-address-form__last_name,
.nvg-virtual-only-cart .wc-block-components-address-form__address_1,
.nvg-virtual-only-cart .wc-block-components-address-form__address_2,
.nvg-virtual-only-cart .wc-block-components-address-form__city,
.nvg-virtual-only-cart .wc-block-components-address-form__state,
.nvg-virtual-only-cart .wc-block-components-address-form__postcode,
.nvg-virtual-only-cart .wc-block-components-address-form__country,
.nvg-virtual-only-cart .wc-block-components-address-form__company,
.nvg-virtual-only-cart .wc-block-components-address-form__phone,
.nvg-virtual-only-cart .wc-block-checkout__shipping-fields,
.nvg-virtual-only-cart .wc-block-checkout__add-address-fields,
.nvg-virtual-only-cart .wp-block-woocommerce-checkout-shipping-address-block,
.nvg-virtual-only-cart .wp-block-woocommerce-checkout-billing-address-block .wc-block-components-title,
.nvg-virtual-only-cart .wp-block-woocommerce-checkout-billing-address-block h2,
.nvg-virtual-only-cart .wp-block-woocommerce-checkout-billing-address-block h3,
.nvg-virtual-only-cart .wp-block-woocommerce-checkout-order-note-block {
    display: none !important;
}
</style>' . "\n";
}
add_action( 'wp_head', 'nvg_block_checkout_virtual_css', 100 );