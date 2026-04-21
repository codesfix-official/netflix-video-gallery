<?php
/**
 * Template for Video Gallery Archive
 */

get_header();
?>

<div class="nvg-archive-wrapper">
    
    <!-- Hero Section - Featured Videos -->
    <section class="nvg-hero-section">
        <?php
        $featured_args = array(
            'post_type'      => 'video-gallery',
            'posts_per_page' => 5,
            'meta_query'     => array(
                array(
                    'key'     => 'featured',
                    'value'   => array('Yes', '1', true),
                    'compare' => 'IN',
                ),
            ),
        );
        
        $featured_query = new WP_Query($featured_args);
        
        if ($featured_query->have_posts()) :
        ?>
            <div class="swiper nvg-hero-slider">
                <div class="swiper-wrapper">
                    <?php while ($featured_query->have_posts()) : $featured_query->the_post(); 
                        $video_url = get_field('video_url');
                        $video_id = nvg_get_vimeo_id($video_url);
                        $thumbnail = nvg_get_video_thumbnail(get_the_ID());
                        $short_desc = get_field('short_description');
                    ?>
                        <div class="swiper-slide">
                            <div class="nvg-hero-slide" style="background-image: url('<?php echo esc_url($thumbnail); ?>')">
                                <div class="nvg-hero-overlay"></div>
                                <div class="nvg-hero-content">
                                    <h1 class="nvg-hero-title"><?php the_title(); ?></h1>
                                    <?php if ($short_desc) : ?>
                                        <p class="nvg-hero-description"><?php echo esc_html($short_desc); ?></p>
                                    <?php endif; ?>
                                    <div class="nvg-hero-buttons">
                                        <a href="<?php the_permalink(); ?>" class="nvg-btn nvg-btn-primary">
                                            <svg viewBox="0 0 24 24" width="24" height="24">
                                                <path fill="currentColor" d="M8 5v14l11-7z"/>
                                            </svg>
                                            Play Now
                                        </a>
                                        <a href="<?php the_permalink(); ?>" class="nvg-btn nvg-btn-secondary">
                                            More Info
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-button-next"></div>
            </div>
        <?php 
            wp_reset_postdata();
        endif; 
        ?>
    </section>
    
    <!-- Filter Section -->
    <section class="nvg-filter-section">
        <div class="nvg-container">
            <div class="nvg-filters">
                <select id="nvg-category-filter" class="nvg-filter-select">
                    <option value="all">All Categories</option>
                    <?php
                    $categories = get_terms(array(
                        'taxonomy'   => 'video-category',
                        'hide_empty' => true,
                    ));
                    
                    foreach ($categories as $category) :
                    ?>
                        <option value="<?php echo esc_attr($category->slug); ?>">
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label class="nvg-filter-checkbox">
                    <input type="checkbox" id="nvg-free-filter" value="yes">
                    <span>Free Videos Only</span>
                </label>
            </div>
        </div>
    </section>
    
    <!-- Category Rows -->
    <section class="nvg-categories-section">
        <div class="nvg-container">
            <?php
            $categories = get_terms(array(
                'taxonomy'   => 'video-category',
                'hide_empty' => true,
            ));
            
            foreach ($categories as $category) :
                $category_videos = new WP_Query(array(
                    'post_type'      => 'video-gallery',
                    'posts_per_page' => 12,
                    'tax_query'      => array(
                        array(
                            'taxonomy' => 'video-category',
                            'field'    => 'term_id',
                            'terms'    => $category->term_id,
                        ),
                    ),
                ));
                
                if ($category_videos->have_posts()) :
            ?>
                <div class="nvg-category-row" data-category="<?php echo esc_attr($category->slug); ?>">
                    <div class="nvg-category-header">
                        <h2 class="nvg-category-title">
                            <a href="<?php echo get_term_link($category); ?>">
                                <?php echo esc_html($category->name); ?>
                            </a>
                        </h2>
                        <a href="<?php echo get_term_link($category); ?>" class="nvg-view-all">
                            View All <span>→</span>
                        </a>
                    </div>
                    
                    <div class="swiper nvg-category-slider">
                        <div class="swiper-wrapper">
                            <?php while ($category_videos->have_posts()) : $category_videos->the_post(); ?>
                                <div class="swiper-slide">
                                    <?php nvg_render_video_card(get_the_ID()); ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            <?php
                    wp_reset_postdata();
                endif;
            endforeach;
            ?>
        </div>
    </section>
    
    <!-- Free Videos Section -->
    <section class="nvg-free-section">
        <div class="nvg-container">
            <?php
            $free_videos = new WP_Query(array(
                'post_type'      => 'video-gallery',
                'posts_per_page' => 12,
                'meta_query'     => array(
                    array(
                        'key'     => 'is_free',
                        'value'   => array('Yes', '1', true),
                        'compare' => 'IN',
                    ),
                ),
            ));
            
            if ($free_videos->have_posts()) :
            ?>
                <div class="nvg-category-row">
                    <div class="nvg-category-header">
                        <h2 class="nvg-category-title">Free Videos</h2>
                    </div>
                    
                    <div class="swiper nvg-category-slider">
                        <div class="swiper-wrapper">
                            <?php while ($free_videos->have_posts()) : $free_videos->the_post(); ?>
                                <div class="swiper-slide">
                                    <?php nvg_render_video_card(get_the_ID()); ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    </div>
                </div>
            <?php
                wp_reset_postdata();
            endif;
            ?>
        </div>
    </section>
    
</div>

<?php get_footer(); ?>