<?php
/**
 * Course Helper Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all lessons for a course
 */
function nvg_get_course_lessons($course_id) {
    $course_id = absint($course_id);

    if (!$course_id) {
        return array();
    }

    $lessons = get_field('lessons', $course_id);

    if (!$lessons || !is_array($lessons)) {
        return array();
    }

    $normalized_lessons = array();

    foreach ($lessons as $lesson) {
        $lesson_id = 0;

        if (is_object($lesson) && isset($lesson->ID)) {
            $lesson_id = (int) $lesson->ID;
        } elseif (is_array($lesson)) {
            if (!empty($lesson['ID'])) {
                $lesson_id = absint($lesson['ID']);
            } elseif (!empty($lesson['id'])) {
                $lesson_id = absint($lesson['id']);
            } elseif (!empty($lesson['lesson'])) {
                if (is_object($lesson['lesson']) && isset($lesson['lesson']->ID)) {
                    $lesson_id = (int) $lesson['lesson']->ID;
                } else {
                    $lesson_id = absint($lesson['lesson']);
                }
            } elseif (!empty($lesson['lesson_id'])) {
                $lesson_id = absint($lesson['lesson_id']);
            }
        } else {
            $lesson_id = absint($lesson);
        }

        if (!$lesson_id) {
            continue;
        }

        $lesson_post = get_post($lesson_id);
        if (!$lesson_post || $lesson_post->post_type !== 'lesson') {
            continue;
        }

        $normalized_lessons[] = $lesson_id;
    }

    $normalized_lessons = array_values(array_unique(array_map('absint', $normalized_lessons)));

    return $normalized_lessons;
}

/**
 * Get lesson by index in course
 */
function nvg_get_lesson_by_index($course_id, $index) {
    $lessons = nvg_get_course_lessons($course_id);

    if (!isset($lessons[$index])) {
        return null;
    }

    $lesson_id = absint($lessons[$index]);
    return get_post($lesson_id);
}

/**
 * Get current lesson index for a course
 */
function nvg_get_current_lesson_index($course_id, $lesson_id) {
    $lesson_id = absint($lesson_id);
    $lessons = nvg_get_course_lessons($course_id);

    foreach ($lessons as $index => $lid) {
        if ((int) $lid === $lesson_id) {
            return $index;
        }
    }

    return 0;
}

/**
 * Mark a lesson as complete for the current user
 */
function nvg_mark_lesson_complete($course_id, $lesson_id) {
    $course_id = absint($course_id);
    $lesson_id = absint($lesson_id);

    if (!$course_id || !$lesson_id) {
        return false;
    }

    $user_id = get_current_user_id();

    if (!$user_id) {
        return false;
    }

    $completed = get_user_meta($user_id, 'nvg_completed_lessons_' . $course_id, true);

    if (!is_array($completed)) {
        $completed = array();
    }

    $completed = array_values(array_unique(array_map('absint', $completed)));

    if (!in_array($lesson_id, $completed, true)) {
        $completed[] = $lesson_id;
        update_user_meta($user_id, 'nvg_completed_lessons_' . $course_id, $completed);
    }

    return true;
}

/**
 * Check if a lesson is completed by the current user
 */
function nvg_is_lesson_completed($course_id, $lesson_id) {
    $course_id = absint($course_id);
    $lesson_id = absint($lesson_id);

    if (!$course_id || !$lesson_id) {
        return false;
    }

    $user_id = get_current_user_id();

    if (!$user_id) {
        return false;
    }

    $completed = get_user_meta($user_id, 'nvg_completed_lessons_' . $course_id, true);

    if (!is_array($completed)) {
        return false;
    }

    return in_array($lesson_id, array_map('absint', $completed), true);
}

/**
 * Get course progress percentage
 */
function nvg_get_course_progress($course_id) {
    $course_id = absint($course_id);

    if (!$course_id) {
        return 0;
    }

    $user_id = get_current_user_id();

    if (!$user_id) {
        return 0;
    }

    $lessons = nvg_get_course_lessons($course_id);

    if (empty($lessons)) {
        return 0;
    }

    $completed = get_user_meta($user_id, 'nvg_completed_lessons_' . $course_id, true);

    if (!is_array($completed)) {
        $completed = array();
    }

    $total = count($lessons);
    $completed_ids = array_intersect($lessons, array_map('absint', $completed));
    $completed_count = count($completed_ids);

    return round(($completed_count / $total) * 100);
}

/**
 * Get next lesson in course
 */
function nvg_get_next_lesson($course_id, $current_lesson_id) {
    $current_lesson_id = absint($current_lesson_id);
    $lessons = nvg_get_course_lessons($course_id);

    foreach ($lessons as $index => $lid) {
        if ((int) $lid === $current_lesson_id && isset($lessons[$index + 1])) {
            return get_post(absint($lessons[$index + 1]));
        }
    }

    return null;
}

/**
 * Get lesson video URL from ACF
 */
function nvg_get_lesson_video($lesson_id) {
    return get_field('video_url', absint($lesson_id));
}

/**
 * Get lesson content
 */
function nvg_get_lesson_content($lesson_id) {
    $post = get_post(absint($lesson_id));
    return $post ? $post->post_content : '';
}

/**
 * Get Vimeo embed URL for lesson
 */
function nvg_get_lesson_vimeo_embed_url($lesson_id, $autoplay = false) {
    $video_url = nvg_get_lesson_video($lesson_id);

    if (!$video_url) {
        return false;
    }

    $video_id = nvg_get_vimeo_id($video_url);

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
 * Get all completed lessons for user in a course
 */
function nvg_get_completed_lessons($course_id) {
    $course_id = absint($course_id);
    $user_id = get_current_user_id();

    if (!$user_id || !$course_id) {
        return array();
    }

    $completed = get_user_meta($user_id, 'nvg_completed_lessons_' . $course_id, true);

    return is_array($completed) ? array_values(array_unique(array_map('absint', $completed))) : array();
}

/**
 * Render a course card for archive/search results
 */
function nvg_render_course_card($course_id, $is_logged_in = null) {
    $course_id = absint($course_id);

    if (!$course_id) {
        return;
    }

    if (null === $is_logged_in) {
        $is_logged_in = is_user_logged_in();
    }

    $lessons       = nvg_get_course_lessons($course_id);
    $lesson_count  = count($lessons);
    $can_access    = nvg_user_can_access_course($course_id);
    $progress      = $is_logged_in ? nvg_get_course_progress($course_id) : 0;
    $completed     = $is_logged_in ? count(nvg_get_completed_lessons($course_id)) : 0;
    $has_thumbnail = has_post_thumbnail($course_id);
    $course_url    = get_permalink($course_id);
    $excerpt       = get_the_excerpt($course_id);

    $first_lesson_url = '';
    if (!empty($lessons)) {
        $first_lesson_id = absint($lessons[0]);
        $first_lesson_url = add_query_arg('lesson', $first_lesson_id, $course_url);
    }

    if (!$can_access) {
        $cta_label = __('Unlock Course', 'netflix-video-gallery');
        $cta_url   = $course_url;
    } elseif ($is_logged_in && $progress > 0 && $progress < 100) {
        $cta_label = __('Continue', 'netflix-video-gallery');
        $cta_url   = $first_lesson_url ?: $course_url;
        $completed_lessons = nvg_get_completed_lessons($course_id);

        if (!empty($completed_lessons) && !empty($lessons)) {
            foreach (array_reverse($lessons) as $lid) {
                if (in_array(absint($lid), array_map('absint', $completed_lessons), true)) {
                    $next = nvg_get_next_lesson($course_id, absint($lid));
                    if ($next) {
                        $cta_url = add_query_arg('lesson', $next->ID, $course_url);
                    }
                    break;
                }
            }
        }
    } elseif ($is_logged_in && $progress >= 100) {
        $cta_label = __('Review', 'netflix-video-gallery');
        $cta_url   = $first_lesson_url ?: $course_url;
    } else {
        $cta_label = __('Start Course', 'netflix-video-gallery');
        $cta_url   = $first_lesson_url ?: $course_url;
    }
    ?>
    <div class="nvg-course-card" data-post-id="<?php echo esc_attr($course_id); ?>" data-can-access="<?php echo esc_attr($can_access ? '1' : '0'); ?>">
        <a href="<?php echo esc_url($cta_url); ?>" class="nvg-course-card-thumb-link nvg-course-card-link" tabindex="-1" aria-hidden="true">
            <div class="nvg-course-card-thumb">
                <?php if ($has_thumbnail) : ?>
                    <?php echo get_the_post_thumbnail($course_id, 'large', array('class' => 'nvg-course-thumb-img', 'alt' => esc_attr(get_the_title($course_id)))); ?>
                <?php else : ?>
                    <div class="nvg-course-thumb-placeholder">
                        <svg viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                            <path d="M19 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <div class="nvg-course-card-overlay">
                    <span class="nvg-btn nvg-btn-primary nvg-course-cta-btn" aria-hidden="true">
                        <?php echo esc_html($cta_label); ?>
                    </span>
                </div>

                <?php if ($is_logged_in && $progress >= 100) : ?>
                    <span class="nvg-course-badge nvg-badge-completed"><?php esc_html_e('Completed', 'netflix-video-gallery'); ?></span>
                <?php elseif ($is_logged_in && $progress > 0) : ?>
                    <span class="nvg-course-badge nvg-badge-in-progress"><?php esc_html_e('In Progress', 'netflix-video-gallery'); ?></span>
                <?php elseif (!$can_access) : ?>
                    <span class="nvg-course-badge nvg-badge-locked"><?php esc_html_e('Members Only', 'netflix-video-gallery'); ?></span>
                <?php endif; ?>
            </div>
        </a>

        <div class="nvg-course-card-body">
            <div class="nvg-course-card-meta">
                <span class="nvg-course-lesson-count">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/>
                    </svg>
                    <?php
                    echo esc_html(sprintf(
                        _n('%d Lesson', '%d Lessons', $lesson_count, 'netflix-video-gallery'),
                        $lesson_count
                    ));
                    ?>
                </span>
            </div>

            <h2 class="nvg-course-card-title">
                <a href="<?php echo esc_url($cta_url); ?>" class="nvg-course-card-link"><?php echo esc_html(get_the_title($course_id)); ?></a>
            </h2>

            <?php if ($excerpt) : ?>
                <p class="nvg-course-card-excerpt"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>

            <?php if ($is_logged_in && $lesson_count > 0) : ?>
                <div class="nvg-course-card-progress">
                    <div class="nvg-course-progress-bar">
                        <div class="nvg-course-progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
                    </div>
                    <div class="nvg-course-progress-text">
                        <span>
                            <?php
                            echo esc_html(sprintf(
                                __('%d/%d lessons', 'netflix-video-gallery'),
                                $completed,
                                $lesson_count
                            ));
                            ?>
                        </span>
                        <span><?php echo esc_html($progress); ?>%</span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="nvg-course-card-footer">
                <a href="<?php echo esc_url($cta_url); ?>" class="nvg-btn nvg-btn-primary nvg-course-card-btn nvg-course-card-link">
                    <?php echo esc_html($cta_label); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}
