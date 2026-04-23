<?php
/**
 * Template for Single Video
 */

get_header();

while (have_posts()) : the_post();
    $video_url = get_field('video_url');
    $video_id = nvg_get_vimeo_id($video_url);
    $embed_url = nvg_get_vimeo_embed_url($video_url);
    $short_desc = get_field('short_description');
    $is_free = nvg_is_free_video(get_the_ID());
    $categories = get_the_terms(get_the_ID(), 'video-category');
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
            <?php if ($embed_url) : ?>
                <iframe src="<?php echo esc_url($embed_url); ?>" 
                        width="100%" 
                        height="100%" 
                        frameborder="0" 
                        allow="autoplay; fullscreen; picture-in-picture" 
                        allowfullscreen>
                </iframe>
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
            
            <?php if (get_the_content()) : ?>
                <div class="nvg-single-content">
                    <?php the_content(); ?>
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