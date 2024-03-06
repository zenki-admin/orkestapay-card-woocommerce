<?php
if (!defined('ABSPATH')) {
    exit();
}

/**
 * OrkestaPayCard_API class.
 *
 * Communicates with OrkestaPay API.
 */
class OrkestaPayCard_API
{
    /**
     * Transient name for OrkestaPay's token
     */
    const ORKESTAPAY_TOKEN = 'orkestapay_card_token';

    /**
     * OrkestaPay's token expiration time in seconds
     */
    const ORKESTAPAY_TOKEN_EXPIRATION = 120;

    /**
     * ID API Key.
     *
     * @var string
     */
    private static $client_id = '';

    /**
     * Secret API Key.
     *
     * @var string
     */
    private static $client_secret = '';

    /**
     * Set ID API Key.
     *
     * @param string $key
     */
    public static function set_client_id($client_id)
    {
        self::$client_id = $client_id;
    }

    /**
     * Get ID key.
     *
     * @return string
     */
    public static function get_client_id()
    {
        if (!self::$client_id) {
            $options = get_option('woocommerce_orkesta_settings');
            $client_id = $options['client_id'] ?? '';

            self::set_client_id($client_id);
        }
        return self::$client_id;
    }

    /**
     * Set secret API Key.
     *
     * @param string $key
     */
    public static function set_client_secret($client_secret)
    {
        self::$client_secret = $client_secret;
    }

    /**
     * Get secret key.
     *
     * @return string
     */
    public static function get_client_secret()
    {
        if (!self::$client_secret) {
            $options = get_option('woocommerce_orkesta_settings');
            $client_secret = $options['client_secret'] ?? '';

            self::set_client_secret($client_secret);
        }
        return self::$client_secret;
    }

    /**
     * Generates the headers to pass to API request.
     *
     */
    public static function get_headers()
    {
        $clientId = self::get_client_id();
        $clientSecret = self::get_client_secret();
        $tokenResult = self::get_access_token($clientId, $clientSecret);

        if (!array_key_exists('access_token', $tokenResult)) {
            throw new Exception(__('There was a problem getting the access token.', 'orkestapay-card'));
        }

        $headers = [
            'Authorization' => 'Bearer ' . $tokenResult['access_token'],
            'Content-Type' => 'application/json',
        ];

        return $headers;
    }

    /**
     * Send the request to OrkestaPay's API
     *
     * @param array  $request
     * @param string $api
     * @param string $method
     * @param bool   $with_headers To get the response with headers.
     * @return stdClass|array
     * @throws WP_Error
     */
    public static function request($request, $api, $method = 'POST', $with_headers = false)
    {
        $headers = self::get_headers();
        $pattern = '/payments/i';
        $idempotency_key = '';

        if (preg_match($pattern, $api) === 1 && ($method === 'POST' || $method === 'PATCH')) {
            $idempotency_key = $request['metadata']['merchant_order_id'] . '-' . time();
            $headers['Idempotency-Key'] = wp_hash($idempotency_key, 'nonce');
        }

        $response = wp_safe_remote_post($api, [
            'method' => $method,
            'headers' => $headers,
            'body' => json_encode($request),
            'timeout' => 60,
        ]);

        $responseCode = wp_remote_retrieve_response_code($response);

        if (is_wp_error($response) || empty($response['body']) || ($responseCode < 200 || $responseCode >= 300)) {
            $responseMessage = empty($response['body']) ? wp_remote_retrieve_response_message($response) : json_decode($response['body'], true)['message'];
            OrkestaPayCard_Logger::error('#request', ['api' => $api, 'headers' => $headers, 'request' => json_encode($request), 'error_code' => $responseCode, 'error_message' => $response]);

            throw new Exception($responseMessage, $responseCode);
        }

        OrkestaPayCard_Logger::log('#request', ['api' => $api, 'headers' => $headers, 'request' => json_encode($request), 'response_code' => $responseCode, 'response' => $response['body']]);

        if ($with_headers) {
            return [
                'headers' => wp_remote_retrieve_headers($response),
                'body' => json_decode($response['body']),
            ];
        }

        return json_decode($response['body']);
    }

    /**
     * Retrieve API endpoint.
     *
     * @param string $api
     */
    public static function retrieve($api, $extraHeaders = [])
    {
        $headers = self::get_headers();

        $response = wp_safe_remote_get($api, [
            'method' => 'GET',
            'headers' => array_merge($headers, $extraHeaders),
            'timeout' => 30,
        ]);

        $responseCode = wp_remote_retrieve_response_code($response);

        if (is_wp_error($response) || empty($response['body']) || ($responseCode < 200 || $responseCode >= 300)) {
            $responseMessage = wp_remote_retrieve_response_message($response);
            OrkestaPayCard_Logger::error('#retrieve', ['api' => $api, 'headers' => $headers, 'error_code' => $responseCode, 'error_message' => $responseMessage]);

            throw new Exception(__('There was a problem connecting to the Orkesta API endpoint.', 'orkestapay-card'), $responseCode);
        }

        OrkestaPayCard_Logger::log('#retrieve', ['api' => $api, 'headers' => $headers, 'response_code' => $responseCode, 'response' => $response['body']]);

        return json_decode($response['body']);
    }

    /**
     * Get OrkestaPay's access token
     *
     * @return array
     */
    public static function get_access_token($client_id, $client_secret)
    {
        $orkestapay = new OrkestaPayCard_Gateway();
        $apiHost = $orkestapay->getApiHost();

        $token = get_transient(self::ORKESTAPAY_TOKEN);
        if ($token) {
            return json_decode($token, true);
        }

        $request = ['client_id' => $client_id, 'client_secret' => $client_secret, 'grant_type' => 'client_credentials'];

        $response = wp_safe_remote_request("$apiHost/v1/oauth/tokens", [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($request),
            'timeout' => 30,
        ]);

        $responseCode = wp_remote_retrieve_response_code($response);

        if (is_wp_error($response) || empty($response['body']) || ($responseCode < 200 || $responseCode >= 300)) {
            $responseMessage = empty($response['body']) ? wp_remote_retrieve_response_message($response) : json_decode($response['body'], true)['message'];
            OrkestaPayCard_Logger::error('#get_access_token', ['api' => "$apiHost/v1/oauth/tokens", 'error_code' => $responseCode, 'error_message' => $response]);

            throw new Exception($responseMessage, $responseCode);
        }

        OrkestaPayCard_Logger::log('#get_access_token', ['api' => "$apiHost/v1/oauth/tokens", 'response_code' => $responseCode, 'response' => $response['body']]);

        set_transient(self::ORKESTAPAY_TOKEN, $response['body'], self::ORKESTAPAY_TOKEN_EXPIRATION);

        return json_decode($response['body'], true);
    }
}
