<?php
/**
 * Template for Video Category (70/30 Layout with Auto-play)
 */

get_header();

$term = get_queried_object();

// Get videos in this category
$args = array(
    'post_type'      => 'video-gallery',
    'posts_per_page' => -1,
    'tax_query'      => array(
        array(
            'taxonomy' => 'video-category',
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ),
    ),
);

// Check for featured video
$featured_args = $args;
$featured_args['posts_per_page'] = 1;
$featured_args['meta_query'] = array(
    array(
        'key'     => 'featured',
        'value'   => array('Yes', '1', true),
        'compare' => 'IN',
    ),
);

$featured_query = new WP_Query($featured_args);
$videos_query = new WP_Query($args);

// Determine initial video
$initial_video = null;
if ($featured_query->have_posts()) {
    $featured_query->the_post();
    $initial_video = get_post();
    wp_reset_postdata();
} elseif ($videos_query->have_posts()) {
    $videos_query->the_post();
    $initial_video = get_post();
    wp_reset_postdata();
}

?>

<div class="nvg-category-page">
    
    <!-- Page Header -->
    <div class="nvg-category-page-header">
        <div class="nvg-container">
            <h1 class="nvg-page-title"><?php echo esc_html($term->name); ?></h1>
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
            <?php endif; ?>
        </div>
        
        <!-- Right Section: Playlist (30%) -->
        <div class="nvg-playlist-section">
            <div class="nvg-playlist-header">
                <h3>Playlist</h3>
                <span class="nvg-playlist-count">
                    <?php echo $videos_query->post_count; ?> videos
                </span>
            </div>
            
            <div class="nvg-playlist-wrapper">
                <div id="nvg-playlist" class="nvg-playlist">
                    <?php
                    if ($videos_query->have_posts()) :
                        $index = 0;
                        while ($videos_query->have_posts()) : $videos_query->the_post();
                            $post_id = get_the_ID();
                            $video_url = get_field('video_url', $post_id);
                            $video_id = nvg_get_vimeo_id($video_url);
                            $thumbnail = nvg_get_video_thumbnail($post_id, 'medium');
                            $is_free = nvg_is_free_video($post_id);
                            $is_active = ($initial_video && $post_id === $initial_video->ID) ? 'active' : '';
                    ?>
                        <div class="nvg-playlist-item <?php echo $is_active; ?>" 
                             data-video-id="<?php echo esc_attr($video_id); ?>"
                             data-post-id="<?php echo esc_attr($post_id); ?>"
                             data-index="<?php echo esc_attr($index); ?>">
                            <div class="nvg-playlist-thumbnail">
                                <img src="<?php echo esc_url($thumbnail); ?>" 
                                     alt="<?php the_title_attribute(); ?>"
                                     loading="lazy">
                                <div class="nvg-playlist-play-icon">
                                    <svg viewBox="0 0 24 24" width="30" height="30">
                                        <path fill="currentColor" d="M8 5v14l11-7z"/>
                                    </svg>
                                </div>
                                <?php if ($is_free) : ?>
                                    <span class="nvg-playlist-free-badge">FREE</span>
                                <?php endif; ?>
                            </div>
                            <div class="nvg-playlist-info">
                                <h4 class="nvg-playlist-title"><?php the_title(); ?></h4>
                                <span class="nvg-playlist-index"><?php echo ($index + 1); ?> / <?php echo $videos_query->post_count; ?></span>
                            </div>
                        </div>
                    <?php
                        $index++;
                        endwhile;
                        wp_reset_postdata();
                    endif;
                    ?>
                </div>
            </div>
        </div>
        
    </div>
    
</div>

<?php get_footer(); ?>