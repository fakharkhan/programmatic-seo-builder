<?php

class PSEO_API_Handler {
    private $api_key;
    private $api_endpoint = 'https://api.deepseek.com/v1/chat/completions';

    public function __construct() {
        $this->api_key = get_option('pseo_api_key');
        add_action('wp_ajax_pseo_test_api', array($this, 'test_api_connection'));
        add_action('wp_ajax_pseo_generate_content', array($this, 'generate_content'));
    }

    public function generate_content($template_content, $location, $keyword, $skill_set) {
        if (empty($this->api_key)) {
            return new WP_Error('api_key_missing', 'DeepSeek API key is not configured');
        }

        // Get common definitions
        $common_definitions = get_option('pseo_common_definitions', '');

        // Prepare the prompt
        $prompt = $this->prepare_prompt($template_content, $location, $keyword, $skill_set, $common_definitions);

        $response = wp_remote_post($this->api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'deepseek-chat',
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are an HTML content generator. You must output ONLY valid HTML content, never Markdown. Always use proper HTML tags for formatting.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'response_format' => array('type' => 'text'),
                'stop' => ['```']
            )),
            'timeout' => 60
        ));

        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        // Extract the content from the response
        $generated_content = $body['choices'][0]['message']['content'];
        
        // Clean up any potential markdown or code block markers
        $generated_content = preg_replace('/^```html\s*|\s*```$/i', '', trim($generated_content));
        
        // Ensure proper HTML structure
        if (!preg_match('/<[^>]+>/', $generated_content)) {
            // If no HTML tags found, wrap in paragraph tags
            $generated_content = '<p>' . $generated_content . '</p>';
        }

        return $generated_content;
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
} 