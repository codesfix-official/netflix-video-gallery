# Netflix Video Gallery

A WordPress plugin that provides a Netflix-style video and course experience with Vimeo playback, WooCommerce commerce flows, and WooCommerce Memberships-based content restriction.

## Overview

This plugin adds:

- A Video Gallery custom post type with category taxonomy
- A Course and Lesson learning structure
- Netflix-style archive/category/single templates with sliders and playlist UI
- Vimeo embed playback and thumbnail handling
- Access gating through:
  - Free flags
  - Individual purchases (video/course one-off)
  - WooCommerce Subscriptions products
  - WooCommerce Memberships post restrictions
- A paywall popup with dynamic subscription plan cards
- A My Library endpoint in My Account for individually purchased items
- Course progress tracking and lesson completion

## Features

### Content Types

- `video-gallery` (public)
- `course` (public)
- `lesson` (admin-managed under Courses, not publicly queryable)
- `video-category` taxonomy for videos

### Front-End Experience

- Hero slider on video archive
- Category-based horizontal sliders
- Video card overlay UX with free badge and play action
- Category page with 70/30 layout:
  - Left: active Vimeo player
  - Right: clickable playlist with lock indicators
- Single video page with related videos slider
- Course archive with search + AJAX pagination
- Single course page with lesson navigation and progress bar
- Responsive UI for desktop/tablet/mobile

### Access and Entitlement Model

The effective access checks are:

1. Free content is always viewable (videos only)
2. Individual purchase entitlement grants access
3. If WooCommerce Memberships marks the post as restricted, membership permission is evaluated for that specific post
4. For non-restricted content, active subscription ownership can grant access

This is implemented via:

- `nvg_user_can_watch_video()`
- `nvg_user_can_access_course()`
- `nvg_user_has_individual_access()`
- `nvg_user_has_subscription_access()`

### WooCommerce Integration

- Uses configurable carrier product IDs for individual purchases:
  - one product ID for single video purchases
  - one product ID for single course purchases
- Stores purchased content metadata in cart and order item meta
- Overrides carrier product line item price with item-specific ACF price
- Grants entitlements on order `processing` and `completed`
- Checkout upsell can replace individual items with a chosen subscription product

### Membership Popup

- Modal is injected in footer on video archive, category, single video, and single course pages
- Shows dynamic subscription plans built from configured subscription product IDs
- Supports one or many plans
- Supports optional single-item buy offer for the currently blocked content
- Footer link behavior:
  - Logged-in users: My Library
  - Guests: configurable account link

### My Library Endpoint

- Adds WooCommerce My Account endpoint: `my-library`
- Displays individually purchased videos and courses
- Uses existing card components for rendering

## Requirements

- WordPress
- Advanced Custom Fields (required)
- WooCommerce (required for commerce features)
- WooCommerce Memberships (optional but supported/recommended for restriction rules)
- WooCommerce Subscriptions (optional but supported for active-subscription checks)

If ACF is missing, the plugin shows an admin notice and does not initialize.

## File Structure

- `netflix-video-gallery.php`: plugin bootstrap, CPT/taxonomy registration, script enqueue, template routing
- `includes/helper-functions.php`: core helpers, entitlement logic, settings page, popup rendering, Woo hooks, My Library endpoint
- `includes/course-helpers.php`: lessons/progress utilities and course-card rendering
- `includes/ajax-handlers.php`: AJAX handlers for filtering, player data, lesson completion, course search, purchase offer
- `templates/archive-video-gallery.php`: video archive UI
- `templates/taxonomy-video-category.php`: category player + playlist page
- `templates/single-video-gallery.php`: single video playback and lock state
- `templates/archive-course.php`: course archive and search shell
- `templates/single-course.php`: lesson player and completion actions
- `assets/js/main.js`: sliders, filters, hover interactions
- `assets/js/category-player.js`: category playlist playback + AJAX video loading
- `assets/js/course-player.js`: lesson completion AJAX actions
- `assets/js/course-archive.js`: AJAX course search and pagination
- `assets/js/paywall-popup.js`: modal open/close, offer loading, auto-open behavior
- `assets/css/style.css`: global plugin styling

## Admin Settings

Menu path:

- Video Gallery -> Membership Settings

Tabs:

1. Commerce
- Single Video Product ID
- Single Course Product ID
- Subscription Product IDs (one per line)

2. Popup Content
- Popup title
- Popup description

3. Guest Account Link
- Guest line text
- Guest link label
- Guest account URL

Settings are saved into:

- `nvg_commerce_settings`
- `nvg_popup_settings`

## Expected ACF Fields

### On Video (`video-gallery`)

- `video_url` (Vimeo URL)
- `short_description` (text/textarea)
- `is_free` (boolean/choice)
- `featured` (boolean/choice)
- `enable_individual_purchase` (boolean)
- `individual_price` (number)

### On Course (`course`)

- `lessons` (relationship/repeater containing lesson references)
- `enable_individual_purchase` (boolean)
- `individual_price` (number)

### On Lesson (`lesson`)

- `video_url` (Vimeo URL)

## Access-Control Notes

- Membership-restricted posts rely on Woo Memberships per-post checks.
- Subscription checks are product-based and use configured subscription product IDs.
- Individual entitlements are stored in user meta key: `nvg_purchased_access_items`.
- Course completion is stored per user/course in user meta key format:
  - `nvg_completed_lessons_{course_id}`

## AJAX Endpoints

All endpoints use nonce `nvg_nonce`.

- `nvg_filter_videos`
- `nvg_load_more`
- `nvg_get_video_data`
- `nvg_mark_lesson_complete`
- `nvg_search_courses`
- `nvg_get_purchase_offer`

## Script and Style Loading

Assets are enqueued on:

- Video archive
- Single video
- Video category taxonomy
- Single course
- Course archive
- My Library endpoint under My Account

External dependencies loaded from CDN:

- Swiper v11
- Vimeo Player API

## Template Routing

The plugin routes to bundled templates for:

- `archive-video-gallery.php`
- `taxonomy-video-category.php`
- `single-video-gallery.php`
- `archive-course.php`
- `single-course.php`

## Activation/Deactivation

- On activation:
  - Registers CPTs/taxonomy
  - Flushes rewrite rules
- On deactivation:
  - Flushes rewrite rules

## Known Behaviors from Current Code

- Video archive and category queries are currently ordered by `date` with `ASC` in template query args.
- Category page also applies a PHP sort step to keep featured videos first and preserve date-driven ordering inside groups.

## Extending the Plugin

Useful integration points:

- Filter hook used:
  - `wc_memberships_restricted_content_message`
- Woo actions/filters used for cart/order flow:
  - `woocommerce_add_cart_item_data`
  - `woocommerce_before_calculate_totals`
  - `woocommerce_get_item_data`
  - `woocommerce_checkout_create_order_line_item`
  - `woocommerce_before_checkout_form`

## Troubleshooting

1. Popup not showing plans
- Ensure subscription product IDs are configured under Membership Settings -> Commerce
- Ensure products are valid and published

2. Individual purchase does not grant access
- Confirm checkout item has `nvg_content_id` metadata
- Confirm order reached processing/completed

3. My Library not visible
- Save permalinks once (or reactivate plugin) to refresh endpoints
- Ensure WooCommerce My Account page is active

4. Vimeo player missing
- Confirm `video_url` is valid Vimeo URL
- Check browser console for Vimeo script load issues

## Version

Current plugin version in header/constants: `1.0.1`
