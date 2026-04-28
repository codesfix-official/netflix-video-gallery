<?php
/**
 * Template for Video Category (70/30 Layout with Auto-play)
 */

get_header();

$term = get_queried_object();

// Fetch all videos in this category, ordered strictly by date DESC.
// A single query avoids any secondary meta-value sorting that appears
// when meta_query is mixed with orderby=date in separate queries.
// suppress_filters = true prevents WooCommerce Memberships (and other plugins)
// from hooking into posts_orderby / posts_join and reordering results by
// membership access level. Ordering is handled explicitly in PHP below.
$all_videos_query = new WP_Query(array(
    'post_type'        => 'video-gallery',
    'posts_per_page'   => -1,
    'orderby'          => 'date',
    'order'            => 'ASC',
    'suppress_filters' => true,
    'tax_query'        => array(
        array(
            'taxonomy' => 'video-category',
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ),
    ),
));

$featured_ids    = array();
$featured_videos = array();
$other_videos    = array();

foreach ($all_videos_query->posts as $video_post) {
    $featured = get_field('featured', $video_post->ID);
    $is_featured = ($featured === 'Yes' || $featured === true || $featured === '1');

    if ($is_featured) {
        $featured_ids[]    = (int) $video_post->ID;
        $featured_videos[] = $video_post;
    } else {
        $other_videos[] = $video_post;
    }
}

// Featured first (date DESC within them), then the rest (date DESC).
// Free/paid status has zero influence on position.
$ordered_videos = array_merge($featured_videos, $other_videos);

// Safety net: re-sort each group by date DESC in PHP so no DB-level filter
// (e.g. WooCommerce Memberships) can silently change the order.
usort($featured_videos, function($a, $b) {
    return strtotime($a->post_date) - strtotime($b->post_date);
});
usort($other_videos, function($a, $b) {
    return strtotime($a->post_date) - strtotime($b->post_date);
});
$ordered_videos = array_merge($featured_videos, $other_videos);

$total_videos = count($ordered_videos);

// Determine initial video
$initial_video = null;

foreach ($ordered_videos as $video_post) {
    if (nvg_user_can_watch_video($video_post->ID)) {
        $initial_video = $video_post;
        break;
    }
}

?>

<div class="nvg-category-page">

    <!-- Page Header -->
    <div class="nvg-category-page-header">
        <div class="nvg-container">
            <h1 class="nvg-page-title"><?php echo esc_html($term->name); ?></h1>
            <a class="nvg-back-btn" href="javascript:history.back()" aria-label="Go back">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Back
            </a>
            <?php if ($term->description) : ?>
                <p class="nvg-page-description"><?php echo esc_html($term->description); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Player Layout (70/30) -->
    <div class="nvg-player-layout">
        
        <!-- Left Section: Video Player (70%) -->
        <div class="nvg-player-section">
            <?php if ($initial_video) : 
                $video_url = get_field('video_url', $initial_video->ID);
                $video_id = nvg_get_vimeo_id($video_url);
                $short_desc = get_field('short_description', $initial_video->ID);
            ?>
                <div class="nvg-player-wrapper">
                    <div id="nvg-vimeo-player" 
                         data-video-id="<?php echo esc_attr($video_id); ?>"
                         data-post-id="<?php echo esc_attr($initial_video->ID); ?>">
                    </div>
                </div>
                
                <div class="nvg-player-info">
                    <h2 id="nvg-current-title" class="nvg-video-title">
                        <?php echo esc_html($initial_video->post_title); ?>
                    </h2>
                    <div class="nvg-video-meta">
                        <span class="nvg-category-badge"><?php echo esc_html($term->name); ?></span>
                    </div>
                    <?php if ($short_desc) : ?>
                        <p id="nvg-current-description" class="nvg-video-description">
                            <?php echo esc_html($short_desc); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="nvg-player-wrapper">
                    <div class="nvg-no-video nvg-restricted-video">
                        <?php echo wp_kses_post(__('No videos are currently available for your membership access in this category.', 'netflix-video-gallery')); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Section: Playlist (30%) -->
        <div class="nvg-playlist-section">
            <div class="nvg-playlist-header">
                <h3>Playlist</h3>
                <span class="nvg-playlist-count">
                    <?php echo esc_html($total_videos); ?> videos
                </span>
            </div>
            
            <div class="nvg-playlist-wrapper">
                <div id="nvg-playlist" class="nvg-playlist">
                    <?php
                    if (!empty($ordered_videos)) :
                        foreach ($ordered_videos as $index => $video_post) :
                            $post_id = $video_post->ID;
                            $video_url = get_field('video_url', $post_id);
                            $video_id = nvg_get_vimeo_id($video_url);
                            $thumbnail = nvg_get_video_thumbnail($post_id, 'medium');
                            $is_free = nvg_is_free_video($post_id);
                            $can_watch = nvg_user_can_watch_video($post_id);
                            $is_locked = !$can_watch;
                            $is_active = ($initial_video && $post_id === $initial_video->ID) ? 'active' : '';
                    ?>
                        <div class="nvg-playlist-item <?php echo esc_attr(trim($is_active . ' ' . ($is_locked ? 'locked' : ''))); ?>" 
                             data-video-id="<?php echo esc_attr($can_watch ? $video_id : ''); ?>"
                             data-post-id="<?php echo esc_attr($post_id); ?>"
                             data-can-watch="<?php echo esc_attr($can_watch ? '1' : '0'); ?>"
                             data-index="<?php echo esc_attr($index); ?>">
                            <div class="nvg-playlist-thumbnail">
                                <img src="<?php echo esc_url($thumbnail); ?>" 
                                     alt="<?php echo esc_attr(get_the_title($post_id)); ?>"
                                     loading="lazy">
                                <div class="nvg-playlist-play-icon">
                                    <svg viewBox="0 0 24 24" width="30" height="30">
                                        <path fill="currentColor" d="M8 5v14l11-7z"/>
                                    </svg>
                                </div>
                                <?php if ($is_free) : ?>
                                    <span class="nvg-playlist-free-badge">FREE</span>
                                <?php endif; ?>
                                <?php if ($is_locked) : ?>
                                    <span class="nvg-playlist-lock-badge">MEMBERS ONLY</span>
                                <?php endif; ?>
                            </div>
                            <div class="nvg-playlist-info">
                                <h4 class="nvg-playlist-title"><?php echo esc_html(get_the_title($post_id)); ?></h4>
                                <span class="nvg-playlist-index"><?php echo esc_html($index + 1); ?> / <?php echo esc_html($total_videos); ?></span>
                            </div>
                        </div>
                    <?php
                        endforeach;
                        wp_reset_postdata();
                    endif;
                    ?>
                </div>
            </div>
        </div>
        
    </div>
    
</div>

<?php get_footer(); ?>