<?php
/**
 * Plugin Name: Boon Band: Auto SEO Index Switcher
 * Description: Controls the SEO indexing settings for live and staging environments.
 * Version: 1.0
 * Author: Boon Band
 * Author URI: https://boon.band/
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: bb-seo-index-control
 */

// Hook into 'init' to run our function
add_action('init', 'bb_auto_seo_index_switcher');

// Add admin menu under 'Settings'
add_action('admin_menu', 'bb_auto_seo_index_switcher_menu');

// Main function
function bb_auto_seo_index_switcher() {
    // Always sanitize and validate the data
    $live_url_parts = parse_url(sanitize_text_field(get_option('bb_live_url')));
    $staging_url_parts = parse_url(sanitize_text_field(get_option('bb_staging_url')));

    $current_host = $_SERVER['HTTP_HOST'];
    $current_path = $_SERVER['REQUEST_URI'];

    $is_live = $current_host === $live_url_parts['host'] && strpos($current_path, $live_url_parts['path']) === 0;
    $is_staging = $current_host === $staging_url_parts['host'] && strpos($current_path, $staging_url_parts['path']) === 0;

    $email_notifications = get_option('bb_email_notifications', 'yes');
    $last_index_state = get_option('bb_last_index_state', null);

    $new_index_state = 'unknown';

    if ($is_live && !$is_staging) {
        update_option('blog_public', 1);
        add_action('admin_notices', 'bb_live_site_notice');
        $new_index_state = 'live';
    } elseif ($is_staging) {
        update_option('blog_public', 0);
        add_action('admin_notices', 'bb_staging_site_notice');
        $new_index_state = 'staging';
    }

    if ($new_index_state !== $last_index_state) {
        update_option('bb_last_index_state', $new_index_state);

        if ($email_notifications === 'yes') {
            $message = 'SEO indexing state changed to ' . strtoupper($new_index_state) . ' at ' . $current_host . $current_path;
            bb_log_and_notify($message, $current_host);
        }
    }
}

// Admin panel under 'Settings'
function bb_auto_seo_index_switcher_menu() {
    add_submenu_page(
        'options-general.php',
        'Auto SEO Index Switcher',
        'Auto SEO Index Switcher',
        'manage_options',
        'bb-auto-seo-index-switcher',
        'bb_auto_seo_index_switcher_admin_page'
    );
}

// Admin page content
function bb_auto_seo_index_switcher_admin_page() {
    if (isset($_POST['bb_save_settings'])) {
        // Always sanitize and validate the data before saving
        update_option('bb_live_url', sanitize_text_field($_POST['bb_live_url']));
        update_option('bb_staging_url', sanitize_text_field($_POST['bb_staging_url']));
        update_option('bb_email_notifications', sanitize_text_field($_POST['bb_email_notifications']));
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved!</p></div>';
    }

    $email_notifications = get_option('bb_email_notifications', 'yes');

    ?>
    <div class="wrap bb-as-switcher">
        <h1 style="color: #0073aa;">Auto SEO Index Switcher by <a href="https://boon.band/" style="text-decoration: none;">Boon Band</a></h1>
        <p style="font-size: 1.2em;">Don't forget to check out our <a href="https://boon.band/plugins/" target="_blank">other awesome plugins!</a></p>
        <form method="post" style="display: grid; gap: 20px; max-width: 400px;">
            <label>
                Live URL:
                <input type="text" name="bb_live_url" value="<?php echo get_option('bb_live_url'); ?>" style="width: 100%;" />
            </label>
            <label>
                Staging URL:
                <input type="text" name="bb_staging_url" value="<?php echo get_option('bb_staging_url'); ?>" style="width: 100%;" />
            </label>
            <label>
                Enable email notifications:
                <select name="bb_email_notifications">
                    <option value="yes" <?php selected($email_notifications, 'yes'); ?>>Yes</option>
                    <option value="no" <?php selected($email_notifications, 'no'); ?>>No</option>
                </select>
            </label>
            <input type="submit" name="bb_save_settings" value="Save Settings" class="button button-primary" />
        </form>
    </div>
    <?php
}

// Admin notice for live site
function bb_live_site_notice() {
    ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('This site is identified as a Live site. SEO indexing is ENABLED.', 'bb-seo-index-control'); ?></p>
    </div>
    <?php
}

// Admin notice for staging site
function bb_staging_site_notice() {
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><?php _e('This site is identified as a Staging site. SEO indexing is DISABLED.', 'bb-seo-index-control'); ?></p>
    </div>
    <?php
}

// Log and email notification
function bb_log_and_notify($message, $current_host) {
    $log_file = plugin_dir_path(__FILE__) . 'bb_seo_index_control.log';
    file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, FILE_APPEND);

    $admin_email = get_option('admin_email');
    $message .= "\n\n--\nSent from " . $current_host;
    wp_mail($admin_email, 'SEO Index Control Status', $message);
}

// Add styles
function bb_auto_seo_index_switcher_styles() {
    ?>
    <style>
        .bb-as-switcher {
            font-family: Arial, sans-serif;
            background-color: #f1f1f1;
            padding: 20px;
            border-radius: 8px;
        }
        .bb-as-switcher input[type="text"],
        .bb-as-switcher select {
            padding: 6px;
            margin-top: 4px;
        }
        .bb-as-switcher select {
            width: 100%;
            display: block;
        }
        .bb-as-switcher input[type="submit"] {
            background-color: #0073aa;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
        }
    </style>
    <?php
}
add_action('admin_head', 'bb_auto_seo_index_switcher_styles');
