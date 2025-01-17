<?php

class PSEO_Page_Generator {
    private $api_handler;

    public function __construct() {
        $this->api_handler = new PSEO_API_Handler();
        add_action('wp_ajax_pseo_clone_page', array($this, 'ajax_clone_page'));
        add_action('wp_ajax_pseo_get_template_url', array($this, 'ajax_get_template_url'));
        add_action('wp_ajax_pseo_generate_content', array($this, 'ajax_generate_content'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
        add_action('wp_head', array($this, 'output_meta_tags'));
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

    public function ajax_clone_page() {
        check_ajax_referer('pseo_nonce', 'nonce');

        if (!current_user_can('publish_pages')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $replacements = isset($_POST['replacements']) ? $_POST['replacements'] : array();

        if (!$template_id || empty($replacements)) {
            wp_send_json_error(array('message' => 'Missing required parameters'));
            return;
        }

        // Get all SEO related meta keys we want to process
        $seo_meta_keys = array(
            // Yoast SEO
            '_yoast_wpseo_title',
            '_yoast_wpseo_metadesc',
            '_yoast_wpseo_focuskw',
            '_yoast_wpseo_meta-robots-noindex',
            '_yoast_wpseo_meta-robots-nofollow',
            '_yoast_wpseo_meta-robots-adv',
            '_yoast_wpseo_canonical',
            '_yoast_wpseo_bctitle',
            '_yoast_wpseo_opengraph-title',
            '_yoast_wpseo_opengraph-description',
            // RankMath
            'rank_math_title',
            'rank_math_description',
            'rank_math_focus_keyword',
            // All in One SEO
            '_aioseo_title',
            '_aioseo_description',
            '_aioseo_keywords',
            // Our plugin's meta
            '_pseo_meta_description',
            '_pseo_meta_title'
        );

        // Get the template page
        $template = get_post($template_id);
        if (!$template) {
            wp_send_json_error(array('message' => 'Template page not found'));
            return;
        }

        // Create the new page with replaced content
        $new_page = array(
            'post_title'    => $this->replace_placeholders($template->post_title, $replacements),
            'post_content'  => $this->replace_placeholders($template->post_content, $replacements),
            'post_excerpt'  => $this->replace_placeholders($template->post_excerpt, $replacements),
            'post_status'   => 'draft',
            'post_type'     => 'page',
            'post_author'   => get_current_user_id(),
            'post_name'     => sanitize_title($this->replace_placeholders($template->post_title, $replacements))
        );

        // Insert the page
        $new_page_id = wp_insert_post($new_page);

        if (is_wp_error($new_page_id)) {
            wp_send_json_error(array('message' => $new_page_id->get_error_message()));
            return;
        }

        // Copy and process all meta data
        $template_meta = get_post_meta($template_id);
        if ($template_meta) {
            foreach ($template_meta as $key => $values) {
                // Skip internal WordPress meta
                if (in_array($key, array('_edit_lock', '_edit_last'))) {
                    continue;
                }

                foreach ($values as $value) {
                    $meta_value = maybe_unserialize($value);
                    
                    // If this is a SEO meta key or the value is a string, apply replacements
                    if (in_array($key, $seo_meta_keys) || is_string($meta_value)) {
                        $meta_value = $this->replace_placeholders($meta_value, $replacements);
                    }
                    
                    update_post_meta($new_page_id, $key, $meta_value);
                }
            }
        }

        // Generate meta description if none exists
        if (!get_post_meta($new_page_id, '_yoast_wpseo_metadesc', true) && 
            !get_post_meta($new_page_id, 'rank_math_description', true) && 
            !get_post_meta($new_page_id, '_aioseo_description', true) && 
            !get_post_meta($new_page_id, '_pseo_meta_description', true)) {
            
            $meta_description = $this->generate_meta_description($new_page_id, $replacements);
            
            // Update meta description for all supported SEO plugins
            update_post_meta($new_page_id, '_yoast_wpseo_metadesc', $meta_description);
            update_post_meta($new_page_id, 'rank_math_description', $meta_description);
            update_post_meta($new_page_id, '_aioseo_description', $meta_description);
            update_post_meta($new_page_id, '_pseo_meta_description', $meta_description);
        }

        // Add generation metadata
        update_post_meta($new_page_id, '_pseo_generated', true);
        update_post_meta($new_page_id, '_pseo_generated_date', current_time('mysql'));
        update_post_meta($new_page_id, '_pseo_template_id', $template_id);

        // Handle structured data if it exists
        $structured_data = get_post_meta($template_id, '_pseo_structured_data', true);
        if ($structured_data) {
            $updated_structured_data = $this->replace_placeholders($structured_data, $replacements);
            update_post_meta($new_page_id, '_pseo_structured_data', $updated_structured_data);
        }

        wp_send_json_success(array(
            'page_id' => $new_page_id,
            'edit_url' => get_edit_post_link($new_page_id, 'url'),
            'view_url' => get_permalink($new_page_id)
        ));
    }

    private function replace_placeholders($content, $replacements) {
        if (!is_string($content)) {
            return $content;
        }

        foreach ($replacements as $type => $replacement) {
            if (!empty($replacement['find']) && !empty($replacement['replace'])) {
                $content = str_replace($replacement['find'], $replacement['replace'], $content);
            }
        }
        return $content;
    }

    private function generate_meta_description($page_id, $replacements) {
        $content = get_post_field('post_content', $page_id);
        $content = wp_strip_all_tags($content);
        $content = $this->replace_placeholders($content, $replacements);
        
        // Get first 160 characters, ending at a complete sentence
        $meta_desc = substr($content, 0, 160);
        $last_period = strrpos(substr($meta_desc, 0, 157), '.');
        if ($last_period !== false) {
            $meta_desc = substr($meta_desc, 0, $last_period + 1);
        } else {
            // If no period found, try to end at a space to avoid cutting words
            $last_space = strrpos(substr($meta_desc, 0, 157), ' ');
            if ($last_space !== false) {
                $meta_desc = substr($meta_desc, 0, $last_space) . '...';
            }
        }
        
        return $meta_desc;
    }

    public function ajax_get_template_url() {
        check_ajax_referer('pseo_nonce', 'nonce');

        if (!current_user_can('edit_pages')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if (!$template_id) {
            wp_send_json_error(array('message' => 'Invalid template ID'));
            return;
        }

        $url = get_permalink($template_id);
        $title = get_the_title($template_id);
        
        if (!$url) {
            wp_send_json_error(array('message' => 'Template URL not found'));
            return;
        }

        wp_send_json_success(array(
            'url' => $url,
            'title' => $title
        ));
    }

    public function ajax_generate_content() {
        check_ajax_referer('pseo_nonce', 'nonce');

        if (!current_user_can('publish_posts')) {
            wp_send_json_error('Unauthorized access');
        }

        $title = sanitize_text_field($_POST['title']);
        $keywords = sanitize_text_field($_POST['keywords']);
        $tone = sanitize_text_field($_POST['tone']);
        $word_count = intval($_POST['word_count']);
        $page_builder = sanitize_text_field($_POST['page_builder']);

        // Create the prompt for the AI
        $prompt = "Write a {$word_count} word article about {$title}. ";
        $prompt .= "Use these keywords: {$keywords}. ";
        $prompt .= "The tone should be {$tone}. ";
        $prompt .= "Format the content with proper headings, paragraphs, and sections.";

        // Generate content using the API
        $content = $this->api_handler->generate_content($prompt, $page_builder);

        if (is_wp_error($content)) {
            wp_send_json_error($content->get_error_message());
        }

        // Create the WordPress page
        $page_data = array(
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => 'draft',
            'post_type'     => 'page'
        );

        $page_id = wp_insert_post($page_data);

        if (is_wp_error($page_id)) {
            wp_send_json_error('Failed to create page: ' . $page_id->get_error_message());
        }

        // Add meta data
        update_post_meta($page_id, '_pseo_generated', true);
        update_post_meta($page_id, '_pseo_keywords', $keywords);
        update_post_meta($page_id, '_pseo_page_builder', $page_builder);

        // Generate and save meta description
        $meta_description = wp_trim_words(strip_tags($content), 25, '...');
        update_post_meta($page_id, '_pseo_meta_description', $meta_description);

        wp_send_json_success(array(
            'page_id' => $page_id,
            'edit_url' => get_edit_post_link($page_id, 'raw'),
            'preview_url' => get_preview_post_link($page_id)
        ));
    }
} 