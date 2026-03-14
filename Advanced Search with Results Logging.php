<?php
/*
Plugin Name: Advanced Search with Results Logging
Description: Advanced search with strict validation, category filter, search in titles/content/comments/tags, and logs search queries + results count, plus an admin dashboard for log management including Legacy Logs.
Version: 1.2
Author: Farai Dziwanyika
*/

if (!defined('ABSPATH')) {
    exit;
}

// --- Activation ---
register_activation_hook(__FILE__, 'casl_install');

/**
 * Creates or updates the custom database table for search logs.
 * Uses dbDelta to handle both initial creation and schema updates (like adding new columns).
 */
function casl_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cas_search_logs';
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        search_term VARCHAR(255) NOT NULL,
        category INT(10) NOT NULL DEFAULT 0,
        in_titles TINYINT(1) NOT NULL DEFAULT 0,
        in_content TINYINT(1) NOT NULL DEFAULT 0,
        in_comments TINYINT(1) NOT NULL DEFAULT 0,
        results_found INT(10) NOT NULL DEFAULT 0,
        searched_at DATETIME NOT NULL,
        hidden TINYINT(1) NOT NULL DEFAULT 0,
        legacy TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    dbDelta($sql);
}

/**
 * Logs a search query and its results to the custom database table.
 *
 * @param string $term          The search term.
 * @param int    $cat           The category ID searched.
 * @param bool   $titles        Whether titles were searched.
 * @param bool   $content       Whether content was searched.
 * @param bool   $comments      Whether comments were searched.
 * @param int    $results_found The number of results found for the search.
 * @param int    $is_hidden     (Optional) Whether the log entry should be hidden (1 for hidden, 0 for not).
 * @param int    $is_legacy     (Optional) Whether the log entry should be legacy (1 for legacy, 0 for not).
 */
function csl_log_search($term, $cat, $titles, $content, $comments, $results_found = 0, $is_hidden = 0, $is_legacy = 0) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cas_search_logs';

    $wpdb->insert($table_name, [
        'search_term'    => sanitize_text_field($term),
        'category'       => intval($cat),
        'in_titles'      => $titles ? 1 : 0,
        'in_content'     => $content ? 1 : 0,
        'in_comments'    => $comments ? 1 : 0,
        'results_found'  => intval($results_found),
        'searched_at'    => current_time('mysql'),
        'hidden'         => intval($is_hidden), // Use passed parameter
        'legacy'         => intval($is_legacy), // Use passed parameter
    ]);
}

// Register the shortcode for displaying the search form.
add_shortcode('custom_advanced_search', 'cas_render_search_form');
// Hook to initialize search handling and filters early in WordPress load.
add_action('init', 'cas_handle_search');

/**
 * Renders the HTML form for the custom advanced search.
 * This function is hooked to the 'custom_advanced_search' shortcode.
 *
 * @return string The HTML output of the search form.
 */
function cas_render_search_form() {
    $categories = get_categories(['hide_empty' => false]);
    $selected_cat = isset($_GET['cas_category']) ? intval($_GET['cas_category']) : 0;
    $search_query = isset($_GET['cas_search']) ? esc_attr($_GET['cas_search']) : '';
    $search_in_titles    = isset($_GET['cas_search']) ? (isset($_GET['cas_in_titles']) ? 'checked' : '') : 'checked';
    $search_in_content = isset($_GET['cas_search']) ? (isset($_GET['cas_in_content']) ? 'checked' : '') : 'checked';
    $search_in_comments = isset($_GET['cas_search']) ? (isset($_GET['cas_in_comments']) ? 'checked' : '') : 'checked';
    $nonce = wp_create_nonce('cas_search_nonce');
    ob_start();
    ?>
    <style>
        .cas-search-form { max-width: 600px; margin: 20px auto; padding: 25px; border: 1px solid #ddd; border-radius: 10px; background: #fafafa; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); display: flex; flex-direction: column; gap: 20px; font-family: Arial, sans-serif; }
        .cas-input-wrapper { position: relative; }
        #cas_search_input { width: 100%; padding: 15px; font-size: 18px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        #cas_char_count { position: absolute; top: 0; right: 0; font-size: 12px; color: #888; padding: 4px 8px; background: transparent; user-select: none; pointer-events: none; }
        select[name="cas_category"] { width: 100%; padding: 10px; font-size: 16px; border-radius: 6-px; border: 1px solid #ccc; }
        .cas-checkbox-group { display: flex; flex-direction: column; gap: 10px; }
        .cas-checkbox-group label { font-size: 15px; display: flex; align-items: center; gap: 8px; }
        .cas-search-form button { padding: 14px; font-size: 16px; background-color: #0073aa; color: white; border: none; border-radius: 6px; cursor: pointer; transition: background 0.3s; }
        .cas-search-form button:hover { background-color: #005f8d; }
        .cas-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .cas-modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; text-align: center; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19); }
        .cas-modal-content button { background-color: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 15px; }
        .cas-modal-content button:hover { background-color: #005f8d; }
    </style>

    <form method="get" action="<?php echo esc_url(home_url('/')); ?>" class="cas-search-form">
        <div class="cas-input-wrapper">
            <input type="search" name="cas_search" id="cas_search_input" placeholder="Search..." value="<?php echo esc_attr($search_query); ?>" maxlength="40" required autocomplete="off" />
            <span id="cas_char_count">40</span>
        </div>
        <input type="hidden" name="cas_nonce" value="<?php echo esc_attr($nonce); ?>" />
        <select name="cas_category">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($selected_cat, $cat->term_id); ?>>
                    <?php echo esc_html($cat->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="cas-checkbox-group">
            <label><input type="checkbox" name="cas_in_titles" <?php echo esc_attr($search_in_titles); ?>> Search in Titles</label>
            <label><input type="checkbox" name="cas_in_content" <?php echo esc_attr($search_in_content); ?>> Search in Content</label>
            <label><input type="checkbox" name="cas_in_comments" <?php echo esc_attr($search_in_comments); ?>> Search in Comments</label>
        </div>
        <input type="text" name="cas_hp" value="" style="display:none;" autocomplete="off" />
        <button type="submit">Search</button>
    </form>

    <div id="cas_message_modal" class="cas-modal">
        <div class="cas-modal-content">
            <p id="cas_modal_message"></p>
            <button id="cas_modal_close">OK</button>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const form = document.querySelector(".cas-search-form");
        const input = document.getElementById("cas_search_input");
        const counter = document.getElementById("cas_char_count");
        const maxChars = 40;
        const modal = document.getElementById("cas_message_modal");
        const modalMessage = document.getElementById("cas_modal_message");
        const modalCloseBtn = document.getElementById("cas_modal_close");

        function showModal(message) {
            modalMessage.textContent = message;
            modal.style.display = "block";
        }
        modalCloseBtn.onclick = function() { modal.style.display = "none"; }
        window.onclick = function(event) { if (event.target == modal) { modal.style.display = "none"; } }

        form.addEventListener("submit", function (e) {
            const checkboxes = form.querySelectorAll('input[type="checkbox"]');
            let oneChecked = false;
            checkboxes.forEach(cb => { if (cb.checked) oneChecked = true; });
            if (!oneChecked) {
                e.preventDefault();
                showModal("Please select at least one item to search in: Titles, Content, or Comments.");
            }
            if (location.protocol !== 'https:') {
                e.preventDefault();
                showModal("Please use a secure HTTPS connection.");
            }
        });

        function updateCounter() {
            const remaining = maxChars - input.value.length;
            counter.textContent = remaining;
            counter.style.color = remaining <= 5 ? "red" : "#888";
        }
        input.addEventListener("input", function () {
            this.value = this.value.replace(/[^a-zA-Z\s.,!?'"()-]/g, '');
            if (this.value.length > maxChars) { this.value = this.value.substring(0, maxChars); }
            updateCounter();
        });
        updateCounter();
    });
    </script>

    <?php
    if (isset($_GET['cas_search'])) {
        global $wp_query;
        if ($wp_query->found_posts === 0) {
            echo '<p style="color: red;">' . esc_html__('No results found for your search.', 'text-domain') . '</p>';
        }
    }
    return ob_get_clean();
}

/**
 * Validates the search term against a strict regex pattern.
 */
function cas_is_valid_search_term($term) {
    return preg_match('/^[a-zA-Z\s.,!?\'"()-]*$/', $term);
}

/**
 * Handles the server-side validation and applies search filters to the WordPress query.
 */
function cas_handle_search() {
    if (!isset($_GET['cas_search'])) return;
    if (!is_ssl()) { wp_die('Search requests must be made over a secure HTTPS connection.'); }
    $expected_host = parse_url(home_url(), PHP_URL_HOST);
    if (empty($_SERVER['HTTP_REFERER']) || stripos($_SERVER['HTTP_REFERER'], $expected_host) === false) { wp_die('Invalid referrer. Search requests must come from the same site.'); }
    if (!isset($_GET['cas_nonce']) || !wp_verify_nonce($_GET['cas_nonce'], 'cas_search_nonce')) { wp_die('Security check failed.'); }
    if (!empty($_GET['cas_hp'])) { wp_die('Bot detection triggered.'); }
    $checkboxes = ['cas_in_titles', 'cas_in_content', 'cas_in_comments'];
    $valid_checkbox_selected = false;
    foreach ($checkboxes as $cb) { if (isset($_GET[$cb])) { $valid_checkbox_selected = true; break; } }
    if (!$valid_checkbox_selected) { wp_die('At least one search area must be selected (Titles, Content, or Comments).'); }
    $search_term = isset($_GET['cas_search']) ? trim(sanitize_text_field($_GET['cas_search'])) : '';
    if (empty($search_term)) { wp_die('Search term cannot be empty.'); }
    if (mb_strlen($search_term) > 40) { wp_die('Search term is too long.'); }
    if (!cas_is_valid_search_term($search_term)) { wp_die('Search term contains invalid characters. Please type your query manually without emojis or symbols.'); }
    
    add_filter('posts_where', 'cas_custom_search_where');
    add_filter('pre_get_posts', 'cas_filter_category_if_set');
}

/**
 * Modifies the WHERE clause of the main WordPress query to include custom search logic.
 */
function cas_custom_search_where($where) {
    global $wpdb;
    if (!isset($_GET['cas_search'])) return $where;
    $search_term = trim(sanitize_text_field($_GET['cas_search']));
    if (empty($search_term) || mb_strlen($search_term) > 40 || !cas_is_valid_search_term($search_term)) return $where;
    $like_term = '%' . $wpdb->esc_like($search_term) . '%';
    $conditions = [];

    if (isset($_GET['cas_in_titles'])) { $conditions[] = $wpdb->prepare("{$wpdb->posts}.post_title LIKE %s", $like_term); }
    if (isset($_GET['cas_in_content'])) { $conditions[] = $wpdb->prepare("{$wpdb->posts}.post_content LIKE %s", $like_term); }
    if (isset($_GET['cas_in_comments'])) {
        $conditions[] = $wpdb->prepare("
            EXISTS (
                SELECT 1 FROM {$wpdb->comments}
                WHERE {$wpdb->comments}.comment_post_ID = {$wpdb->posts}.ID
                AND {$wpdb->comments}.comment_content LIKE %s
            )", $like_term);
    }
    // Search in post tags (from Code 2)
    $conditions[] = $wpdb->prepare("
        EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE tr.object_id = {$wpdb->posts}.ID
            AND tt.taxonomy = 'post_tag'
            AND t.name LIKE %s
        )", $like_term);

    if (!empty($conditions)) { $where .= ' AND (' . implode(' OR ', $conditions) . ')'; } else { $where .= ' AND 1=0'; }
    return $where;
}

/**
 * Filters the main WordPress query to include a category if specified.
 */
function cas_filter_category_if_set($query) {
    if (!is_admin() && $query->is_main_query()) {
        if (isset($_GET['cas_category']) && intval($_GET['cas_category']) > 0) {
            $query->set('cat', intval($_GET['cas_category']));
        }
    }
}

// Hook to log search results count after the main query has run and template is being loaded.
add_action('template_redirect', 'cas_log_search_results_count');

/**
 * Logs the search query and the number of results found after the main WordPress query has executed.
 * If the search term has previously been marked as hidden or legacy, the new log entry will inherit that status.
 */
function cas_log_search_results_count() {
    if (is_admin() || !is_main_query() || !isset($_GET['cas_search'])) return;
    global $wp_query, $wpdb;

    $search_term = isset($_GET['cas_search']) ? sanitize_text_field($_GET['cas_search']) : '';
    if (empty($search_term)) {
        // If search term is empty, do not log. This should ideally be caught earlier by validation.
        return;
    }

    $category = isset($_GET['cas_category']) ? intval($_GET['cas_category']) : 0;
    $in_titles = isset($_GET['cas_in_titles']);
    $in_content = isset($_GET['cas_in_content']);
    $in_comments = isset($_GET['cas_in_comments']);
    $results_found = isset($wp_query->found_posts) ? intval($wp_query->found_posts) : 0;

    $table_name = $wpdb->prefix . 'cas_search_logs';

    // Determine if the search term has been previously marked as hidden or legacy
    // We check for any existing log entry for this term that is either hidden or legacy.
    // The most recent status is prioritized (hidden over legacy).
    $existing_status = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT hidden, legacy FROM $table_name WHERE search_term = %s ORDER BY searched_at DESC LIMIT 1",
            $search_term
        )
    );

    $is_hidden = 0;
    $is_legacy = 0;

    if ($existing_status) {
        if ($existing_status->hidden == 1) {
            $is_hidden = 1;
        } elseif ($existing_status->legacy == 1) {
            $is_legacy = 1;
        }
    }

    // Log the search with the determined hidden/legacy status
    csl_log_search($search_term, $category, $in_titles, $in_content, $in_comments, $results_found, $is_hidden, $is_legacy);
}

// --- Number formatting helper ---
function cas_format_number($num) {
    if ($num >= 1000000000000) return round($num / 1000000000000, 1) . 'T';
    if ($num >= 1000000000) return round($num / 1000000000, 1) . 'B';
    if ($num >= 1000000) return round($num / 1000000, 1) . 'M';
    if ($num >= 1000) return round($num / 1000, 1) . 'K';
    return $num;
}

// --- Admin Dashboard Code ---

add_action('admin_menu', 'cas_add_admin_menu');

function cas_add_admin_menu() {
    add_menu_page('Search Logs', 'Search Logs', 'manage_options', 'cas-search-logs', 'cas_render_admin_page', 'dashicons-search');
    add_submenu_page('cas-search-logs', 'Legacy Logs', 'Legacy Logs', 'manage_options', 'cas-legacy-logs', 'cas_render_legacy_logs_page');
    add_submenu_page('cas-search-logs', 'Hidden Logs', 'Hidden Logs', 'manage_options', 'cas-hidden-logs', 'cas_render_hidden_logs_page');
}

/**
 * Handles the processing of form submissions for moving logs.
 *
 * @param string $action The action to perform ('move_to_hidden', 'move_to_legacy', etc.).
 */
function cas_process_log_actions($action) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cas_search_logs';
    
    // Check user capability and nonce
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to perform this action.');
    }

    if (isset($_POST['log_ids']) && check_admin_referer('cas_move_logs_nonce')) {
        $all_log_ids = [];
        foreach ($_POST['log_ids'] as $grouped_ids_string) {
            $ids = explode(',', $grouped_ids_string);
            $all_log_ids = array_merge($all_log_ids, array_map('absint', $ids));
        }
        $unique_log_ids = array_unique($all_log_ids);

        if (!empty($unique_log_ids)) {
            // Prepare the placeholders for the query
            $placeholders = implode(',', array_fill(0, count($unique_log_ids), '%d'));
            $id_list = array_values($unique_log_ids);

            switch ($action) {
                case 'move_to_hidden':
                    $query = $wpdb->prepare("UPDATE $table_name SET hidden = 1, legacy = 0 WHERE id IN ($placeholders)", $id_list);
                    $wpdb->query($query);
                    break;
                case 'move_to_legacy':
                    $query = $wpdb->prepare("UPDATE $table_name SET legacy = 1, hidden = 0 WHERE id IN ($placeholders)", $id_list);
                    $wpdb->query($query);
                    break;
                case 'move_to_search_logs_from_legacy':
                    $query = $wpdb->prepare("UPDATE $table_name SET legacy = 0, hidden = 0 WHERE id IN ($placeholders)", $id_list);
                    $wpdb->query($query);
                    break;
                case 'move_to_hidden_logs_from_legacy':
                    $query = $wpdb->prepare("UPDATE $table_name SET legacy = 0, hidden = 1 WHERE id IN ($placeholders)", $id_list);
                    $wpdb->query($query);
                    break;
                case 'move_to_search_logs_from_hidden':
                    $query = $wpdb->prepare("UPDATE $table_name SET hidden = 0, legacy = 0 WHERE id IN ($placeholders)", $id_list);
                    $wpdb->query($query);
                    break;
                case 'move_to_legacy_logs_from_hidden':
                    $query = $wpdb->prepare("UPDATE $table_name SET hidden = 0, legacy = 1 WHERE id IN ($placeholders)", $id_list);
                    $wpdb->query($query);
                    break;
            }
        }
    }
}

function cas_render_admin_page() {
    if (isset($_POST['cas_move_to_hidden'])) {
        cas_process_log_actions('move_to_hidden');
    }
    if (isset($_POST['cas_move_to_legacy'])) {
        cas_process_log_actions('move_to_legacy');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cas_search_logs';
    
    $results = $wpdb->get_results("
        SELECT search_term, COUNT(id) AS search_count, AVG(results_found) AS avg_results, MAX(searched_at) AS last_searched_at, GROUP_CONCAT(id) AS log_ids
        FROM $table_name
        WHERE hidden = 0 AND legacy = 0
        GROUP BY search_term
        ORDER BY last_searched_at DESC
    ");

    echo '<div class="wrap"><h1>Search Logs</h1>';
    echo '<form method="post">';
    wp_nonce_field('cas_move_logs_nonce');
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th><input type="checkbox" id="cas_select_all"></th><th>Search Term</th><th>Times Searched</th><th>Results Found (Avg)</th><th>Last Searched</th></tr></thead><tbody>';
    if (!empty($results)) {
        foreach ($results as $row) {
            echo '<tr><td><input type="checkbox" name="log_ids[]" value="' . esc_attr($row->log_ids) . '"></td>';
            echo '<td>' . esc_html($row->search_term) . '</td>';
            echo '<td>' . esc_html(cas_format_number($row->search_count)) . '</td>';
            echo '<td>' . esc_html(cas_format_number(round($row->avg_results))) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($row->last_searched_at))) . '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="5">No search logs found.</td></tr>';
    }
    echo '</tbody></table>';
    echo '<input type="submit" name="cas_move_to_legacy" class="button action" value="Send to Legacy">';
    echo '<input type="submit" name="cas_move_to_hidden" class="button action" value="Send to Hidden">';
    echo '</form></div>';
}

function cas_render_legacy_logs_page() {
    if (isset($_POST['cas_move_from_legacy'])) {
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        cas_process_log_actions('move_to_' . $action_type);
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cas_search_logs';

    $results = $wpdb->get_results("
        SELECT search_term, COUNT(id) AS search_count, AVG(results_found) AS avg_results, MAX(searched_at) AS last_searched_at, GROUP_CONCAT(id) AS log_ids
        FROM $table_name
        WHERE legacy = 1
        GROUP BY search_term
        ORDER BY last_searched_at DESC
    ");
    echo '<div class="wrap"><h1>Legacy Logs</h1>';
    echo '<form method="post">';
    wp_nonce_field('cas_move_logs_nonce');
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th><input type="checkbox" id="cas_select_all"></th><th>Search Term</th><th>Times Searched</th><th>Results Found (Avg)</th><th>Last Searched</th></tr></thead><tbody>';
    if (!empty($results)) {
        foreach ($results as $row) {
            echo '<tr><td><input type="checkbox" name="log_ids[]" value="' . esc_attr($row->log_ids) . '"></td>';
            echo '<td>' . esc_html($row->search_term) . '</td>';
            echo '<td>' . esc_html(cas_format_number($row->search_count)) . '</td>';
            echo '<td>' . esc_html(cas_format_number(round($row->avg_results))) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($row->last_searched_at))) . '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="5">No legacy logs found.</td></tr>';
    }
    echo '</tbody></table>';
    // Use separate submit buttons for different actions to correctly capture action_type
    echo '<input type="submit" name="cas_move_from_legacy" class="button action" value="Send to Search Logs" onclick="this.form.action_type.value=\'search_logs_from_legacy\';">';
    echo '<input type="submit" name="cas_move_from_legacy" class="button action" value="Send to Hidden Logs" onclick="this.form.action_type.value=\'hidden_logs_from_legacy\';">';
    echo '<input type="hidden" name="action_type" value="">'; // Hidden field to store the action type
    echo '</form></div>';
}

function cas_render_hidden_logs_page() {
    if (isset($_POST['cas_move_from_hidden'])) {
        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        cas_process_log_actions('move_to_' . $action_type);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cas_search_logs';
    
    $results = $wpdb->get_results("
        SELECT search_term, COUNT(id) AS search_count, AVG(results_found) AS avg_results, MAX(searched_at) AS last_searched_at, GROUP_CONCAT(id) AS log_ids
        FROM $table_name
        WHERE hidden = 1
        GROUP BY search_term
        ORDER BY last_searched_at DESC
    ");
    echo '<div class="wrap"><h1>Hidden Logs</h1>';
    echo '<form method="post">';
    wp_nonce_field('cas_move_logs_nonce');
    echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th><input type="checkbox" id="cas_select_all"></th><th>Search Term</th><th>Times Searched</th><th>Results Found (Avg)</th><th>Last Searched</th></tr></thead><tbody>';
    if (!empty($results)) {
        foreach ($results as $row) {
            echo '<tr><td><input type="checkbox" name="log_ids[]" value="' . esc_attr($row->log_ids) . '"></td>';
            echo '<td>' . esc_html($row->search_term) . '</td>';
            echo '<td>' . esc_html(cas_format_number($row->search_count)) . '</td>';
            echo '<td>' . esc_html(cas_format_number(round($row->avg_results))) . '</td>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format'), strtotime($row->last_searched_at))) . '</td></tr>';
        }
    } else {
        echo '<tr><td colspan="5">No hidden logs found.</td></tr>';
    }
    echo '</tbody></table>';
    // Use separate submit buttons for different actions to correctly capture action_type
    echo '<input type="submit" name="cas_move_from_hidden" class="button action" value="Send to Search Logs" onclick="this.form.action_type.value=\'search_logs_from_hidden\';">';
    echo '<input type="submit" name="cas_move_from_hidden" class="button action" value="Send to Legacy Logs" onclick="this.form.action_type.value=\'legacy_logs_from_hidden\';">';
    echo '<input type="hidden" name="action_type" value="">'; // Hidden field to store the action type
    echo '</form></div>';
}
?>