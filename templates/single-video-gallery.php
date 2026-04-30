<?php
/**
 * Template for Single Video
 */

get_header();

while (have_posts()) : the_post();
    $current_video_id = get_the_ID();
    $video_url = get_field('video_url');
    $video_id = nvg_get_vimeo_id($video_url);
    $embed_url = nvg_get_vimeo_embed_url($video_url);
    $short_desc = get_field('short_description');
    $is_free = nvg_is_free_video($current_video_id);
    $can_watch_video = nvg_user_can_watch_video($current_video_id);
    $has_individual_access = nvg_user_has_individual_access($current_video_id);
    $should_auto_open_paywall = (!$can_watch_video && !$is_free);
    $categories = get_the_terms($current_video_id, 'video-category');

    $video_content_html = '';
    if (get_the_content()) {
        $video_content_html = apply_filters('the_content', get_the_content());

        // If user bought this video individually, remove memberships upsell notice from content area.
        if ($has_individual_access && function_exists('wc_memberships_get_restricted_content_message') && function_exists('wc_memberships_is_post_content_restricted') && wc_memberships_is_post_content_restricted($current_video_id)) {
            $restriction_message = wc_memberships_get_restricted_content_message(get_post($current_video_id));
            if (!empty($restriction_message)) {
                $video_content_html = str_replace((string) $restriction_message, '', (string) $video_content_html);
            }
        }
    }
?>

<div class="nvg-single-wrapper">

    <section class="nvg-single-player">
        <div class="nvg-single-player-top">
            <a class="nvg-back-btn" href="javascript:history.back()" aria-label="Go back">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Back
            </a>
        </div>
        <div class="nvg-player-container">
            <?php if ($can_watch_video && $embed_url) : ?>
                <iframe src="<?php echo esc_url($embed_url); ?>" 
                        width="100%" 
                        height="100%" 
                        frameborder="0" 
                        allow="autoplay; fullscreen; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            <?php elseif (!$can_watch_video) : ?>
                <div class="nvg-no-video nvg-restricted-video" data-post-id="<?php echo esc_attr($current_video_id); ?>" <?php echo $should_auto_open_paywall ? 'data-nvg-auto-paywall="1"' : ''; ?>>
                    <?php echo wp_kses_post(nvg_get_video_restriction_message($current_video_id)); ?>
                    <?php if ($should_auto_open_paywall) : ?>
                        <p>
                            <button type="button" class="nvg-btn nvg-btn-primary nvg-open-paywall-popup" data-post-id="<?php echo esc_attr($current_video_id); ?>">
                                <?php esc_html_e('View Membership Plans', 'netflix-video-gallery'); ?>
                            </button>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="nvg-no-video">
                    <p>Video not available</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (($categories && !is_wp_error($categories)) || $is_free) : ?>
        <section class="nvg-single-player-meta">
            <div class="nvg-container">
                <div class="nvg-single-meta">
                    <?php if ($categories && !is_wp_error($categories)) : ?>
                        <div class="nvg-categories">
                            <?php foreach ($categories as $category) : ?>
                                <a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="nvg-category-badge">
                                    <?php echo esc_html($category->name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($is_free) : ?>
                        <span class="nvg-free-badge">FREE</span>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
    
    <!-- Video Info Section -->
    <section class="nvg-single-info">
        <div class="nvg-container">
            <div class="nvg-info-header">
                <h1 class="nvg-single-title"><?php the_title(); ?></h1>
            </div>
            
            <?php if ($short_desc) : ?>
                <div class="nvg-single-description">
                    <p><?php echo esc_html($short_desc); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (trim(wp_strip_all_tags((string) $video_content_html)) !== '') : ?>
                <div class="nvg-single-content">
                    <?php echo wp_kses_post($video_content_html); ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Related Videos Section -->
    <section class="nvg-related-section">
        <div class="nvg-container">
            <?php
            $related = nvg_get_related_videos(get_the_ID(), 12);
            
            if ($related->have_posts()) :
            ?>
                <div class="nvg-section-header">
                    <h2 class="nvg-section-title">Related Videos</h2>
                </div>
                
                <div class="swiper nvg-related-slider">
                    <div class="swiper-wrapper">
                        <?php while ($related->have_posts()) : $related->the_post(); ?>
                            <div class="swiper-slide">
                                <?php nvg_render_video_card(get_the_ID()); ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            <?php
                wp_reset_postdata();
            endif;
            ?>
        </div>
    </section>
    
</div>

<?php
endwhile;
get_footer();
?>