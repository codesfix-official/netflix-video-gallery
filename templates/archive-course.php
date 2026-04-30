<?php
/**
 * Template for Course Archive (Grid Layout)
 */

get_header();

$is_logged_in = is_user_logged_in();
?>

<div class="nvg-courses-archive-wrapper">

    <!-- Page Header -->
    <div class="nvg-courses-archive-header">
        <div class="nvg-container">
            <h1 class="nvg-courses-archive-title">
                <?php esc_html_e('All Courses', 'netflix-video-gallery'); ?>
            </h1>
            <?php if ($is_logged_in) : ?>
                <p class="nvg-courses-archive-subtitle">
                    <?php esc_html_e('Continue your learning journey', 'netflix-video-gallery'); ?>
                </p>
            <?php else : ?>
                <p class="nvg-courses-archive-subtitle">
                    <?php esc_html_e('Browse our courses and start learning today', 'netflix-video-gallery'); ?>
                </p>
            <?php endif; ?>

            <div class="nvg-course-search-wrap">
                <label class="screen-reader-text" for="nvg-course-search-input"><?php esc_html_e('Search courses', 'netflix-video-gallery'); ?></label>
                <input
                    type="search"
                    id="nvg-course-search-input"
                    class="nvg-course-search-input"
                    placeholder="<?php esc_attr_e('Search courses...', 'netflix-video-gallery'); ?>"
                    autocomplete="off"
                />
                <span class="nvg-course-search-status" id="nvg-course-search-status" aria-live="polite"></span>
            </div>
        </div>
    </div>

    <!-- Courses Grid -->
    <div class="nvg-container">
        <div id="nvg-course-results" data-per-page="12">
            <?php if (have_posts()) : ?>
                <div class="nvg-courses-grid" id="nvg-courses-grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <?php nvg_render_course_card(get_the_ID(), $is_logged_in); ?>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php
                $pagination = paginate_links(array(
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'type'      => 'array',
                ));
                if ($pagination) : ?>
                    <nav class="nvg-pagination" id="nvg-courses-pagination" aria-label="<?php esc_attr_e('Courses navigation', 'netflix-video-gallery'); ?>">
                        <?php echo implode("\n", array_map('wp_kses_post', $pagination)); ?>
                    </nav>
                <?php endif; ?>

            <?php else : ?>
                <div class="nvg-courses-empty" id="nvg-courses-empty">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="64" height="64">
                        <path d="M19 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                    <h2><?php esc_html_e('No courses found', 'netflix-video-gallery'); ?></h2>
                    <p><?php esc_html_e('Check back soon for new content.', 'netflix-video-gallery'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php get_footer(); ?>
