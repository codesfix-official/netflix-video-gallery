<?php
/**
 * Plugin Name: Netflix Video Gallery
 * Description: Netflix-style video gallery with Vimeo integration and ACF fields
 * Version: 1.0.1
 * Author: codesfix
 * Author URI: https://www.codesfix.net/
 * License: GPL v2 or later
 * Text Domain: netflix-video-gallery
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('NVG_VERSION', '1.0.1');
define('NVG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NVG_PLUGIN_URL', plugin_dir_url(__FILE__));

class Netflix_Video_Gallery {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->include_files();
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_course_post_type'));
        add_action('init', array($this, 'register_lesson_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('template_include', array($this, 'load_templates'));
    }
    
    private function include_files() {
        require_once NVG_PLUGIN_DIR . 'includes/helper-functions.php';
        require_once NVG_PLUGIN_DIR . 'includes/course-helpers.php';
        require_once NVG_PLUGIN_DIR . 'includes/ajax-handlers.php';
    }
    
    /**
     * Register Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'               => 'Video Gallery',
            'singular_name'      => 'Video',
            'menu_name'          => 'Video Gallery',
            'add_new'            => 'Add New Video',
            'add_new_item'       => 'Add New Video',
            'edit_item'          => 'Edit Video',
            'new_item'           => 'New Video',
            'view_item'          => 'View Video',
            'search_items'       => 'Search Videos',
            'not_found'          => 'No videos found',
            'not_found_in_trash' => 'No videos found in Trash',
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'video-gallery'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-video-alt3',
            'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest'        => true,
        );
        
        register_post_type('video-gallery', $args);
    }
    
    /**
     * Register Taxonomy
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => 'Video Categories',
            'singular_name'     => 'Video Category',
            'search_items'      => 'Search Categories',
            'all_items'         => 'All Categories',
            'parent_item'       => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
            'edit_item'         => 'Edit Category',
            'update_item'       => 'Update Category',
            'add_new_item'      => 'Add New Category',
            'new_item_name'     => 'New Category Name',
            'menu_name'         => 'Categories',
        );
        
        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'video-category'),
            'show_in_rest'      => true,
        );
        
        register_taxonomy('video-category', array('video-gallery'), $args);
    }

    /**
     * Register Course Custom Post Type
     */
    public function register_course_post_type() {
        $labels = array(
            'name'               => 'Courses',
            'singular_name'      => 'Course',
            'menu_name'          => 'Courses',
            'add_new'            => 'Add New Course',
            'add_new_item'       => 'Add New Course',
            'edit_item'          => 'Edit Course',
            'new_item'           => 'New Course',
            'view_item'          => 'View Course',
            'search_items'       => 'Search Courses',
            'not_found'          => 'No courses found',
            'not_found_in_trash' => 'No courses found in Trash',
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'course'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 6,
            'menu_icon'           => 'dashicons-book',
            'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest'        => true,
        );
        
        register_post_type('course', $args);
    }

    /**
     * Register Lesson Custom Post Type
     */
    public function register_lesson_post_type() {
        $labels = array(
            'name'               => 'Lessons',
            'singular_name'      => 'Lesson',
            'menu_name'          => 'Lessons',
            'add_new'            => 'Add New Lesson',
            'add_new_item'       => 'Add New Lesson',
            'edit_item'          => 'Edit Lesson',
            'new_item'           => 'New Lesson',
            'view_item'          => 'View Lesson',
            'search_items'       => 'Search Lessons',
            'not_found'          => 'No lessons found',
            'not_found_in_trash' => 'No lessons found in Trash',
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'edit.php?post_type=course',
            'query_var'           => false,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'menu_position'       => 7,
            'supports'            => array('title', 'editor'),
            'show_in_rest'        => true,
        );
        
        register_post_type('lesson', $args);
    }
    
    /**
     * Enqueue Scripts and Styles
     */
    public function enqueue_scripts() {
        $library_query_var = get_query_var('my-library', null);
        $is_library_endpoint = function_exists('is_account_page')
            && is_account_page()
            && (
                (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('my-library'))
                || null !== $library_query_var
                || isset($_GET['my-library'])
            );

        if (is_post_type_archive('video-gallery') || 
            is_singular('video-gallery') || 
            is_tax('video-category') ||
            is_singular('course') ||
            is_post_type_archive('course') ||
            $is_library_endpoint) {
            
            // Swiper CSS
            wp_enqueue_style(
                'swiper-css',
                'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
                array(),
                '11.0.0'
            );
            
            // Custom CSS
            wp_enqueue_style(
                'nvg-style',
                NVG_PLUGIN_URL . 'assets/css/style.css',
                array(),
                NVG_VERSION
            );
            
            // Swiper JS
            wp_enqueue_script(
                'swiper-js',
                'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
                array(),
                '11.0.0',
                true
            );
            
            // Vimeo Player API
            wp_enqueue_script(
                'vimeo-player',
                'https://player.vimeo.com/api/player.js',
                array(),
                null,
                true
            );
            
            // Main JS
            wp_enqueue_script(
                'nvg-main',
                NVG_PLUGIN_URL . 'assets/js/main.js',
                array('jquery', 'swiper-js'),
                NVG_VERSION,
                true
            );
            
            // Category Player JS (only on taxonomy pages)
            if (is_tax('video-category')) {
                wp_enqueue_script(
                    'nvg-category-player',
                    NVG_PLUGIN_URL . 'assets/js/category-player.js',
                    array('jquery', 'vimeo-player'),
                    NVG_VERSION,
                    true
                );
            }

            // Course Player JS (only on single course pages)
            if (is_singular('course')) {
                wp_enqueue_script(
                    'nvg-course-player',
                    NVG_PLUGIN_URL . 'assets/js/course-player.js',
                    array('jquery', 'vimeo-player'),
                    NVG_VERSION,
                    true
                );
            }

            // Course archive search JS (only on course archive pages)
            if (is_post_type_archive('course')) {
                wp_enqueue_script(
                    'nvg-course-archive',
                    NVG_PLUGIN_URL . 'assets/js/course-archive.js',
                    array('jquery', 'nvg-main'),
                    NVG_VERSION,
                    true
                );
            }

            // Paywall popup for viewers who may lack access to a specific item.
            if (is_post_type_archive('video-gallery') || is_tax('video-category') || is_singular('video-gallery') || is_singular('course')) {
                wp_enqueue_script(
                    'nvg-paywall-popup',
                    NVG_PLUGIN_URL . 'assets/js/paywall-popup.js',
                    array('jquery', 'nvg-main'),
                    NVG_VERSION,
                    true
                );
            }
            
            // Localize script
            wp_localize_script('nvg-main', 'nvgAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('nvg_nonce'),
                'isLoggedIn' => is_user_logged_in(),
            ));
        }
    }
    
    /**
     * Load Custom Templates
     */
    public function load_templates($template) {
        if (is_post_type_archive('video-gallery')) {
            $custom_template = NVG_PLUGIN_DIR . 'templates/archive-video-gallery.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        if (is_tax('video-category')) {
            $custom_template = NVG_PLUGIN_DIR . 'templates/taxonomy-video-category.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        if (is_singular('video-gallery')) {
            $custom_template = NVG_PLUGIN_DIR . 'templates/single-video-gallery.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_singular('course')) {
            $custom_template = NVG_PLUGIN_DIR . 'templates/single-course.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        if (is_post_type_archive('course')) {
            $custom_template = NVG_PLUGIN_DIR . 'templates/archive-course.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        
        return $template;
    }
}

// Initialize plugin
function nvg_init() {
    if ( ! function_exists( 'get_field' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Netflix Video Gallery requires the Advanced Custom Fields (ACF) plugin to be installed and activated.', 'netflix-video-gallery' ) . '</p></div>';
        } );
        return;
    }
    return Netflix_Video_Gallery::get_instance();
}
add_action('plugins_loaded', 'nvg_init');

// Activation hook - flush rewrite rules
register_activation_hook(__FILE__, 'nvg_activate');
function nvg_activate() {
    $plugin = Netflix_Video_Gallery::get_instance();
    $plugin->register_post_type();
    $plugin->register_course_post_type();
    $plugin->register_lesson_post_type();
    $plugin->register_taxonomy();
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'nvg_deactivate');
function nvg_deactivate() {
    flush_rewrite_rules();
}