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
                    <button class="pseo-tab-button" data-tab="csv-generator">CSV Generator</button>
                    <button class="pseo-tab-button" data-tab="ai-generator">AI Generator</button>
                    <button class="pseo-tab-button" data-tab="settings">Settings</button>
                </div>

                <!-- Generator Tab -->
                <div class="pseo-tab-content active" id="generator">
                    <div class="pseo-form-container">
                        <h2>Generate New Page</h2>
                        <form id="pseo-generator-form">
                            <div class="form-group">
                                <label for="template_page">Select Template Page:</label>
                                <div class="template-select-group">
                                    <select id="template_page" name="template_page">
                                        <option value="">Select a page...</option>
                                        <?php foreach ($pages as $page): ?>
                                            <option value="<?php echo esc_attr($page->ID); ?>">
                                                <?php echo esc_html($page->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <a href="#" class="preview-template dashicons dashicons-visibility" id="preview-template" title="View template page" target="_blank" style="display: none;"></a>
                                </div>
                            </div>

                            <div class="form-group keyword-group">
                                <label>Primary Keyword:</label>
                                <div class="find-replace-group">
                                    <div class="find-field">
                                        <label for="keyword_find">Find:</label>
                                        <input type="text" id="keyword_find" name="keyword_find" 
                                               placeholder="e.g. Web Designer">
                                    </div>
                                    <div class="replace-field">
                                        <label for="keyword">Replace with:</label>
                                        <input type="text" id="keyword" name="keyword" 
                                               placeholder="e.g. Web Developer">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group dynamic-replacements">
                                <label>Additional Find & Replace:</label>
                                <div id="dynamic-rows">
                                    <!-- Dynamic rows will be added here -->
                                </div>
                                <button type="button" class="button add-row">+ Add Find & Replace</button>
                            </div>

                            <div class="form-group-submit">
                                <button type="submit" class="button button-primary">Generate Page</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- AI Generator Tab -->
                <div class="pseo-tab-content" id="ai-generator">
                    <div class="pseo-form-container">
                        <h2>AI Content Generator</h2>
                        <form id="pseo-ai-generator-form">
                            <div class="form-group">
                                <label for="page_builder">Select Page Builder:</label>
                                <select id="page_builder" name="page_builder" required>
                                    <option value="">Select a page builder...</option>
                                    <?php 
                                    // Check for common page builders
                                    $page_builders = array(
                                        'elementor' => 'Elementor',
                                        'divi-builder' => 'Divi Builder',
                                        'gutenberg' => 'Gutenberg',
                                        'wpbakery' => 'WPBakery Page Builder',
                                        'oxygen' => 'Oxygen Builder',
                                        'fusion-builder' => 'Avada Builder'
                                    );
                                    
                                    foreach ($page_builders as $slug => $name) {
                                        if (is_plugin_active($slug . '/' . $slug . '.php') || $slug === 'gutenberg') {
                                            echo '<option value="' . esc_attr($slug) . '">' . esc_html($name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="page_title">Page Title:</label>
                                <input type="text" id="page_title" name="page_title" 
                                       placeholder="Enter the page title" required>
                            </div>

                            <div class="form-group">
                                <label for="page_keyword">Primary Keyword:</label>
                                <input type="text" id="page_keyword" name="page_keyword" 
                                       placeholder="Enter the main keyword for the page" required>
                            </div>

                            <div class="form-group">
                                <label for="content_tone">Content Tone:</label>
                                <select id="content_tone" name="content_tone">
                                    <option value="professional">Professional</option>
                                    <option value="casual">Casual</option>
                                    <option value="friendly">Friendly</option>
                                    <option value="authoritative">Authoritative</option>
                                    <option value="informative">Informative</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="word_count">Word Count:</label>
                                <input type="number" id="word_count" name="word_count" 
                                       value="1000" min="500" max="3000" step="100">
                                <p class="description">Choose between 500 and 3000 words</p>
                            </div>

                            <div class="form-group-submit">
                                <button type="submit" class="button button-primary">Generate Content</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- CSV Generator Tab -->
                <div class="pseo-tab-content" id="csv-generator">
                    <div class="pseo-form-container">
                        <h2>Generate Pages from CSV</h2>
                        
                        <div class="form-group">
                            <label for="template_page_csv">Select Template Page:</label>
                            <div class="template-select-group">
                                <select id="template_page_csv" name="template_page_csv">
                                    <option value="">Select a page...</option>
                                    <?php foreach ($pages as $page): ?>
                                        <option value="<?php echo esc_attr($page->ID); ?>">
                                            <?php echo esc_html($page->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="#" class="preview-template dashicons dashicons-visibility" id="preview-template-csv" title="View template page" target="_blank" style="display: none;"></a>
                            </div>
                        </div>

                        <div class="csv-upload-container">
                            <div class="form-group">
                                <label for="csv_file">Upload CSV File:</label>
                                <input type="file" id="csv_file" name="csv_file" accept=".csv" />
                                <p class="description">Upload a CSV file where:<br>
                                - First row contains the text to find<br>
                                - Following rows contain the replacement values<br>
                                - All rows must have the same number of columns<br><br>
                                Example:<br>
                                Laravel,Alabama<br>
                                React,New York<br>
                                Angular,New York<br>
                                Open AI,New York</p>
                            </div>
                            
                            <div id="csv-preview" style="display: none;">
                                <h3>CSV Preview</h3>
                                <div class="csv-preview-content"></div>
                            </div>
                        </div>

                        <div class="csv-progress" style="display: none;">
                            <div class="csv-progress-bar">
                                <div class="csv-progress-bar-inner"></div>
                            </div>
                            <div class="csv-status">Processing row 0 of 0...</div>
                        </div>

                        <div class="form-group-submit">
                            <button type="button" id="generate-from-csv" class="button button-primary" disabled>Generate Pages</button>
                        </div>
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