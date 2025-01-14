<?php

class PSEO_Page_Generator {
    private $api_handler;

    public function __construct() {
        $this->api_handler = new PSEO_API_Handler();
        add_action('wp_ajax_pseo_generate_page', array($this, 'ajax_generate_page'));
        add_action('wp_ajax_pseo_preview_content', array($this, 'ajax_preview_content'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
        add_action('wp_head', array($this, 'output_meta_tags'));
    }

    public function ajax_generate_page() {
        check_ajax_referer('pseo_nonce', 'nonce');

        if (!current_user_can('publish_pages')) {
            wp_send_json_error(array(
                'message' => 'Insufficient permissions',
                'code' => 'unauthorized'
            ));
        }

        // Validate and sanitize input
        $template_id = !empty($_POST['template_page']) ? intval($_POST['template_page']) : 0;
        $keyword = sanitize_text_field($_POST['keyword']);
        $location = sanitize_text_field($_POST['location']);
        $skill_set = sanitize_text_field($_POST['skill_set']);

        // Check required fields
        if (!$location || !$keyword || !$skill_set) {
            wp_send_json_error(array(
                'message' => 'Missing required fields: location, keyword, and skill set are required',
                'code' => 'missing_fields'
            ));
        }

        try {
            // Create new page title first
            $new_title = '';
            $new_content = '';
            $template_page = null;
            
            if ($template_id) {
                // Get template page if provided
                $template_page = get_post($template_id);
                if (!$template_page || $template_page->post_type !== 'page') {
                    wp_send_json_error(array(
                        'message' => 'Invalid template page',
                        'code' => 'invalid_template'
                    ));
                }
            }

            // Generate page content and create the page
            $result = $this->generate_single_page($template_id, $template_page, $location, $keyword, $skill_set);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => $result->get_error_message(),
                    'code' => $result->get_error_code()
                ));
                return;
            }

            wp_send_json_success(array(
                'page_id' => $result['page_id'],
                'edit_url' => $result['edit_url'],
                'view_url' => $result['view_url'],
                'preview_content' => $result['preview_content']
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error generating page: ' . $e->getMessage(),
                'code' => 'generation_error'
            ));
        }
    }

    /**
     * Generate a single page with the given parameters
     */
    private function generate_single_page($template_id, $template_page, $location, $keyword, $skill_set) {
        // Generate title
        if ($template_id && $template_page) {
            $new_title = $this->generate_page_title($template_page->post_title, $location, $keyword, $skill_set);
            // Generate content based on template
            $new_content = $this->api_handler->generate_content(
                $template_page->post_content,
                $location,
                $keyword,
                $skill_set
            );
        } else {
            $new_title = sprintf('%s in %s - %s', $keyword, $location, $skill_set);
            $new_content = $this->generate_new_page_content($new_title, $location, $keyword, $skill_set);
        }

        if (is_wp_error($new_content)) {
            return $new_content;
        }

        // Generate meta description
        $meta_description = $this->generate_meta_description($new_content, $location, $keyword, $skill_set);

        // Create new page
        $new_page_id = $this->create_page($new_title, $new_content, $meta_description);

        if (is_wp_error($new_page_id)) {
            return $new_page_id;
        }

        // Copy template data if using a template
        if ($template_id) {
            $this->copy_template_meta_data($template_id, $new_page_id);
            $this->copy_template_taxonomies($template_id, $new_page_id);
            $this->clone_page_builder_data($template_id, $new_page_id);
            
            $template_data = $this->get_template_data($template_id);
            if ($template_data) {
                $this->clone_template_association($new_page_id, $template_data);
            }
        }

        // Add schema.org structured data
        $this->add_structured_data($new_page_id, $location, $keyword, $skill_set);

        return array(
            'page_id' => $new_page_id,
            'edit_url' => get_edit_post_link($new_page_id, 'url'),
            'view_url' => get_permalink($new_page_id),
            'preview_content' => $this->format_preview_content($new_content)
        );
    }

    public function ajax_preview_content() {
        check_ajax_referer('pseo_nonce', 'nonce');

        if (!current_user_can('publish_pages')) {
            wp_send_json_error(array(
                'message' => 'Insufficient permissions',
                'code' => 'unauthorized'
            ));
        }

        $template_id = !empty($_POST['template_page']) ? intval($_POST['template_page']) : 0;
        $location = sanitize_text_field($_POST['location']);
        $keyword = sanitize_text_field($_POST['keyword']);
        $skill_set = sanitize_text_field($_POST['skill_set']);

        if (!$location || !$keyword || !$skill_set) {
            wp_send_json_error(array(
                'message' => 'Missing required fields: location, keyword, and skill set are required',
                'code' => 'missing_fields'
            ));
        }

        try {
            $new_content = '';
            $title = sprintf('%s in %s - %s', $keyword, $location, $skill_set);

            if ($template_id) {
                // Get template page if provided
                $template_page = get_post($template_id);
                if (!$template_page || $template_page->post_type !== 'page') {
                    wp_send_json_error(array(
                        'message' => 'Invalid template page',
                        'code' => 'invalid_template'
                    ));
                }
                
                // Generate content based on template
                $new_content = $this->api_handler->generate_content(
                    $template_page->post_content,
                    $location,
                    $keyword,
                    $skill_set
                );
            } else {
                // Generate new content without template
                $new_content = $this->generate_new_page_content($title, $location, $keyword, $skill_set);
            }

            if (is_wp_error($new_content)) {
                wp_send_json_error(array(
                    'message' => $new_content->get_error_message(),
                    'code' => $new_content->get_error_code(),
                    'details' => $new_content->get_error_data()
                ));
                return;
            }

            wp_send_json_success(array(
                'content' => $this->format_preview_content($new_content),
                'meta_description' => $this->generate_meta_description($new_content, $location, $keyword, $skill_set)
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error generating preview: ' . $e->getMessage(),
                'code' => 'generation_error'
            ));
        }
    }

    private function format_preview_content($content) {
        // Apply basic WordPress formatting
        $content = wpautop($content);
        $content = wptexturize($content);
        return $content;
    }

    private function generate_meta_description($content, $location, $keyword, $skill_set) {
        // Extract first paragraph or first 160 characters
        $first_para = substr($content, 0, strpos($content, "\n\n"));
        if (!$first_para) {
            $first_para = $content;
        }

        $meta_desc = wp_strip_all_tags($first_para);
        $meta_desc = str_replace(array("\r", "\n"), ' ', $meta_desc);
        $meta_desc = substr($meta_desc, 0, 160);

        // Ensure it ends with a complete sentence
        $last_period = strrpos(substr($meta_desc, 0, 157), '.');
        if ($last_period !== false) {
            $meta_desc = substr($meta_desc, 0, $last_period + 1);
        }

        return $meta_desc;
    }

    private function add_structured_data($page_id, $location, $keyword, $skill_set) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => get_the_title($page_id),
            'description' => get_post_meta($page_id, '_pseo_meta_description', true),
            'url' => get_permalink($page_id),
            'datePublished' => get_the_date('c', $page_id),
            'dateModified' => get_the_modified_date('c', $page_id),
            'publisher' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => get_home_url()
            ),
            'about' => array(
                '@type' => 'Thing',
                'name' => $keyword,
                'description' => sprintf('Information about %s in %s', $keyword, $location)
            ),
            'locationCreated' => array(
                '@type' => 'Place',
                'name' => $location
            )
        );

        update_post_meta($page_id, '_pseo_structured_data', wp_json_encode($schema));
    }

    public function output_meta_tags() {
        if (!is_singular()) {
            return;
        }

        $post_id = get_the_ID();
        if (!get_post_meta($post_id, '_pseo_generated', true)) {
            return;
        }

        $meta_description = get_post_meta($post_id, '_pseo_meta_description', true);
        if ($meta_description) {
            echo '<meta name="description" content="' . esc_attr($meta_description) . '" />' . "\n";
        }

        // Output Open Graph tags
        echo '<meta property="og:title" content="' . esc_attr(get_the_title()) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '" />' . "\n";
        if ($meta_description) {
            echo '<meta property="og:description" content="' . esc_attr($meta_description) . '" />' . "\n";
        }

        // Output structured data
        $structured_data = get_post_meta($post_id, '_pseo_structured_data', true);
        if ($structured_data) {
            echo '<script type="application/ld+json">' . $structured_data . '</script>' . "\n";
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'pseo_meta_box',
            'SEO Information',
            array($this, 'render_meta_box'),
            'page',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        $meta_description = get_post_meta($post->ID, '_pseo_meta_description', true);
        ?>
        <div class="pseo-meta-box">
            <p>
                <label for="pseo_meta_description">Meta Description:</label><br>
                <textarea name="pseo_meta_description" id="pseo_meta_description" rows="3" style="width: 100%;"
                          maxlength="160"><?php echo esc_textarea($meta_description); ?></textarea>
                <span class="description">Maximum 160 characters. Current length: <span id="meta_desc_length">0</span></span>
            </p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                const metaDesc = $('#pseo_meta_description');
                const lengthDisplay = $('#meta_desc_length');
                
                function updateLength() {
                    lengthDisplay.text(metaDesc.val().length);
                }
                
                metaDesc.on('input', updateLength);
                updateLength();
            });
        </script>
        <?php
    }

    public function save_meta_data($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['pseo_meta_description'])) {
            update_post_meta(
                $post_id,
                '_pseo_meta_description',
                sanitize_textarea_field($_POST['pseo_meta_description'])
            );
        }
    }

    private function generate_page_title($template_title, $location, $keyword, $skill_set) {
        // Replace placeholders in template title if they exist
        $title = str_replace(
            array('[location]', '[keyword]', '[skill_set]'),
            array($location, $keyword, $skill_set),
            $template_title
        );

        // If no placeholders were replaced, create a new title
        if ($title === $template_title) {
            $title = sprintf('%s in %s - %s', $keyword, $location, $skill_set);
        }

        return $title;
    }

    private function create_page($title, $content, $meta_description) {
        global $wpdb;
        
        // Get the template type and check if we need to auto-generate
        $template_type = isset($_POST['template_type']) ? sanitize_text_field($_POST['template_type']) : 'default';
        $template_id = !empty($_POST['template_page']) ? intval($_POST['template_page']) : 0;
        $is_new_page = empty($template_id);

        // Prepare the page data
        if ($is_new_page) {
            // Generate new page structure
            $page_data = array(
                'post_title' => $title,
                'post_content' => $content, // Use the provided content directly for new pages
                'post_status' => 'draft',
                'post_type' => 'page',
                'post_author' => get_current_user_id(),
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_name' => sanitize_title($title),
            );
        } else {
            // Get the template page
            $template = get_post($template_id);
            if (!$template) {
                // Fallback to new page generation if template is invalid
                return $this->create_page($title, $content, $meta_description);
            }

            // Prepare the page data, maintaining original structure
            $page_data = array(
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'draft',
                'post_type' => $template->post_type,
                'post_excerpt' => $template->post_excerpt,
                'post_author' => get_current_user_id(),
                'comment_status' => $template->comment_status,
                'ping_status' => $template->ping_status,
                'post_password' => $template->post_password,
                'post_name' => sanitize_title($title),
                'post_parent' => $template->post_parent,
                'menu_order' => $template->menu_order,
                'post_mime_type' => $template->post_mime_type,
                'comment_count' => 0,
                'filter' => 'raw',
            );
        }

        // Insert the page
        $new_page_id = wp_insert_post($page_data, true);

        if (is_wp_error($new_page_id)) {
            return $new_page_id;
        }

        // Set the template
        if ($template_type !== 'default') {
            update_post_meta($new_page_id, '_wp_page_template', $template_type);
        }

        // If we're using a template page, copy its meta data and settings
        if (!$is_new_page) {
            $this->copy_template_meta_data($template_id, $new_page_id);
            $this->copy_template_taxonomies($template_id, $new_page_id);
            $this->clone_page_builder_data($template_id, $new_page_id);
        } else {
            // Set default meta data for new pages
            $this->set_default_meta_data($new_page_id);
        }

        // Add our custom meta data
        update_post_meta($new_page_id, '_pseo_generated', true);
        update_post_meta($new_page_id, '_pseo_generated_date', current_time('mysql'));
        update_post_meta($new_page_id, '_pseo_meta_description', $meta_description);
        if ($template_id) {
            update_post_meta($new_page_id, '_pseo_template_id', $template_id);
        }

        return $new_page_id;
    }

    private function generate_new_page_content($title, $location, $keyword, $skill_set) {
        // Get theme's content width
        $content_width = isset($GLOBALS['content_width']) ? $GLOBALS['content_width'] : 1200;
        
        // Properly escape all variables
        $title = esc_html($title);
        $location = esc_html($location);
        $keyword = esc_html($keyword);
        $skill_set = esc_html($skill_set);

        // Build content with standard WordPress blocks
        $content = '';

        // Featured Image Block
        $content .= '<!-- wp:image {"align":"wide","className":"featured-image wp-post-image"} -->
<figure class="wp-block-image alignwide featured-image wp-post-image"><img src="' . sprintf('https://via.placeholder.com/%dx400/f5f5f5/333333', $content_width) . '" alt="' . $title . '"/></figure>
<!-- /wp:image -->' . "\n\n";

        // Title
        $content .= '<!-- wp:heading {"level":1,"className":"page-title"} -->
<h1 class="wp-block-heading page-title">' . $title . '</h1>
<!-- /wp:heading -->' . "\n\n";

        // Introduction
        $content .= '<!-- wp:paragraph {"className":"location-intro"} -->
<p class="location-intro">Discover the thriving opportunities for ' . $keyword . ' in ' . $location . '. As a dynamic hub for professionals, ' . $location . ' offers unique possibilities for those skilled in ' . $skill_set . '.</p>
<!-- /wp:paragraph -->' . "\n\n";

        // First Section
        $content .= '<!-- wp:heading {"className":"section-title"} -->
<h2 class="wp-block-heading section-title">Why Choose ' . $location . ' for ' . $keyword . '</h2>
<!-- /wp:heading -->' . "\n\n";

        $content .= '<!-- wp:paragraph -->
<p>Learn about the growing demand for ' . $keyword . ' professionals in ' . $location . ', and how your expertise in ' . $skill_set . ' can contribute to the local industry.</p>
<!-- /wp:paragraph -->' . "\n\n";

        // Subsection
        $content .= '<!-- wp:heading {"level":3,"className":"sub-section-title"} -->
<h3 class="wp-block-heading sub-section-title">Key Opportunities in ' . $location . '</h3>
<!-- /wp:heading -->' . "\n\n";

        // List
        $content .= '<!-- wp:list {"className":"opportunities-list"} -->
<ul class="opportunities-list">
    <li>Growing market demand for ' . $keyword . ' specialists</li>
    <li>Thriving business ecosystem</li>
    <li>Networking opportunities</li>
    <li>Professional development resources</li>
</ul>
<!-- /wp:list -->' . "\n\n";

        // Skills Section
        $content .= '<!-- wp:heading {"level":3,"className":"sub-section-title"} -->
<h3 class="wp-block-heading sub-section-title">Required Skills and Expertise</h3>
<!-- /wp:heading -->' . "\n\n";

        $content .= '<!-- wp:paragraph -->
<p>Success in ' . $location . '\'s ' . $keyword . ' sector requires proficiency in ' . $skill_set . '. These skills are particularly valuable in the local market.</p>
<!-- /wp:paragraph -->' . "\n\n";

        // Getting Started Section
        $content .= '<!-- wp:heading {"className":"section-title"} -->
<h2 class="wp-block-heading section-title">Getting Started in ' . $location . '</h2>
<!-- /wp:heading -->' . "\n\n";

        $content .= '<!-- wp:paragraph -->
<p>Ready to explore opportunities in ' . $location . '? Connect with local professionals and organizations to learn more about ' . $keyword . ' opportunities in the area.</p>
<!-- /wp:paragraph -->';

        return $content;
    }

    private function set_default_meta_data($page_id) {
        // Set default meta description
        $meta_description = sprintf(
            'Explore opportunities for %s professionals in %s. Learn about the local market, required skills, and how to succeed in this dynamic field.',
            sanitize_text_field($_POST['keyword']),
            sanitize_text_field($_POST['location'])
        );
        update_post_meta($page_id, '_pseo_meta_description', $meta_description);

        // Set default thumbnail if theme supports it
        if (current_theme_supports('post-thumbnails')) {
            // You can set a default thumbnail ID here if you have one
            // set_post_thumbnail($page_id, $default_thumbnail_id);
        }

        // Add generation metadata
        update_post_meta($page_id, '_pseo_generated_type', 'new');
        update_post_meta($page_id, '_pseo_keyword', sanitize_text_field($_POST['keyword']));
        update_post_meta($page_id, '_pseo_location', sanitize_text_field($_POST['location']));
        update_post_meta($page_id, '_pseo_skill_set', sanitize_text_field($_POST['skill_set']));
        update_post_meta($page_id, '_pseo_generated_date', current_time('mysql'));
    }

    /**
     * Get template data for a given post
     */
    private function get_template_data($post_id) {
        $template_data = array();
        
        // Check for page template
        $page_template = get_page_template_slug($post_id);
        if ($page_template) {
            $template_data['page_template'] = $page_template;
        }

        // Check for theme template hierarchy
        $post_type = get_post_type($post_id);
        $template_hierarchy = array();
        
        // Get template hierarchy based on post type and other factors
        if ($post_type === 'page') {
            $template_hierarchy = array(
                get_page_template_slug($post_id),
                'page-' . $post_id . '.php',
                'page-' . get_post_field('post_name', $post_id) . '.php',
                'page.php'
            );
        } else {
            $template_hierarchy = array(
                get_post_type_archive_template(),
                'single-' . $post_type . '-' . $post_id . '.php',
                'single-' . $post_type . '.php',
                'single.php'
            );
        }

        // Remove empty values
        $template_hierarchy = array_filter($template_hierarchy);
        
        if (!empty($template_hierarchy)) {
            $template_data['hierarchy'] = $template_hierarchy;
        }

        // Check for custom template meta
        $custom_template = get_post_meta($post_id, '_wp_page_template', true);
        if ($custom_template && $custom_template !== 'default') {
            $template_data['custom_template'] = $custom_template;
        }

        // Get template parts used in the content
        $template_parts = $this->get_template_parts($post_id);
        if (!empty($template_parts)) {
            $template_data['template_parts'] = $template_parts;
        }

        return !empty($template_data) ? $template_data : false;
    }

    /**
     * Get template parts used in the content
     */
    private function get_template_parts($post_id) {
        $content = get_post_field('post_content', $post_id);
        $template_parts = array();

        // Check for get_template_part calls in shortcodes
        if (has_shortcode($content, 'get_template_part')) {
            preg_match_all('/\[get_template_part[^\]]*\]/', $content, $matches);
            foreach ($matches[0] as $shortcode) {
                $atts = shortcode_parse_atts($shortcode);
                if (isset($atts['slug'])) {
                    $template_parts[] = array(
                        'type' => 'shortcode',
                        'slug' => $atts['slug'],
                        'name' => isset($atts['name']) ? $atts['name'] : ''
                    );
                }
            }
        }

        // Check for template part blocks (Gutenberg)
        if (has_blocks($content)) {
            $blocks = parse_blocks($content);
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'core/template-part') {
                    $template_parts[] = array(
                        'type' => 'block',
                        'slug' => $block['attrs']['slug'] ?? '',
                        'theme' => $block['attrs']['theme'] ?? ''
                    );
                }
            }
        }

        return $template_parts;
    }

    /**
     * Clone template associations to the new page
     */
    private function clone_template_association($new_page_id, $template_data) {
        // Set page template
        if (isset($template_data['page_template'])) {
            update_post_meta($new_page_id, '_wp_page_template', $template_data['page_template']);
        }

        // Set custom template
        if (isset($template_data['custom_template'])) {
            update_post_meta($new_page_id, '_wp_page_template', $template_data['custom_template']);
        }

        // Store template hierarchy information
        if (isset($template_data['hierarchy'])) {
            update_post_meta($new_page_id, '_pseo_template_hierarchy', $template_data['hierarchy']);
        }

        // Store template parts information
        if (isset($template_data['template_parts'])) {
            update_post_meta($new_page_id, '_pseo_template_parts', $template_data['template_parts']);
        }

        return true;
    }

    private function clone_page_builder_data($template_id, $new_page_id) {
        // Elementor
        if (class_exists('\Elementor\Plugin')) {
            $elementor_data = get_post_meta($template_id, '_elementor_data', true);
            if ($elementor_data) {
                update_post_meta($new_page_id, '_elementor_data', $elementor_data);
                update_post_meta($new_page_id, '_elementor_version', get_post_meta($template_id, '_elementor_version', true));
                update_post_meta($new_page_id, '_elementor_edit_mode', get_post_meta($template_id, '_elementor_edit_mode', true));
                update_post_meta($new_page_id, '_elementor_template_type', get_post_meta($template_id, '_elementor_template_type', true));
                update_post_meta($new_page_id, '_elementor_page_settings', get_post_meta($template_id, '_elementor_page_settings', true));
            }
        }

        // Divi Builder
        $divi_data = get_post_meta($template_id, '_et_pb_use_builder', true);
        if ($divi_data) {
            update_post_meta($new_page_id, '_et_pb_use_builder', $divi_data);
            update_post_meta($new_page_id, '_et_pb_old_content', get_post_meta($template_id, '_et_pb_old_content', true));
            update_post_meta($new_page_id, '_et_pb_builder_version', get_post_meta($template_id, '_et_pb_builder_version', true));
            $builder_data = get_post_meta($template_id, '_et_builder_version', true);
            if ($builder_data) {
                update_post_meta($new_page_id, '_et_builder_version', $builder_data);
            }
        }

        // WPBakery Page Builder
        $wpb_data = get_post_meta($template_id, '_wpb_vc_js_status', true);
        if ($wpb_data) {
            update_post_meta($new_page_id, '_wpb_vc_js_status', $wpb_data);
            update_post_meta($new_page_id, '_wpb_shortcodes_custom_css', get_post_meta($template_id, '_wpb_shortcodes_custom_css', true));
            update_post_meta($new_page_id, 'vc_page_settings', get_post_meta($template_id, 'vc_page_settings', true));
        }

        // Beaver Builder
        if (class_exists('FLBuilder')) {
            $beaver_data = get_post_meta($template_id, '_fl_builder_data', true);
            if ($beaver_data) {
                update_post_meta($new_page_id, '_fl_builder_data', $beaver_data);
                update_post_meta($new_page_id, '_fl_builder_draft', get_post_meta($template_id, '_fl_builder_draft', true));
                update_post_meta($new_page_id, '_fl_builder_enabled', get_post_meta($template_id, '_fl_builder_enabled', true));
            }
        }

        // Oxygen Builder
        $oxygen_data = get_post_meta($template_id, 'ct_builder_shortcodes', true);
        if ($oxygen_data) {
            update_post_meta($new_page_id, 'ct_builder_shortcodes', $oxygen_data);
            update_post_meta($new_page_id, 'ct_builder_json', get_post_meta($template_id, 'ct_builder_json', true));
            update_post_meta($new_page_id, 'ct_other_template', get_post_meta($template_id, 'ct_other_template', true));
        }

        // Gutenberg blocks
        $has_blocks = use_block_editor_for_post($template_id);
        if ($has_blocks) {
            // Gutenberg blocks are stored in post_content, which we've already copied
            // Copy any additional block-related meta
            $block_meta = get_post_meta($template_id, '_blocks', true);
            if ($block_meta) {
                update_post_meta($new_page_id, '_blocks', $block_meta);
            }
        }

        return true;
    }

    private function prepare_prompt($template_content, $location, $keyword, $skill_set, $common_definitions) {
        return <<<PROMPT
You are an HTML content generator. Your task is to rewrite the content while perfectly preserving all HTML structure. The output must be valid HTML that matches the exact structure of the original content.

IMPORTANT OUTPUT RULES:
1. Output MUST be in pure HTML format - DO NOT use Markdown or any other format
2. Use proper HTML tags for emphasis (<strong>, <em>) instead of Markdown asterisks
3. Use <h1>, <h2>, <h3> tags for headings instead of Markdown #
4. Use <ul> and <li> for lists instead of Markdown dashes or asterisks
5. Use <p> tags for paragraphs instead of double line breaks
6. Use <a href="..."> for links instead of Markdown brackets
7. Preserve ALL existing HTML tags exactly as they appear
8. Keep all HTML attributes unchanged (class, id, data-* attributes)
9. Maintain exact indentation and HTML structure
10. Keep all HTML comments in their original positions
11. Preserve all shortcodes in their exact format: [shortcode_name attr="value"]

Original HTML Content:
{$template_content}

Target Parameters:
- Location: {$location}
- Keyword: {$keyword}
- Skill Set: {$skill_set}

Common Definitions to Include:
{$common_definitions}

Content Generation Rules:
1. ONLY replace the text between HTML tags
2. Keep all <div>, <section>, <article> structures identical
3. Maintain all CSS classes and IDs
4. Preserve all WordPress shortcodes exactly as they appear
5. Keep all HTML comments and their positions
6. Maintain all whitespace and formatting
7. Preserve all inline styles and attributes
8. Keep all script and style tags untouched
9. Maintain all meta tags and SEO elements
10. Preserve all form elements and their attributes

Example Input:
<article class="content-area">
    <h1 class="title">Services in [location]</h1>
    <p>Find the best <strong>services</strong> in your area.</p>
    <!-- Service list -->
    <ul class="services-list">
        <li>Service 1</li>
        <li>Service 2</li>
    </ul>
    [contact_form id="123"]
</article>

Example Output (with new content but same structure):
<article class="content-area">
    <h1 class="title">Services in New York</h1>
    <p>Find the best <strong>plumbing services</strong> in your area.</p>
    <!-- Service list -->
    <ul class="services-list">
        <li>Emergency Repairs</li>
        <li>Installation Services</li>
    </ul>
    [contact_form id="123"]
</article>

Please provide the rewritten content as pure HTML with all structural elements preserved exactly as they appear in the original.
PROMPT;
    }

    private function copy_template_meta_data($template_id, $new_page_id) {
        // Get all meta keys for the template
        $template_meta = get_post_meta($template_id);
        
        if (!empty($template_meta)) {
            foreach ($template_meta as $meta_key => $meta_values) {
                // Skip certain meta keys that shouldn't be copied
                if (in_array($meta_key, array('_edit_lock', '_edit_last', '_pseo_generated', '_pseo_generated_date'))) {
                    continue;
                }
                
                // Copy each meta value
                foreach ($meta_values as $meta_value) {
                    update_post_meta($new_page_id, $meta_key, maybe_unserialize($meta_value));
                }
            }
        }
    }

    private function copy_template_taxonomies($template_id, $new_page_id) {
        // Get all taxonomies for pages
        $taxonomies = get_object_taxonomies('page');
        
        foreach ($taxonomies as $taxonomy) {
            // Get all terms for the template page
            $terms = wp_get_object_terms($template_id, $taxonomy, array('fields' => 'ids'));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                // Set the terms to the new page
                wp_set_object_terms($new_page_id, $terms, $taxonomy);
            }
        }
    }
} 