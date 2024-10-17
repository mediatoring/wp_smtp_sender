<?php
/**
 * Plugin Name: Webklient SMTP Mail
 * Description: Bezpečný plugin pro zajištění odesílání e-mailů přes SMTP server.
 * Version: 1.1.0
 * Author: Michal Kubíček, Webklient.cz
 * Author URI: https://www.webklient.cz, https://www.kubicek.ai
 * Text Domain: webklient-smtp-mail
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Secure_SMTP_Plugin {

    public function __construct() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Secure_SMTP_Plugin constructor called.');
        }
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('phpmailer_init', [$this, 'configure_phpmailer']);
        add_filter('wp_mail_failed', [$this, 'log_failed_mail']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    public function load_textdomain() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Loading plugin textdomain.');
        }
        load_plugin_textdomain('webklient-smtp-mail', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_admin_menu() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Adding admin menu.');
        }
        add_menu_page(__('Maily', 'webklient-smtp-mail'), __('Maily', 'webklient-smtp-mail'), 'manage_options', 'smtp-settings', [$this, 'smtp_settings_page'], 'dashicons-email', 100);
        add_submenu_page('smtp-settings', __('LOG', 'webklient-smtp-mail'), __('LOG', 'webklient-smtp-mail'), 'manage_options', 'smtp-logs', [$this, 'smtp_logs_page']);
    }

    public function register_settings() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Registering SMTP settings.');
        }
        register_setting('smtp_settings', 'smtp_server', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('smtp_settings', 'smtp_username', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('smtp_settings', 'smtp_password', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('smtp_settings', 'smtp_from_name', ['sanitize_callback' => 'sanitize_text_field']);
    }

    public function smtp_settings_page() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Displaying SMTP settings page.');
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Nastavení SMTP', 'webklient-smtp-mail'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('smtp_settings');
                do_settings_sections('smtp_settings');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php _e('SMTP Server', 'webklient-smtp-mail'); ?></th>
                        <td><input type="text" name="smtp_server" value="<?php echo esc_attr(get_option('smtp_server')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Přihlašovací jméno', 'webklient-smtp-mail'); ?></th>
                        <td><input type="text" name="smtp_username" value="<?php echo esc_attr(get_option('smtp_username')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Heslo', 'webklient-smtp-mail'); ?></th>
                        <td><input type="password" name="smtp_password" value="<?php echo esc_attr(get_option('smtp_password')); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php _e('Zobrazované jméno odesílatele', 'webklient-smtp-mail'); ?></th>
                        <td><input type="text" name="smtp_from_name" value="<?php echo esc_attr(get_option('smtp_from_name')); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function configure_phpmailer($phpmailer) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Configuring PHPMailer.');
        }
        $phpmailer->isSMTP();
        $phpmailer->Host = sanitize_text_field(get_option('smtp_server'));
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = sanitize_text_field(get_option('smtp_username'));
        $phpmailer->Password = sanitize_text_field(get_option('smtp_password'));
        $phpmailer->SMTPSecure = 'tls';
        $phpmailer->Port = 587;
        $phpmailer->FromName = sanitize_text_field(get_option('smtp_from_name'));
    }

    public function log_failed_mail($wp_error) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Logging failed mail.');
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'smtp_logs';
        $wpdb->insert(
            $table_name,
            [
                'email_to' => sanitize_text_field($wp_error->get('to')),
                'subject' => sanitize_text_field($wp_error->get('subject')),
                'status' => 'failed',
                'created_at' => current_time('mysql')
            ]
        );
        add_action('admin_notices', [$this, 'show_admin_notice']);
    }

    public function show_admin_notice() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Showing admin notice for failed mail.');
        }
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php _e('Došlo k chybě při odesílání e-mailu. Prosím zkontrolujte logy.', 'webklient-smtp-mail'); ?></p>
        </div>
        <?php
    }

    public function smtp_logs_page() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Displaying SMTP logs page.');
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'smtp_logs';
        $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d", 30));
        ?>
        <div class="wrap">
            <h1><?php _e('SMTP LOG', 'webklient-smtp-mail'); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th><?php _e('Email', 'webklient-smtp-mail'); ?></th>
                    <th><?php _e('Předmět', 'webklient-smtp-mail'); ?></th>
                    <th><?php _e('Status', 'webklient-smtp-mail'); ?></th>
                    <th><?php _e('Čas', 'webklient-smtp-mail'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->email_to); ?></td>
                        <td><?php echo esc_html($log->subject); ?></td>
                        <td><?php echo esc_html($log->status); ?></td>
                        <td><?php echo esc_html($log->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

new Secure_SMTP_Plugin();
