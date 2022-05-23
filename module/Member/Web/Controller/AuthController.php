<?php


namespace Module\Member\Web\Controller;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use ModStart\Core\Input\InputPackage;
use ModStart\Core\Input\Request;
use ModStart\Core\Input\Response;
use ModStart\Core\Util\CurlUtil;
use ModStart\Module\ModuleBaseController;
use Module\Member\Auth\MemberUser;



class AuthController extends ModuleBaseController
{
    
    private $api;

    public function __construct()
    {
        $this->api = app(\Module\Member\Api\Controller\AuthController::class);
    }

    public function login()
    {
        $input = InputPackage::buildFromInput();
        $redirect = $input->getTrimString('redirect', modstart_web_url('member'));
        if (modstart_config('ssoClientEnable', false)) {
            Input::merge(['client' => Request::domainUrl() . '/sso/client']);
            $ret = $this->api->ssoClientPrepare();
            if ($ret['code']) {
                return Response::send(-1, $ret['msg']);
            }
            Session::put('ssoClientRedirect', $redirect);
            return Response::send(0, null, null, $ret['data']['redirect']);
        }
        if (Request::isPost()) {
            $ret = $this->api->login();
            if (Response::isError($ret)) {
                return Response::sendFromGenerate($ret);
            }
            return Response::send(0, '', '', $redirect);
        }
        return $this->view('login', ['redirect' => $redirect]);
    }

    public function loginCaptcha()
    {
        return $this->api->loginCaptchaRaw();
    }

    public function logout()
    {
        $input = InputPackage::buildFromInput();
        $redirect = $input->getTrimString('redirect', modstart_web_url(''));
        if (modstart_config('ssoClientEnable', false) && modstart_config('ssoClientLogoutSyncEnable', false)) {
            Input::merge(['domainUrl' => Request::domainUrl()]);
            $ret = $this->api->ssoClientLogoutPrepare();
            if ($ret['code']) {
                return Response::send(-1, $ret['msg']);
            }
            Session::put('ssoLogoutRedirect', $redirect);
            return Response::send(0, null, null, $ret['data']['redirect']);
        }
        $ret = $this->api->logout();
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        $input = InputPackage::buildFromInput();
        Session::forget('memberUserId');
        $redirect = $input->getTrimString('redirect', modstart_web_url(''));
        return Response::redirect($redirect);
    }

    public function register()
    {
        $input = InputPackage::buildFromInput();
        $redirect = $input->getTrimString('redirect', modstart_web_url('member'));
        if (Request::isPost()) {
            $ret = $this->api->register();
            if ($ret['code']) {
                if ($input->getTrimString('captcha')) {
                    return Response::send(-1, $ret['msg'], null, '[js]$("[data-captcha]").click()');
                }
                return Response::send(-1, $ret['msg']);
            }
            return Response::send(0, '', '', modstart_web_url('login') . '?redirect=' . urlencode($redirect));
        }
        return $this->view('register', [
            'redirect' => $redirect,
        ]);
    }

    public function registerEmailVerify()
    {
        return Response::sendFromGenerate($this->api->registerEmailVerify());
    }

    public function registerPhoneVerify()
    {
        return Response::sendFromGenerate($this->api->registerPhoneVerify());
    }

    public function registerCaptcha()
    {
        return $this->api->registerCaptchaRaw();
    }

    public function registerCaptchaVerify()
    {
        $ret = $this->api->registerCaptchaVerify();
        if ($ret['code']) {
            return Response::send(-1, $ret['msg'], null, '[js]$("[data-captcha]").click()');
        }
        return Response::send(0, $ret['msg']);
    }

    public function retrieve()
    {
        $input = InputPackage::buildFromInput();
        $redirect = $input->getTrimString('redirect', modstart_web_url('member'));
        return $this->view('retrieve', [
            'redirect' => $redirect,
        ]);
    }

    public function retrievePhone()
    {
        $input = InputPackage::buildFromInput();
        $redirect = $input->getTrimString('redirect', modstart_web_url('member'));
        if (Request::isPost()) {
            $ret = $this->api->retrievePhone();
            if ($ret['code']) {
                return Response::send(-1, $ret['msg']);
            }
            return Response::send(0, $ret['msg'], null, modstart_web_url('retrieve') . '/reset?redirect=' . urlencode($redirect));
        }
        return $this->view('retrievePhone', [
            'redirect' => $redirect,
        ]);
    }

    public function retrievePhoneVerify()
    {
        return Response::sendFromGenerate($this->api->retrievePhoneVerify());
    }

    public function retrieveEmail()
    {
        $input = InputPackage::buildFromInput();
        $redirect = $input->getTrimString('redirect', modstart_web_url('member'));
        if (Request::isPost()) {
            $ret = $this->api->retrieveEmail();
            if ($ret['code']) {
                return Response::send(-1, $ret['msg']);
            }
            return Response::send(0, $ret['msg'], null, modstart_web_url('retrieve') . '/reset?redirect=' . urlencode($redirect));
        }
        return $this->view('retrieveEmail', [
            'redirect' => $redirect,
        ]);
    }

    public function retrieveEmailVerify()
    {
        return Response::sendFromGenerate($this->api->retrieveEmailVerify());
    }

    public function retrieveCaptcha()
    {
        return $this->api->retrieveCaptchaRaw();
    }

    public function retrieveReset()
    {
        $input = InputPackage::buildFromInput();
        $redirect = $input->getTrimString('redirect', modstart_web_url('member'));
        if (Request::isPost()) {
            $ret = $this->api->retrieveReset();
            if ($ret['code']) {
                return Response::send(-1, $ret['msg']);
            }
            return Response::send(0, $ret['msg'], null, $redirect);
        }
        $ret = $this->api->retrieveResetInfo();
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        return $this->view('retrieveReset', [
            'redirect' => $redirect,
            'memberUser' => $ret['data']['memberUser'],
        ]);
    }

    public function oauthLogin($oauthType = null)
    {
        $input = InputPackage::buildFromInput();
        $view = $input->getBoolean('view', false);
        if ($view) {
            Session::put('oauthLoginView', true);
        }
        $redirect = $input->getTrimString('redirect', modstart_web_url('member'));
        $callback = Request::domainUrl() . '/oauth_callback_' . $oauthType;
        $ret = $this->api->oauthLogin($oauthType, $callback);
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        Session::put('oauthRedirect', $redirect);
        return Response::redirect($ret['data']['redirect']);
    }

    public function oauthCallback($oauthType = null)
    {
        $callback = Request::domainUrl() . '/oauth_callback_' . $oauthType;
        $ret = $this->api->oauthCallback($oauthType, $callback);
        if ($ret['code']) {
            return Response::sendFromGenerate($ret);
        }
        $view = Session::get('oauthLoginView', false);
        Session::forget('oauthLoginView');
        if ($view) {
            $redirect = Session::get('oauthRedirect', modstart_web_url('member'));
            $oauthUserInfo = Session::get('oauthUserInfo');
            Session::put('oauthViewOpenId_' . $oauthType, $oauthUserInfo['openid']);
            return Response::redirect($redirect);
        }
        return Response::redirect(Request::domainUrl() . '/oauth_bind_' . $oauthType);
    }

    public function oauthBind($oauthType = null)
    {
        $redirect = Session::get('oauthRedirect', modstart_web_url('member'));
        if (Request::isPost()) {
            $ret = $this->api->oauthBind($oauthType);
            if ($ret['code']) {
                return Response::send(-1, $ret['msg']);
            }
            Session::forget('oauthRedirect');
            return Response::send(0, $ret['msg'], null, $redirect);
        }
        $oauthUserInfo = Session::get('oauthUserInfo', []);
        $ret = $this->api->oauthTryLogin($oauthType);
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        if ($ret['data']['memberUserId'] > 0) {
            Session::forget('oauthRedirect');
            return Response::redirect($redirect);
        }
        if (MemberUser::isLogin()) {
            $ret = $this->api->oauthBind($oauthType);
            if ($ret['code']) {
                return Response::send(-1, $ret['msg']);
            }
            Session::forget('oauthRedirect');
            return Response::send(0, '绑定成功', null, $redirect);
        }
        return $this->view('oauthBind', [
            'oauthUserInfo' => $oauthUserInfo,
            'redirect' => $redirect,
        ]);
    }

    public function oauthProxy()
    {
        return $this->view('oauthProxy');
    }

    public function ssoClient()
    {
        $ret = $this->api->ssoClient();
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        $redirect = Session::get('ssoClientRedirect', modstart_web_url('member'));
        return Response::send(0, null, null, $redirect);
    }

    public function ssoServer()
    {
        $input = InputPackage::buildFromInput();
        $ret = $this->api->ssoServer();
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        $serverSuccessUrl = '/sso/server_success?' . http_build_query([
                'client' => $input->getTrimString('client'),
                'domainUrl' => Request::domainUrl(),
            ]);
        if ($ret['data']['isLogin']) {
            return Response::send(0, null, null, $serverSuccessUrl);
        }
        return Response::send(0, null, null, modstart_web_url('login') . '?' . http_build_query(['redirect' => $serverSuccessUrl]));
    }

    public function ssoServerSuccess()
    {
        $ret = $this->api->ssoServerSuccess();
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        return Response::send(0, null, null, $ret['data']['redirect']);
    }

    public function ssoServerLogout()
    {
        $input = InputPackage::buildFromInput();
        $ret = $this->api->ssoServerLogout();
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        $redirect = $input->getTrimString('redirect', modstart_web_url(''));
        return Response::send(0, null, null, $redirect);
    }

    public function ssoClientLogout()
    {
        $ret = $this->api->ssoClientLogout();
        if ($ret['code']) {
            return Response::send(-1, $ret['msg']);
        }
        $redirect = Session::get('ssoLogoutRedirect', modstart_web_url(''));
        return Response::send(0, null, null, $redirect);
    }
}
