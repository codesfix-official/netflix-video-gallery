(function($) {
    'use strict';
    
    let playerElement;
    let currentIndex = 0;
    let playlist = [];
    
    $(document).ready(function() {
        initPlayer();
        initPlaylist();
        initPopState();
    });
    
    /**
     * Initialize Vimeo Player
     */
    function initPlayer() {
        const $playerElement = $('#nvg-video-player');
        
        if (!$playerElement.length) {
            return;
        }

        playerElement = $playerElement.get(0);
    }
    
    /**
     * Initialize Playlist
     */
    function initPlaylist() {
        const $playlistItems = $('.nvg-playlist-item');
        
        if (!$playlistItems.length) {
            return;
        }
        
        // Build playlist array
        playlist = [];
        $playlistItems.each(function(index) {
            const $item = $(this);
            playlist.push({
                postId: $item.data('post-id'),
                canWatch: Number($item.data('can-watch')) === 1,
                index: index,
                element: $item,
            });
            
            // Set current index if this is active
            if ($item.hasClass('active')) {
                currentIndex = index;
            }
        });
        
        // Attach click handlers
        $playlistItems.on('click', function() {
            const index = $(this).data('index');
            playVideoByIndex(index);
        });
    }
    
    /**
     * Play video by playlist index
     */
    function playVideoByIndex(index) {
        if (!playlist[index]) {
            console.error('Invalid playlist index:', index);
            return;
        }
        
        const video = playlist[index];

        if (!video.canWatch) {
            if (window.NVGPaywall) {
                $(document).trigger('nvg:openPaywallPopup', { postId: video.postId });
                return;
            }

            alert('This video is available to members only.');
            return;
        }
        
        // Load video data via AJAX
        $.ajax({
            url: nvgAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'nvg_get_video_data',
                nonce: nvgAjax.nonce,
                post_id: video.postId,
            },
            success: function(response) {
                if (!response.success) {
                    if (window.NVGPaywall) {
                        $(document).trigger('nvg:openPaywallPopup', { postId: video.postId });
                        return;
                    }

                    const message = response.data && response.data.message
                        ? response.data.message
                        : 'This video is not available for your account.';
                    alert(message);
                    return;
                }

                currentIndex = index;

                // Update active state only when playback is allowed.
                $('.nvg-playlist-item').removeClass('active');
                video.element.addClass('active');

                // Scroll to active item
                scrollToActiveItem();

                updatePlayerContent(response.data);
                loadVideoInPlayer(response.data.embed_url || '');
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }
    
    /**
     * Load video in player
     */
    function loadVideoInPlayer(embedUrl) {
        if (!playerElement) {
            console.error('Player not initialized');
            return;
        }

        if (!embedUrl) {
            console.error('No embed URL provided');
            return;
        }

        playerElement.setAttribute('src', embedUrl);
    }
    
    /**
     * Update player content (title, description)
     */
    function updatePlayerContent(data) {
        $('#nvg-current-title').text(data.title);
        
        if (data.description) {
            $('#nvg-current-description').text(data.description);
        }
        
        // Update URL without reload
        if (history.pushState && data.permalink) {
            history.pushState({ postId: data.id, videoId: data.video_id }, data.title, data.permalink);
        }
    }
    
    /**
     * Scroll to active playlist item
     */
    function scrollToActiveItem() {
        const $activeItem = $('.nvg-playlist-item.active');
        const $playlist = $('.nvg-playlist');
        
        if ($activeItem.length && $playlist.length) {
            const itemTop = $activeItem.position().top;
            const playlistScrollTop = $playlist.scrollTop();
            const playlistHeight = $playlist.height();
            const itemHeight = $activeItem.outerHeight();
            
            // Scroll if item is not visible
            if (itemTop < 0 || itemTop + itemHeight > playlistHeight) {
                $playlist.animate({
                    scrollTop: playlistScrollTop + itemTop - (playlistHeight / 2) + (itemHeight / 2)
                }, 300);
            }
        }
    }
    
    /**
     * Handle browser back/forward navigation
     */
    function initPopState() {
        $(window).on('popstate', function(e) {
            var state = e.originalEvent.state;
            if (state && state.postId) {
                var index = playlist.findIndex(function(item) {
                    return item.postId === state.postId;
                });
                if (index !== -1) {
                    playVideoByIndex(index);
                    return;
                }
            }
            window.location.reload();
        });
    }

})(jQuery);