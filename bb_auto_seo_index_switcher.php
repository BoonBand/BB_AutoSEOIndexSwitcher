<?php
/**
 * Plugin Name: Boon Band: Auto SEO Index Switcher
 * Description: Controls the SEO indexing settings for live and staging environments.
 * Version: 2.0
 * Author: Boon Band
 * Author URI: https://boon.band/
 * License: GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: bb-seo-index-control
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BB_Auto_SEO_Index_Switcher {

    public function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'auto_seo_index_switcher'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_head', array($this, 'admin_styles'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Load plugin textdomain for internationalization.
     */
    public function load_textdomain() {
        load_plugin_textdomain('bb-seo-index-control', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Register settings using the WordPress Settings API.
     */
    public function register_settings() {
        register_setting('bb_seo_index_group', 'bb_live_url', array($this, 'sanitize_base64'));
        register_setting('bb_seo_index_group', 'bb_staging_url', array($this, 'sanitize_base64'));
        register_setting('bb_seo_index_group', 'bb_email_notifications', 'sanitize_text_field');
    }

    /**
     * Sanitize base64 encoded options.
     */
    public function sanitize_base64($input) {
        return base64_encode(sanitize_text_field($input));
    }

    /**
     * Add the settings page to the WordPress admin menu.
     */
    public function add_settings_page() {
        add_submenu_page(
            'options-general.php',
            'Auto SEO Index Switcher',
            'Auto SEO Index Switcher',
            'manage_options',
            'bb-auto-seo-index-switcher',
            array($this, 'settings_page')
        );
    }

    /**
     * Display the settings page.
     */
    public function settings_page() {
        ?>
        <div class="wrap bb-as-switcher">
            <h1 style="color: #0073aa;">Auto SEO Index Switcher by <a href="https://boon.band/" style="text-decoration: none;">Boon Band</a></h1>
            <p style="font-size: 1.2em;">Don't forget to check out our <a href="https://boon.band/plugins/" target="_blank">other awesome plugins!</a></p>
            <form method="post" action="options.php" style="display: grid; gap: 20px; max-width: 400px;">
                <?php
                settings_fields('bb_seo_index_group');
                do_settings_sections('bb_seo_index_group');
                ?>
                <label>
                    <?php _e('Live URL:', 'bb-seo-index-control'); ?>
                    <input type="text" name="bb_live_url" value="<?php echo esc_attr(base64_decode(get_option('bb_live_url'))); ?>" style="width: 100%;" />
                </label>
                <label>
                    <?php _e('Staging URL:', 'bb-seo-index-control'); ?>
                    <input type="text" name="bb_staging_url" value="<?php echo esc_attr(base64_decode(get_option('bb_staging_url'))); ?>" style="width: 100%;" />
                </label>
                <label>
                    <?php _e('Enable email notifications:', 'bb-seo-index-control'); ?>
                    <select name="bb_email_notifications">
                        <option value="yes" <?php selected(get_option('bb_email_notifications'), 'yes'); ?>><?php _e('Yes', 'bb-seo-index-control'); ?></option>
                        <option value="no" <?php selected(get_option('bb_email_notifications'), 'no'); ?>><?php _e('No', 'bb-seo-index-control'); ?></option>
                    </select>
                </label>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Add custom styles to the admin area.
     */
    public function admin_styles() {
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

    /**
     * Main function to control SEO indexing based on environment.
     */
    public function auto_seo_index_switcher() {
        $live_url = base64_decode(get_option('bb_live_url'));
        $staging_url = base64_decode(get_option('bb_staging_url'));

        $current_host = $_SERVER['HTTP_HOST'];
        $current_uri = $_SERVER['REQUEST_URI'];

        $is_live = $live_url && (strpos($current_host . $current_uri, $live_url) !== false);
        $is_staging = $staging_url && (strpos($current_host . $current_uri, $staging_url) !== false);

        $email_notifications = get_option('bb_email_notifications', 'yes');
        $last_index_state = get_option('bb_last_index_state', null);

        $new_index_state = 'unknown';

        if ($is_live && !$is_staging) {
            update_option('blog_public', 1);
            $new_index_state = 'live';
        } elseif ($is_staging) {
            update_option('blog_public', 0);
            $new_index_state = 'staging';
        }

        if ($new_index_state !== $last_index_state) {
            update_option('bb_last_index_state', $new_index_state);
            do_action('bb_index_state_changed', $new_index_state);

            if ($email_notifications === 'yes') {
                $message = sprintf(__('SEO indexing state changed to %s at %s%s', 'bb-seo-index-control'), strtoupper($new_index_state), $current_host, $current_uri);
                $this->log_and_notify($message, $current_host);
            }
        }
    }

    /**
     * Display admin notices based on the indexing state.
     */
    public function admin_notices() {
        $last_index_state = get_option('bb_last_index_state', null);
        if ($last_index_state === 'live') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('This site is identified as a Live site. SEO indexing is ENABLED.', 'bb-seo-index-control'); ?></p>
            </div>
            <?php
        } elseif ($last_index_state === 'staging') {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><?php _e('This site is identified as a Staging site. SEO indexing is DISABLED.', 'bb-seo-index-control'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Log changes and send email notifications if enabled.
     */
    private function log_and_notify($message, $current_host) {
        $log_file = plugin_dir_path(__FILE__) . 'bb_seo_index_control.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, FILE_APPEND);

        $admin_email = get_option('admin_email');
        $message .= "\n\n--\n" . sprintf(__('Sent from %s', 'bb-seo-index-control'), $current_host);
        $email_subject = apply_filters('bb_email_subject', __('SEO Index Control Status', 'bb-seo-index-control'));
        wp_mail($admin_email, $email_subject, $message);
    }

}

new BB_Auto_SEO_Index_Switcher();
