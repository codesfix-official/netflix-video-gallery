(function($) {
    'use strict';

    let lastFocusedElement = null;
    let activeContentId = 0;

    $(document).ready(function() {
        initPaywallPopup();
    });

    function initPaywallPopup() {
        const $modal = $('#nvg-paywall-modal');
        const $singleOffer = $('#nvg-paywall-single-offer');
        const $singleLabel = $('#nvg-paywall-single-label');
        const $singlePrice = $('#nvg-paywall-single-price');
        const $singleBuy = $('#nvg-paywall-single-buy');

        if (!$modal.length) {
            return;
        }

        function hideSingleOffer() {
            if ($singleOffer.length) {
                $singleOffer.prop('hidden', true);
            }
            if ($singleLabel.length) {
                $singleLabel.text('Buy This Item');
            }
            if ($singlePrice.length) {
                $singlePrice.text('');
            }
            if ($singleBuy.length) {
                $singleBuy.attr('href', '#');
            }
        }

        function loadSingleOffer(postId) {
            if (!postId || !$singleOffer.length) {
                hideSingleOffer();
                return;
            }

            $.ajax({
                url: nvgAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'nvg_get_purchase_offer',
                    nonce: nvgAjax.nonce,
                    post_id: postId
                }
            }).done(function(response) {
                if (!response || !response.success || !response.data || !response.data.enabled) {
                    hideSingleOffer();
                    return;
                }

                const label = response.data.post_type === 'course' ? 'Buy This Course' : 'Buy This Video';
                $singleLabel.text(label);
                $singlePrice.html(response.data.price_html || '');
                $singleBuy.attr('href', response.data.add_to_cart_url || response.data.checkout_url || '#');
                $singleOffer.prop('hidden', false);
            }).fail(function() {
                hideSingleOffer();
            });
        }

        function openModal(postId) {
            lastFocusedElement = document.activeElement;
            activeContentId = postId ? parseInt(postId, 10) : 0;
            $modal.addClass('is-open').attr('aria-hidden', 'false');
            $('body').addClass('nvg-modal-open');
            $modal.find('.nvg-paywall-close').trigger('focus');
            loadSingleOffer(activeContentId);
        }

        function closeModal() {
            $modal.removeClass('is-open').attr('aria-hidden', 'true');
            $('body').removeClass('nvg-modal-open');
            activeContentId = 0;
            hideSingleOffer();
            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        }

        window.NVGPaywall = {
            open: openModal,
            close: closeModal
        };

        // Open popup when a viewer clicks a paid card they cannot access.
        $(document).on('click', '.nvg-video-card[data-is-free="0"] .nvg-card-link', function(e) {
            const $card = $(this).closest('.nvg-video-card');
            if ($card.data('can-watch') === 1 || $card.data('can-watch') === '1') {
                return;
            }

            e.preventDefault();
            const postId = $card.data('post-id') || 0;
            openModal(postId);
        });

        $(document).on('click', '.nvg-course-card-link', function(e) {
            const $card = $(this).closest('.nvg-course-card');
            if (!$card.length) {
                return;
            }

            if ($card.data('can-access') === 1 || $card.data('can-access') === '1') {
                return;
            }

            e.preventDefault();
            const postId = $card.data('post-id') || 0;
            openModal(postId);
        });

        // Open from custom trigger (used by category playlist)
        $(document).on('nvg:openPaywallPopup', function(event, data) {
            const postId = data && data.postId ? data.postId : 0;
            openModal(postId);
        });

        // Manual trigger button (single video restricted state)
        $(document).on('click', '.nvg-open-paywall-popup', function(e) {
            e.preventDefault();
            const postId = $(this).data('post-id') || $(this).closest('.nvg-restricted-video').data('post-id') || 0;
            openModal(postId);
        });

        // Add individual item to cart via AJAX (stay on current page).
        $(document).on('click', '#nvg-paywall-single-buy', function(e) {
            const postId = activeContentId || 0;
            if (!postId) {
                return;
            }

            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: nvgAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'nvg_add_purchase_to_cart',
                    nonce: nvgAjax.nonce,
                    post_id: postId
                }
            }).done(function(response) {
                if (!response || !response.success) {
                    const message = response && response.data && response.data.message
                        ? response.data.message
                        : 'Could not add this item to cart.';
                    alert(message);
                    return;
                }

                // Ask WooCommerce to refresh cart fragments (mini-cart/count) without page reload.
                $(document.body).trigger('wc_fragment_refresh');

                closeModal();
            }).fail(function() {
                alert('Could not add this item to cart.');
            }).always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        });

        // Auto-open when a viewer directly lands on a restricted paid item.
        const $autoPaywall = $('.nvg-restricted-video[data-nvg-auto-paywall="1"]');
        if ($autoPaywall.length) {
            const postId = $autoPaywall.first().data('post-id') || 0;
            setTimeout(function() {
                openModal(postId);
            }, 120);
        }

        // Close handlers
        $modal.on('click', '[data-nvg-paywall-close]', function() {
            closeModal();
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $modal.hasClass('is-open')) {
                closeModal();
            }
        });
    }
})(jQuery);
