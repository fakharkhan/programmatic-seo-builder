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

}