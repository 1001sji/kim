(function($){
    'use strict';
    window.chk_captcha = function(){
        if(!$("[name=cf-turnstile-response]").val()) {
            alert("자동등록방지를 반드시 체크해 주세요.");
            return false;
        }
        return true;
    };
})(jQuery);
