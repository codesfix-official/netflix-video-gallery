(function($) {
    'use strict';

    $(document).ready(function() {
        initMarkCompleteButton();
    });

    /**
     * Initialize Mark Complete Button
     */
    function initMarkCompleteButton() {
        $('.nvg-mark-complete-btn').on('click', function() {
            const $btn = $(this);
            
            if ($btn.prop('disabled')) {
                return;
            }

            const courseId = $btn.data('course-id');
            const lessonId = $btn.data('lesson-id');

            $.ajax({
                url: nvgAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'nvg_mark_lesson_complete',
                    nonce: nvgAjax.nonce,
                    course_id: courseId,
                    lesson_id: lessonId,
                },
                success: function(response) {
                    if (response.success) {
                        // Update button state
                        $btn.prop('disabled', true);
                        $btn.text('✓ Completed');
                        $btn.addClass('completed');

                        // Update sidebar lesson status
                        $('[data-lesson-id="' + lessonId + '"]')
                            .addClass('completed')
                            .find('.nvg-lesson-status')
                            .text('Completed');

                        // Update progress bar
                        const progress = response.data.progress;
                        $('.nvg-progress-fill').css('width', progress + '%');
                        $('.nvg-progress-percent').text(progress + '%');

                        // Show success message
                        showNotification('Lesson marked as complete!');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    showNotification('Error marking lesson as complete', 'error');
                }
            });
        });
    }

    /**
     * Show notification message
     */
    function showNotification(message, type = 'success') {
        const notifClass = type === 'error' ? 'nvg-notification-error' : 'nvg-notification-success';
        const $notification = $('<div class="nvg-notification ' + notifClass + '">' + message + '</div>');

        $('body').append($notification);

        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

})(jQuery);
