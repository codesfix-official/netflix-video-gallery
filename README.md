# Netflix Video Gallery

A WordPress plugin that provides a Netflix-style video experience with Vimeo playback, WooCommerce commerce flows, and WooCommerce Memberships-aware access rules.

## Overview

This plugin adds:

- A Video Gallery custom post type with video category taxonomy
- Netflix-style archive, category, and single-video templates
- Vimeo embed playback and thumbnail handling
- Access gating through:
  - Free flags
  - Individual video purchases
  - Subscription products
  - WooCommerce Memberships post restrictions
- A paywall popup with dynamic subscription plans and single-video offer
- A My Library endpoint in WooCommerce My Account for purchased videos

## Features

### Content Types

- video-gallery (public)
- video-category taxonomy for videos

### Front-End Experience

- Hero slider on video archive
- Category-based horizontal sliders
- Video card overlay with free badge and access-aware behavior
- Category page with active Vimeo player and clickable playlist
- Single video page with related videos slider
- Responsive UI for desktop, tablet, and mobile

### Access and Entitlement Model

Effective access checks:

1. Free videos are always viewable
2. Individual purchase entitlement grants access
3. If WooCommerce Memberships marks the video as restricted, membership permission is evaluated for that post
4. For non-restricted videos, active subscription ownership can grant access

Main helpers:

- nvg_user_can_watch_video
- nvg_user_has_individual_access
- nvg_user_has_subscription_access

### WooCommerce Integration

- Uses one configurable carrier product ID for individual video purchases
- Stores purchased content metadata in cart and order item meta
- Overrides carrier product line-item price with item-specific ACF price
- Grants entitlements on order processing and completed states
- Checkout upsell can replace individual items with a selected subscription product

### Membership Popup

- Modal is injected in footer on:
  - Video archive
  - Video taxonomy pages
  - Single video pages
- Shows dynamic subscription plans built from configured subscription product IDs
- Supports optional single-item buy offer for the blocked video
- Footer link behavior:
  - Logged-in users: My Library
  - Guests: configurable account link

### My Library Endpoint

- Adds WooCommerce My Account endpoint: my-library
- Displays individually purchased videos

## Requirements

- WordPress
- Advanced Custom Fields (required)
- WooCommerce (required for commerce features)
- WooCommerce Memberships (optional)
- WooCommerce Subscriptions (optional)

If ACF is missing, the plugin shows an admin notice and does not initialize.

## File Structure

- netflix-video-gallery.php: plugin bootstrap, CPT/taxonomy registration, script enqueue, template routing
- includes/helper-functions.php: core helpers, entitlement logic, settings page, popup rendering, Woo hooks, My Library endpoint
- includes/ajax-handlers.php: AJAX handlers for filtering, player data, and purchase offers
- templates/archive-video-gallery.php: video archive UI
- templates/taxonomy-video-category.php: category player and playlist page
- templates/single-video-gallery.php: single video playback and lock state
- assets/js/main.js: sliders, filters, and interactions
- assets/js/category-player.js: category playlist playback and AJAX video loading
- assets/js/paywall-popup.js: modal open/close, offer loading, and auto-open behavior
- assets/css/style.css: global plugin styling

## Admin Settings

Menu path:

- Video Gallery -> Membership Settings

Tabs:

1. Commerce
- Single Video Product ID
- Subscription Product IDs (one per line)

2. Popup Content
- Popup title
- Popup description
- Individual button text

3. Guest Account Link
- Guest line text
- Guest link label
- Guest account URL

Saved options:

- nvg_commerce_settings
- nvg_popup_settings

## Expected ACF Fields

On video-gallery:

- video_url (YouTube or Vimeo URL)
- short_description (text or textarea)
- is_free (boolean or choice)
- featured (boolean or choice)
- enable_individual_purchase (boolean)
- individual_price (number)

## Data and Access Notes

- Membership-restricted posts use Woo Memberships post-level checks
- Subscription checks are product-based and use configured subscription product IDs
- Individual entitlements are stored in user meta key: nvg_purchased_access_items

## AJAX Endpoints

All endpoints use nonce nvg_nonce.

- nvg_filter_videos
- nvg_load_more
- nvg_get_video_data
- nvg_get_purchase_offer
- nvg_add_purchase_to_cart

## Script and Style Loading

Assets are enqueued on:

- Video archive
- Single video
- Video category taxonomy
- My Library endpoint under My Account

External dependencies:

- Swiper v11
- Vimeo Player API

## Template Routing

The plugin routes to bundled templates for:

- archive-video-gallery.php
- taxonomy-video-category.php
- single-video-gallery.php

## Activation and Deactivation

- On activation:
  - Registers video post type and taxonomy
  - Flushes rewrite rules
- On deactivation:
  - Flushes rewrite rules

## Troubleshooting

1. Popup not showing plans
- Ensure subscription product IDs are configured under Membership Settings -> Commerce
- Ensure products are valid and published

2. Individual purchase does not grant access
- Confirm checkout item has nvg_content_id metadata
- Confirm order reached processing or completed

3. My Library not visible
- Save permalinks once (or reactivate plugin) to refresh endpoints
- Ensure WooCommerce My Account page is active

4. Vimeo player missing
- Confirm video_url is a valid Vimeo URL
- Check browser console for Vimeo script load errors

## Version

Current plugin version in header/constants: 1.0.1
