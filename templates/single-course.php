<?php
/**
 * Template for Single Course
 */

get_header();

while (have_posts()) : the_post();
    $course_id = get_the_ID();
    $can_access_course = nvg_user_can_access_course($course_id);
    $show_course_paywall = !$can_access_course;
    $lessons = nvg_get_course_lessons($course_id);

    // Get current lesson from URL or use first lesson.
    $requested_lesson_id = isset($_GET['lesson']) ? absint($_GET['lesson']) : 0;
    $current_lesson_id = 0;

    if (!empty($lessons)) {
        $current_lesson_id = in_array($requested_lesson_id, $lessons, true) ? $requested_lesson_id : absint($lessons[0]);
    }

    $current_lesson = $current_lesson_id ? get_post($current_lesson_id) : null;
    $current_lesson_index = $current_lesson_id ? nvg_get_current_lesson_index($course_id, $current_lesson_id) : 0;
    $is_lesson_completed = $current_lesson_id ? nvg_is_lesson_completed($course_id, $current_lesson_id) : false;
    $progress = nvg_get_course_progress($course_id);
    $next_lesson = $current_lesson_id ? nvg_get_next_lesson($course_id, $current_lesson_id) : null;
?>

<div class="nvg-course-wrapper">

    <?php if (!$can_access_course) : ?>
        <div class="nvg-course-header">
            <div class="nvg-container">
                <a class="nvg-back-btn" href="javascript:history.back()" aria-label="Go back">
                    <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    Back
                </a>
                <h1 class="nvg-course-title"><?php the_title(); ?></h1>
            </div>
        </div>

        <div class="nvg-container">
            <div class="nvg-no-video nvg-restricted-video" data-post-id="<?php echo esc_attr($course_id); ?>" <?php echo $show_course_paywall ? 'data-nvg-auto-paywall="1"' : ''; ?>>
                <p><?php esc_html_e('This course is available for members or individual purchase.', 'netflix-video-gallery'); ?></p>
                <?php if ($show_course_paywall) : ?>
                    <p>
                        <button type="button" class="nvg-btn nvg-btn-primary nvg-open-paywall-popup" data-post-id="<?php echo esc_attr($course_id); ?>">
                            <?php esc_html_e('View Plans or Buy Course', 'netflix-video-gallery'); ?>
                        </button>
                    </p>
                <?php else : ?>
                    <p><?php echo wp_kses_post(nvg_get_video_restriction_message($course_id)); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php else : ?>

    <!-- Course Header -->
    <div class="nvg-course-header">
        <div class="nvg-container">
            <a class="nvg-back-btn" href="javascript:history.back()" aria-label="Go back">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                Back
            </a>
            <h1 class="nvg-course-title"><?php the_title(); ?></h1>
            
            <!-- Progress Bar -->
            <div class="nvg-progress-container">
                <div class="nvg-progress-label">
                    <span>Progress</span>
                    <span class="nvg-progress-percent"><?php echo esc_html($progress); ?>%</span>
                </div>
                <div class="nvg-progress-bar">
                    <div class="nvg-progress-fill" style="width: <?php echo esc_attr($progress); ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Content Layout (70/30) -->
    <div class="nvg-course-layout">
        
        <!-- Left Sidebar: Lessons List (30%) -->
        <div class="nvg-lessons-sidebar">
            <div class="nvg-lessons-header">
                <h3>Course Lessons</h3>
                <span class="nvg-lessons-count"><?php echo count($lessons); ?> lessons</span>
            </div>
            
            <div class="nvg-lessons-list">
                <?php 
                foreach ($lessons as $index => $lesson_id) :
                    $lesson_id = absint($lesson_id);
                    $lesson = get_post($lesson_id);
                    if (!$lesson || $lesson->post_type !== 'lesson') {
                        continue;
                    }

                    $is_active = ($lesson_id === absint($current_lesson_id));
                    $is_completed = nvg_is_lesson_completed($course_id, $lesson_id);
                    $lesson_class = $is_active ? 'active' : '';
                    $lesson_class .= $is_completed ? ' completed' : '';
                ?>
                    <a href="<?php echo esc_url(add_query_arg('lesson', $lesson_id, get_permalink())); ?>" 
                       class="nvg-lesson-item <?php echo esc_attr(trim($lesson_class)); ?>"
                       data-lesson-id="<?php echo esc_attr($lesson_id); ?>">
                        <div class="nvg-lesson-number">
                            <?php if ($is_completed) : ?>
                                <svg class="nvg-checkmark" viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="currentColor" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            <?php else : ?>
                                <span><?php echo $index + 1; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="nvg-lesson-info">
                            <h4><?php echo esc_html($lesson->post_title); ?></h4>
                            <span class="nvg-lesson-status">
                                <?php echo $is_completed ? 'Completed' : 'Incomplete'; ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Content Area: Lesson Content (70%) -->
        <div class="nvg-lesson-content-area">
            <?php if ($current_lesson) : 
                $video_url = nvg_get_lesson_video($current_lesson_id);
                $embed_url = nvg_get_lesson_vimeo_embed_url($current_lesson_id);
            ?>
                <!-- Video Player -->
                <div class="nvg-lesson-player">
                    <div class="nvg-lesson-player-container">
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
                </div>

                <!-- Lesson Title -->
                <div class="nvg-lesson-header">
                    <h2 class="nvg-lesson-title"><?php echo esc_html($current_lesson->post_title); ?></h2>
                    <span class="nvg-lesson-counter"><?php echo ($current_lesson_index + 1); ?> / <?php echo count($lessons); ?></span>
                </div>

                <!-- Lesson Content -->
                <?php if ($current_lesson->post_content) : ?>
                    <div class="nvg-lesson-description">
                        <?php echo wp_kses_post($current_lesson->post_content); ?>
                    </div>
                <?php endif; ?>

                <!-- Lesson Actions -->
                <div class="nvg-lesson-actions">
                    <button class="nvg-btn nvg-btn-primary nvg-mark-complete-btn" 
                            data-course-id="<?php echo esc_attr($course_id); ?>"
                            data-lesson-id="<?php echo esc_attr($current_lesson_id); ?>"
                            <?php echo $is_lesson_completed ? 'disabled' : ''; ?>>
                        <?php echo $is_lesson_completed ? '✓ Completed' : 'Mark as Complete'; ?>
                    </button>

                    <?php if ($next_lesson) : ?>
                        <a href="<?php echo esc_url(add_query_arg('lesson', $next_lesson->ID, get_permalink())); ?>" 
                           class="nvg-btn nvg-btn-secondary">
                            Next Lesson →
                        </a>
                    <?php endif; ?>
                </div>

            <?php else : ?>
                <div class="nvg-no-content">
                    <p>No lessons in this course</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <?php endif; ?>

</div>

<?php
endwhile;
get_footer();
