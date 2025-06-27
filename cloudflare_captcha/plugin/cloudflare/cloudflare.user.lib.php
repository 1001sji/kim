<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가

// 캡챠 HTML 코드 출력
function captcha_html($class="captcha")
{

    global $config;
    
    $html = '<fieldset id="captcha" class="captcha cloudflare">';
    $html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js"></script>';
    $html .= "\n".'<script>var g5_captcha_url  = "'.G5_CAPTCHA_URL.'";</script>';
    $html .= "\n".'<script src="'.G5_CAPTCHA_URL.'/captcha.js"></script>';
    $html .= '<div class="cf-turnstile" data-sitekey="'.$config['cf_captcha_site_key'].'" data-callback="cfCaptchaCallback"></div>';
    $html .= '</fieldset>';

	return $html;
}

// 캡챠 사용시 자바스크립트에서 입력된 캡챠를 검사함
function chk_captcha_js()
{
	return "if (!chk_captcha()) return false;\n";
}

function chk_captcha(){

    global $config;

	$resp = null;
	
    $turnstile_resp = trim(filter_input(INPUT_POST, 'cf-turnstile-response', FILTER_UNSAFE_RAW));
    if ($turnstile_resp) {
        $cloudFlareCaptcha = new CloudFlareCaptcha_GNU($config['cf_captcha_secret_key']);
        $resp = $cloudFlareCaptcha->verify($turnstile_resp);
    }

    if( ! $resp ){
        return false;
    }

    if ($resp != null && $resp->success) {
        return true;
    }

    return false;
}
