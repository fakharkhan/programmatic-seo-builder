<?php
/**
 * Plugin Name: Programmatic SEO Builder
 * Plugin URI: https://github.com/fakharkhan/programmatic-seo-builder
 * Description: A powerful tool for creating programmatic SEO pages using AI content generation with DeepSeek API integration.
 * Version: 1.1.0
 * Author: Fakhar Zaman Khan, Hasan Zaheer
 * Author URI: https://softpyramid.com
 * Text Domain: programmatic-seo-builder
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Constants
 */
define('PSEO_VERSION', '1.1.0');
define('PSEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PSEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PSEO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Helper Functions
 */
function pseo_parse_comma_separated_values($input) {
    if (empty($input)) {
        return array();
    }
    return array_map('trim', array_filter(explode(',', $input)));
}

function pseo_sanitize_array_values($array) {
    return array_map('sanitize_text_field', $array);
}

function pseo_validate_combinations($locations, $skills, $max_combinations = 100) {
    $total = count($locations) * count($skills);
    if ($total > $max_combinations) {
        return new WP_Error(
            'too_many_combinations',
            sprintf('Too many combinations (%d). Maximum allowed is %d. Please reduce the number of locations or skills.', 
                    $total, $max_combinations)
        );
    }
    return true;
}

/**
 * Main Plugin Class
 */
class Programmatic_SEO_Builder {
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Plugin components
     */
    private $admin;
    private $page_generator;
    private $api_handler;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once PSEO_PLUGIN_DIR . 'includes/class-pseo-admin.php';
        require_once PSEO_PLUGIN_DIR . 'includes/class-pseo-page-generator.php';
        require_once PSEO_PLUGIN_DIR . 'includes/class-pseo-api-handler.php';

        // Initialize components
        $this->api_handler = new PSEO_API_Handler();
        $this->page_generator = new PSEO_Page_Generator($this->api_handler);
        $this->admin = new PSEO_Admin($this->page_generator);
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Plugin action links
        add_filter('plugin_action_links_' . PSEO_PLUGIN_BASENAME, array($this, 'add_plugin_links'));

        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary database tables
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Clear rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if necessary
        flush_rewrite_rules();
    }

    /**
     * Create plugin database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Generation history table
        $table_name = $wpdb->prefix . 'pseo_history';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            template_id bigint(20) NOT NULL,
            location varchar(255) NOT NULL,
            keyword varchar(255) NOT NULL,
            skill_set varchar(255) NOT NULL,
            generated_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY page_id (page_id),
            KEY template_id (template_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'pseo_api_key' => '',
            'pseo_common_definitions' => '',
            'pseo_generation_limit' => 50,
            'pseo_auto_publish' => 'no'
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Add plugin action links
     */
    public function add_plugin_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=pseo-builder') . '">' . __('Settings', 'programmatic-seo-builder') . '</a>'
        );
        return array_merge($plugin_links, $links);
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'programmatic-seo-builder',
            false,
            dirname(PSEO_PLUGIN_BASENAME) . '/languages/'
        );
    }
}

/**
 * Initialize the plugin
 */
function pseo_init() {
    return Programmatic_SEO_Builder::get_instance();
}

// Start the plugin
pseo_init(); 