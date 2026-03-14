=== Advanced Search with Results Logging ===
Contributors: Farai Dziwanyika 
Tags: search, advanced search, search logs, analytics, results tracking
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful search enhancement tool that provides users with granular filters and gives administrators a complete audit trail of search behavior.

== Description ==

Advanced Search with Results Logging replaces the standard, limited WordPress search with a robust, filtered system. It allows users to pinpoint information within Titles, Content, or Comments, while simultaneously tracking every query in a custom database for administrative review.

This plugin is ideal for site owners who want to understand their audience's needs and identify content gaps by seeing which search terms return zero results.

= Key Features =
Multi-Location Search: Choose to search specifically within Post Titles, Content, Comments, or Tags.
Category Narrowing: Includes a built-in category dropdown to filter results.
Security First: Requires HTTPS connections, utilizes Nonce verification, and includes a honeypot to block bot-driven searches.
Strict Validation: A 40-character limit and alphanumeric filtering prevent SQL injection and character-set errors.
Rich Analytics: An admin dashboard tracks search frequency, average result counts, and timestamps.
Data Organization: Easily move logs between "Active," "Legacy" (Archived), or "Hidden" views to keep your data clean.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Use the shortcode `[custom_advanced_search]` on any page or post to display the search form.
4. Access search data via the 'Search Logs' menu in the WordPress admin sidebar.

== Frequently Asked Questions ==

= How do I display the search form? =
Simply paste the shortcode `[custom_advanced_search]` into any Page, Post, or Text Widget.

= Where is the data stored? =
The plugin creates a custom table in your WordPress database named `wp_cas_search_logs` (assuming your prefix is wp_).

= Does this work with custom post types? =
The current version is optimized for standard WordPress Posts and Pages.

== Screenshots ==

1. The custom search form with modern UI and character counter.
2. The Search Logs dashboard showing popular search terms and result averages.
3. The Hidden and Legacy log management interfaces.

== Changelog ==

= 1.2 =
Implemented Legacy and Hidden log status inheritance (new searches for hidden terms stay hidden).
Added Farai Dziwanyika as the lead author.
Refined admin dashboard UI with grouped action buttons.

= 1.1 =
Added tag-based searching logic.
Improved security with referrer checking and strict regex validation.

= 1.0 =
Initial release with basic logging and shortcode support.