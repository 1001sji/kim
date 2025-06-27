<?php 
if ( ! class_exists( 'CloudFlareCaptchaResponse_v' ) ){
    class CloudFlareCaptchaResponse_v
    {
        public $success;
        public $errorCodes;
    }
}

class CloudFlareCaptcha_GNU {
	private static $_siteVerifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	
	/**
     * Shared secret for the site.
     * @var string
     */
    private $secret;

    /**
     * Create a configured instance to use the CloudFlare service.
     *
     * @param string $secret shared secret between site and reCAPTCHA server.
     * @param RequestMethod $requestMethod method used to send the request. Defaults to POST.
     * @throws \RuntimeException if $secret is invalid
     */
    public function __construct($secret)
    {
        if (empty($secret)) {
            throw new Exception('No secret provided');
        }

        if (!is_string($secret)) {
            throw new Exception('The provided secret must be a string');
        }

        $this->secret = $secret;
    }
    
    public function get_content($url, $data=array()) {

        $curlsession = curl_init();
        curl_setopt ($curlsession, CURLOPT_URL, $url);
        curl_setopt ($curlsession, CURLOPT_POST, 1);
        curl_setopt ($curlsession, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));
        curl_setopt ($curlsession, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt ($curlsession, CURLINFO_HEADER_OUT, false);
        curl_setopt ($curlsession, CURLOPT_HEADER, false);
        curl_setopt ($curlsession, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($curlsession, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt ($curlsession, CURLOPT_TIMEOUT, 3);

        $response = curl_exec($curlsession);
        $cinfo = curl_getinfo($curlsession);
        curl_close($curlsession);

        if ($cinfo['http_code'] != 200){
            return '';
        }
        return $response;
    }
    
    /**
     * Submits an HTTP GET to a CloudFlare server.
     *
     * @param string $path url path to CloudFlare server.
     * @param array  $data array of parameters to be sent.
     *
     * @return array response
     */
    private function submit($url, $data)
    {
        $response = $this->get_content($url, $data);
        return $response;
    }

    /**
     * Calls the CloudFlare siteverify API to verify whether the user passes
     * CAPTCHA test.
     *
     * @param string $remoteIp   IP address of end user.
     * @param string $response   response string from CloudFlare verification.
     *
     * @return CloudFlareCaptchaResponse_v
     */
    public function verify($response)
    {
        // Discard empty solution submissions
        if ($response == null || strlen($response) == 0) {
            $captchaResponse = new CloudFlareCaptchaResponse_v();
            $captchaResponse->success = false;
            $captchaResponse->errorCodes = 'missing-input';
            return $captchaResponse;
        }
        $getResponse = $this->submit(
            self::$_siteVerifyUrl,
            array (
                'secret' => $this->secret,
                'response' => $response
            )
        );
        // Cloudflare responds with a JSON string. Decode the response
        // only after ensuring we have a valid result.
        $answers = $getResponse ? json_decode($getResponse, true) : array();
        $captchaResponse = new CloudFlareCaptchaResponse_v();
        if (isset($answers['success']) && $answers['success'] == true) {
            $captchaResponse->success = true;
        } else {
            $captchaResponse->success = false;
            $captchaResponse->errorCodes = isset($answers['error-codes']) ? $answers['error-codes'] : 'http_error';
        }
        return $captchaResponse;
    }
}
