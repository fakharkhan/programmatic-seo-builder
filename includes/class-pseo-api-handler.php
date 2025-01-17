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

        // Core system prompt for content generation
        $system_prompt = 'You are an expert SEO content writer. Generate high-quality, SEO-optimized content that is: ' .
                        '1. Engaging and value-driven ' .
                        '2. Properly structured with clear hierarchy ' .
                        '3. Optimized for search engines and readability ' .
                        '4. Focused on user intent and conversion. ' .
                        'Content Requirements: ' .
                        '- Start with a compelling H1 title incorporating the main keyword naturally ' .
                        '- Include a meta description (150-160 characters) ' .
                        '- Create logical sections with H2 and H3 headings ' .
                        '- Add relevant internal links and clear CTAs ' .
                        '- Use bullet points and formatting for better readability. ' .
                        'Technical Requirements: ' . $builder_instructions;

        // Enhanced user prompt with content structure guidance
        $enhanced_prompt = $prompt . "\n\n" .
            "Content Structure Guide:\n" .
            "1. Title Section:\n" .
            "   - SEO-optimized H1 title\n" .
            "   - Engaging meta description\n" .
            "2. Introduction:\n" .
            "   - Hook the reader\n" .
            "   - Present the value proposition\n" .
            "3. Main Content:\n" .
            "   - Logical H2 sections\n" .
            "   - Supporting H3 subsections\n" .
            "   - Relevant examples and evidence\n" .
            "4. Conclusion:\n" .
            "   - Clear summary\n" .
            "   - Actionable next steps\n" .
            "\nUse only clean HTML (<h1>, <h2>, <h3>, <p>, <ul>, <li>, <a>, <strong>, <em>).";

        // Prepare the request payload with optimized parameters
        $payload = array(
            'model' => 'deepseek-coder',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $system_prompt
                ),
                array(
                    'role' => 'user',
                    'content' => $enhanced_prompt
                )
            ),
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'top_p' => 0.9,
            'frequency_penalty' => 0.3,
            'presence_penalty' => 0.3
        );

        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($payload),
            'timeout' => 120, // Increased timeout for longer content
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
        
        // Validate content structure
        if (!$this->validate_content_structure($content)) {
            return new WP_Error('content_structure', 'Generated content does not meet structural requirements');
        }

        return $content;
    }

    /**
     * Clean up the generated content by removing unnecessary formatting
     *
     * @param string $content The raw content from the API
     * @return string The cleaned content
     */
    private function cleanup_generated_content($content) {
        // Remove code block markers
        $content = preg_replace('/```html\s*/', '', $content);
        $content = preg_replace('/```\s*/', '', $content);
        
        // Remove any DOCTYPE or HTML/BODY tags
        $content = preg_replace('/<(!DOCTYPE|html|body|head)[^>]*>/', '', $content);
        $content = preg_replace('/<\/(html|body|head)>/', '', $content);
        
        // Clean up multiple blank lines
        $content = preg_replace("/[\r\n]+/", "\n", $content);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Ensure proper spacing around HTML tags
        $content = preg_replace('/>\s+</', ">\n<", $content);
        
        return trim($content);
    }

    /**
     * Validate the structure of generated content
     *
     * @param string $content The generated content
     * @return boolean Whether the content meets structural requirements
     */
    private function validate_content_structure($content) {
        // Check for required elements
        $has_h1 = preg_match('/<h1[^>]*>.*?<\/h1>/', $content);
        $has_h2 = preg_match('/<h2[^>]*>.*?<\/h2>/', $content);
        $has_paragraphs = preg_match('/<p[^>]*>.*?<\/p>/', $content);
        
        // Ensure basic structure requirements are met
        if (!$has_h1 || !$has_h2 || !$has_paragraphs) {
            return false;
        }
        
        return true;
    }

    /**
     * Get specific instructions for different page builders
     *
     * @param string $page_builder The selected page builder
     * @return string Instructions for the AI
     */
    private function get_page_builder_instructions($page_builder) {
        return 'Use only these HTML elements for content structure: ' .
               '<h1> for main title, ' .
               '<h2> for sections, ' .
               '<h3> for subsections, ' .
               '<p> for paragraphs, ' .
               '<ul>/<li> for lists, ' .
               '<a> for links, ' .
               '<strong> for emphasis, ' .
               '<em> for italic text. ' .
               'Maintain clean, semantic HTML without any page builder-specific code.';
    }

}