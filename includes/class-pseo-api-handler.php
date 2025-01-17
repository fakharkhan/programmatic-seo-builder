<?php

class PSEO_API_Handler {
    private $api_key;
    private $api_endpoint = 'https://api.deepseek.com/v1/chat/completions';

    public function __construct() {
        $this->api_key = get_option('pseo_api_key');
        add_action('wp_ajax_pseo_test_api', array($this, 'test_api_connection'));
    }


    public function test_api_connection() {
        check_ajax_referer('pseo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
        }

        $api_key = sanitize_text_field($_POST['api_key']);
        
        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'deepseek-chat',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => 'Test connection'
                    )
                ),
                'max_tokens' => 5
            ))
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('API connection failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            wp_send_json_error('API Error: ' . $body['error']['message']);
        }

        wp_send_json_success(array('message' => 'API connection successful'));
    }

    public function generate_content($prompt, $page_builder = 'gutenberg') {
        if (empty($this->api_key)) {
            return new WP_Error('api_key_missing', 'API key is not configured');
        }

        // Get page builder specific instructions
        $builder_instructions = $this->get_page_builder_instructions($page_builder);

        // Prepare the request payload
        $payload = array(
            'model' => 'deepseek-chat',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a professional content writer and WordPress expert. Create high-quality, SEO-optimized content. ' . 
                                'Format the content specifically for ' . ucfirst($page_builder) . '. ' . $builder_instructions
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 2000,
            'temperature' => 0.7
        );

        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($payload),
            'timeout' => 60,
            'sslverify' => false,
            'httpversion' => '1.1',
            'blocking' => true
        ));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('PSEO API Error: ' . $error_message);
            if (strpos($error_message, 'timed out') !== false) {
                return new WP_Error('api_timeout', 'The request timed out. Please try again.');
            }
            return new WP_Error('api_error', 'API Error: ' . $error_message);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $error_message = wp_remote_retrieve_response_message($response);
            error_log('PSEO API Error: ' . $response_code . ' - ' . $error_message);
            return new WP_Error('api_error', 'API Error: ' . $error_message . ' (Code: ' . $response_code . ')');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body) || !is_array($body)) {
            return new WP_Error('api_error', 'Invalid response from API');
        }
        
        if (isset($body['error'])) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown API error';
            error_log('PSEO API Error: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            return new WP_Error('api_error', 'Invalid response format from API');
        }

        // Get the content and clean it up
        $content = $this->cleanup_generated_content($body['choices'][0]['message']['content']);

        return $content;
    }

    /**
     * Clean up the generated content by removing unnecessary formatting
     *
     * @param string $content The raw content from the API
     * @return string The cleaned content
     */
    private function cleanup_generated_content($content) {
        // Remove backticks and html tags if they wrap the entire content
        $content = preg_replace('/^`html\s*|`$/i', '', $content);
        $content = preg_replace('/^<html>\s*|<\/html>$/i', '', $content);

        // Remove any remaining backtick formatting
        $content = preg_replace('/^```\s*|```$/m', '', $content);

        // Ensure proper paragraph formatting
        $content = preg_replace('/\n{3,}/', "\n\n", $content); // Replace multiple newlines with double newlines
        
        // Clean up any markdown-style headers that might have been included
        $content = preg_replace('/^#{1,6}\s/m', '', $content);

        return trim($content);
    }

    /**
     * Get specific instructions for different page builders
     *
     * @param string $page_builder The selected page builder
     * @return string Instructions for the AI
     */
    private function get_page_builder_instructions($page_builder) {
        $instructions = array(
            'gutenberg' => 'Use WordPress Gutenberg blocks format. Structure content with <!-- wp:paragraph --> and <!-- wp:heading --> blocks. ' .
                          'Use h2 for main sections and h3 for subsections. Include proper spacing between blocks.',
            
            'elementor' => 'Format content for Elementor. Use standard HTML with proper section and div structures. ' .
                          'Use h2 for main sections and h3 for subsections. Add class="elementor-heading-title" to headings.',
            
            'divi-builder' => 'Format content for Divi Builder. Use standard HTML with proper section and div structures. ' .
                             'Add class="et_pb_text_inner" to text containers. Use h2 for main sections and h3 for subsections.',
            
            'wpbakery' => 'Format content for WPBakery Page Builder. Use [vc_row] and [vc_column] shortcodes. ' .
                         'Wrap text in [vc_column_text] shortcodes. Use h2 for main sections and h3 for subsections.',
            
            'oxygen' => 'Format content for Oxygen Builder. Use standard HTML with proper section and div structures. ' .
                       'Use h2 for main sections and h3 for subsections.',
            
            'fusion-builder' => 'Format content for Avada Fusion Builder. Use [fusion_text] shortcodes for text blocks. ' .
                               'Use [fusion_title] for headings. Structure content in [fusion_builder_container] and [fusion_builder_row].'
        );

        return isset($instructions[$page_builder]) ? $instructions[$page_builder] : $instructions['gutenberg'];
    }

}