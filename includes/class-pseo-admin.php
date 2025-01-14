<?php

class PSEO_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Programmatic SEO Builder',
            'PSEO Builder',
            'manage_options',
            'pseo-builder',
            array($this, 'render_admin_page'),
            'dashicons-admin-site',
            30
        );
    }

    public function register_settings() {
        register_setting('pseo_settings', 'pseo_api_key');
        register_setting('pseo_settings', 'pseo_common_definitions');
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_pseo-builder' !== $hook) {
            return;
        }

        wp_enqueue_style('pseo-admin-style', PSEO_PLUGIN_URL . 'assets/css/admin.css', array(), PSEO_VERSION);
        wp_enqueue_script('pseo-admin-script', PSEO_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), PSEO_VERSION, true);
        
        wp_localize_script('pseo-admin-script', 'pseoAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pseo_nonce')
        ));
    }

    public function render_admin_page() {
        // Get all published pages for the dropdown
        $pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'ASC'));
        ?>
        <div class="wrap">
            <h1>Programmatic SEO Builder</h1>
            
            <div class="pseo-tabs">
                <div class="pseo-tab-nav">
                    <button class="pseo-tab-button active" data-tab="generator">Page Generator</button>
                    <button class="pseo-tab-button" data-tab="settings">Settings</button>
                </div>

                <!-- Generator Tab -->
                <div class="pseo-tab-content active" id="generator">
                    <div class="pseo-form-container">
                        <h2>Generate New Page</h2>
                        <form id="pseo-generator-form">
                            <div class="form-group">
                                <label for="template_page">Select Template Page:</label>
                                <select id="template_page" name="template_page" >
                                    <option value="">Select a page...</option>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>">
                                            <?php echo esc_html($page->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="keyword">Primary Keyword:</label>
                                <input type="text" id="keyword" name="keyword" required 
                                       placeholder="Enter primary keyword (e.g., Web Developer, Digital Marketing)">
                            </div>

                            <div class="form-group">
                                <label for="location">Location:</label>
                                <input type="text" id="location" name="location" required 
                                       placeholder="Enter locations separated by commas (e.g., New York, London, Sydney)">
                            </div>

                            <div class="form-group">
                                <label for="skill_set">Skill Set:</label>
                                <input type="text" id="skill_set" name="skill_set" required 
                                       placeholder="Enter skills separated by commas (e.g., JavaScript, React, Node.js)">
                            </div>

                            <div class="form-group-submit">
                                <button type="submit" class="button button-primary">Generate Page</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div class="pseo-tab-content" id="settings">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('pseo_settings');
                        do_settings_sections('pseo_settings');
                        ?>
                        
                        <div class="form-group api-key-group">
                            <label for="pseo_api_key">DeepSeek API Key:</label>
                            <div class="input-group">
                                <input type="password" id="pseo_api_key" name="pseo_api_key" 
                                       value="<?php echo esc_attr(get_option('pseo_api_key')); ?>" class="regular-text">
                                <button type="button" id="test-api" class="button">Test API Connection</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pseo_common_definitions">Common Content Definitions:</label>
                            <textarea id="pseo_common_definitions" name="pseo_common_definitions" rows="10" class="large-text"
                                    placeholder="Define common elements used across all generated pages. Examples:
- Company Name: Your Company Name
- Contact Email: contact@example.com
- Phone: (555) 123-4567
- Business Hours: Mon-Fri 9am-5pm
- Service Areas: List of primary service locations
- Core Services: List of main services offered"
                            ><?php echo esc_textarea(get_option('pseo_common_definitions')); ?></textarea>
                        </div>

                        <?php submit_button('Save Settings'); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
} 