<?php

class PSEO_Page_Generator {
    private $api_handler;

    public function __construct() {
        $this->api_handler = new PSEO_API_Handler();
        add_action('wp_ajax_pseo_clone_page', array($this, 'ajax_clone_page'));
        add_action('wp_ajax_pseo_get_template_url', array($this, 'ajax_get_template_url'));
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

        // Get the template page
        $template = get_post($template_id);
        if (!$template) {
            wp_send_json_error(array('message' => 'Template page not found'));
            return;
        }

        // Create the new page
        $new_page = array(
            'post_title'    => $this->replace_placeholders($template->post_title, $replacements),
            'post_content'  => $this->replace_placeholders($template->post_content, $replacements),
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

        // Copy template meta
        $template_meta = get_post_meta($template_id);
        if ($template_meta) {
            foreach ($template_meta as $key => $values) {
                if (in_array($key, array('_edit_lock', '_edit_last'))) continue;
                
                foreach ($values as $value) {
                    $meta_value = maybe_unserialize($value);
                    if (is_string($meta_value)) {
                        $meta_value = $this->replace_placeholders($meta_value, $replacements);
                    }
                    update_post_meta($new_page_id, $key, $meta_value);
                }
            }
        }

        // Generate and update meta description
        $meta_description = $this->generate_meta_description($new_page_id, $replacements);
        update_post_meta($new_page_id, '_pseo_meta_description', $meta_description);

        wp_send_json_success(array(
            'page_id' => $new_page_id,
            'edit_url' => get_edit_post_link($new_page_id, 'url'),
            'view_url' => get_permalink($new_page_id)
        ));
    }

    private function replace_placeholders($content, $replacements) {
        foreach ($replacements as $type => $replacement) {
            $content = str_replace($replacement['find'], $replacement['replace'], $content);
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
} 