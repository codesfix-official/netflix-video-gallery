(function($) {
    'use strict';

    $(document).ready(function() {
        initCourseArchiveSearch();
    });

    function initCourseArchiveSearch() {
        const $input = $('#nvg-course-search-input');
        const $status = $('#nvg-course-search-status');
        const $results = $('#nvg-course-results');

        if (!$input.length || !$results.length) {
            return;
        }

        let debounceTimer = null;
        let activeRequest = null;

        const renderEmpty = function() {
            return '' +
                '<div class="nvg-courses-empty">' +
                    '<svg viewBox="0 0 24 24" fill="currentColor" width="64" height="64">' +
                        '<path d="M19 3H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>' +
                    '</svg>' +
                    '<h2>No courses found</h2>' +
                    '<p>Try a different keyword.</p>' +
                '</div>';
        };

        const requestCourses = function(searchTerm, paged) {
            if (activeRequest && activeRequest.readyState !== 4) {
                activeRequest.abort();
            }

            $results.addClass('nvg-search-loading');
            $status.text('Searching...');

            activeRequest = $.ajax({
                url: nvgAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'nvg_search_courses',
                    nonce: nvgAjax.nonce,
                    search: searchTerm,
                    paged: paged || 1
                }
            });

            activeRequest.done(function(response) {
                if (!response || !response.success || !response.data) {
                    $status.text('Search failed.');
                    return;
                }

                const html = response.data.html ? response.data.html : '';
                const pagination = response.data.pagination ? response.data.pagination : '';
                const found = typeof response.data.found === 'number' ? response.data.found : 0;

                let out = '';
                if (html.trim().length) {
                    out += '<div class="nvg-courses-grid" id="nvg-courses-grid">' + html + '</div>';
                } else {
                    out += renderEmpty();
                }

                if (pagination.trim().length) {
                    out += '<nav class="nvg-pagination" id="nvg-courses-pagination" aria-label="Courses navigation">' + pagination + '</nav>';
                }

                $results.html(out);

                if (searchTerm) {
                    $status.text(found + ' result' + (found === 1 ? '' : 's') + ' found');
                } else {
                    $status.text('');
                }
            });

            activeRequest.fail(function(xhr, status) {
                if (status !== 'abort') {
                    $status.text('Search failed.');
                }
            });

            activeRequest.always(function() {
                $results.removeClass('nvg-search-loading');
            });
        };

        $input.on('input', function() {
            const term = $.trim($(this).val());
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                requestCourses(term, 1);
            }, 300);
        });

        $results.on('click', '.nvg-pagination .page-numbers', function(e) {
            const href = $(this).attr('href');
            const term = $.trim($input.val());

            if (!href || href === '#') {
                return;
            }

            e.preventDefault();

            const pageMatch = href.match(/(?:paged=|\/page\/)(\d+)/);
            const page = pageMatch ? parseInt(pageMatch[1], 10) : 1;

            requestCourses(term, page);

            const top = $('.nvg-courses-archive-header').offset();
            if (top && typeof top.top !== 'undefined') {
                $('html, body').animate({ scrollTop: Math.max(top.top - 20, 0) }, 250);
            }
        });
    }
})(jQuery);
