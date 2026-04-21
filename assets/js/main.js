(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initHeroSlider();
        initCategorySliders();
        initRelatedSlider();
        initFilters();
        initCardHover();
    });
    
    /**
     * Initialize Hero Slider
     */
    function initHeroSlider() {
        if ($('.nvg-hero-slider').length) {
            new Swiper('.nvg-hero-slider', {
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
                effect: 'fade',
                fadeEffect: {
                    crossFade: true
                },
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
            });
        }
    }
    
    /**
     * Initialize Category Sliders
     */
    function initCategorySliders() {
        $('.nvg-category-slider').each(function() {
            new Swiper(this, {
                slidesPerView: 2,
                spaceBetween: 15,
                navigation: {
                    nextEl: $(this).closest('.nvg-category-row').find('.swiper-button-next')[0],
                    prevEl: $(this).closest('.nvg-category-row').find('.swiper-button-prev')[0],
                },
                breakpoints: {
                    640: {
                        slidesPerView: 3,
                        spaceBetween: 15,
                    },
                    768: {
                        slidesPerView: 4,
                        spaceBetween: 20,
                    },
                    1024: {
                        slidesPerView: 5,
                        spaceBetween: 20,
                    },
                    1280: {
                        slidesPerView: 6,
                        spaceBetween: 20,
                    },
                },
            });
        });
    }
    
    /**
     * Initialize Related Videos Slider
     */
    function initRelatedSlider() {
        if ($('.nvg-related-slider').length) {
            new Swiper('.nvg-related-slider', {
                slidesPerView: 2,
                spaceBetween: 15,
                navigation: {
                    nextEl: '.nvg-related-section .swiper-button-next',
                    prevEl: '.nvg-related-section .swiper-button-prev',
                },
                breakpoints: {
                    640: {
                        slidesPerView: 3,
                        spaceBetween: 15,
                    },
                    768: {
                        slidesPerView: 4,
                        spaceBetween: 20,
                    },
                    1024: {
                        slidesPerView: 5,
                        spaceBetween: 20,
                    },
                    1280: {
                        slidesPerView: 6,
                        spaceBetween: 20,
                    },
                },
            });
        }
    }
    
    /**
     * Initialize Filters
     */
    function initFilters() {
        const $categoryFilter = $('#nvg-category-filter');
        const $freeFilter = $('#nvg-free-filter');
        
        if (!$categoryFilter.length && !$freeFilter.length) {
            return;
        }
        
        // Category filter change
        $categoryFilter.on('change', function() {
            filterVideos();
        });
        
        // Free videos filter change
        $freeFilter.on('change', function() {
            filterVideos();
        });
        
        function filterVideos() {
            const category = $categoryFilter.val();
            const isFree = $freeFilter.is(':checked') ? 'yes' : '';
            
            // Show all rows first
            $('.nvg-category-row').show();
            
            // Filter by category
            if (category !== 'all') {
                $('.nvg-category-row').each(function() {
                    if ($(this).data('category') !== category) {
                        $(this).hide();
                    }
                });
            }
            
            // Filter by free
            if (isFree === 'yes') {
                $('.nvg-video-card').each(function() {
                    const $card = $(this);
                    const hasFree = $card.find('.nvg-free-badge').length > 0;
                    
                    if (!hasFree) {
                        $card.closest('.swiper-slide').hide();
                    } else {
                        $card.closest('.swiper-slide').show();
                    }
                });
            } else {
                $('.swiper-slide').show();
            }
            
            // Update sliders
            $('.nvg-category-slider').each(function() {
                if (this.swiper) {
                    this.swiper.update();
                }
            });
        }
    }
    
    /**
     * Initialize Card Hover Effects
     */
    function initCardHover() {
        $('.nvg-video-card').on('mouseenter', function() {
            $(this).addClass('hovered');
        }).on('mouseleave', function() {
            $(this).removeClass('hovered');
        });
    }
    
    /**
     * Lazy Load Images
     */
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('.nvg-video-card img[loading="lazy"]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
})(jQuery);