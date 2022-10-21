<?php

namespace TypechoPlugin\OidcLogin;
use Typecho\Widget;
use Typecho\Common;
use Typecho\Db;
use Typecho\Cookie;
use Utils\Helper;

use Exception;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Callback extends Widget
{
    public function callback() {
        if (!isset($_GET['state'])) {
            throw new Exception(_t('invalid request'));
        }

        if (isset($_GET['error'])) {
            throw new Exception($_GET['error'].'. '.$_GET['error_description']);
        }

        if (!isset($_GET['code'])) {
            throw new Exception(_t('invalid request'));
        }

        $options = Helper::options()->plugin('OidcLogin');

        // request token
        $code = $_GET['code'];
        $ret = self::curl_post($options->token_url, Array(
            'grant_type' => 'authorization_code',
            'client_id' => $options->client_id,
            'client_secret' => $options->client_sk,
            'redirect_uri' => $this->redirect_uri(),
            'code' => $code
        ));
        $obj = json_decode($ret);

        // parse token
        if (isset($obj->error)) {
            throw new Exception($obj->error.'. '.$obj->error_description);
        }

        $sub_arr = preg_split('/\./', $obj->id_token);
        $auth_obj = json_decode(base64_decode($sub_arr[1]));

        $user = NULL;
        $db = Db::get();
        $method = $options->id_method;
        switch($method) {
            case 0: // mail
                if (!$auth_obj->email_verified) {
                    throw new Exception(_t('用户邮箱未验证'));
                }

                $user = $db->fetchRow($db->select()->from('table.users')->where('mail = ?', $auth_obj->email)->limit(1));
            break;
            case 1: // username
                $user = $db->fetchRow($db->select()->from('table.users')->where('name = ?', $auth_obj->preferred_username)->limit(1));
            break;
        }

        // Auth success!
        if(!empty($user)) {
            $authCode = $this->rand_string();
            $user['authCode'] = $authCode;
            Cookie::set('__typecho_uid', $user['uid'], 0);
            Cookie::set('__typecho_authCode', Common::hash($authCode), 0);
            $db->query($db->update('table.users')->expression('logged', 'activated')->rows(['authCode' => $authCode])->where('uid = ?', $user['uid']));

            $this->push($user);
            $this->currentUser = $user;
            $this->hasLogin = true;

            $this->redirect(Helper::options()->adminUrl);
            echo 'login success!';
            return;
        }

        if(!$options->create_user) {
            throw new Exception(_t('用户不存在'));
        }

        // todo: create user

    }

    public function login() {
        if (!empty($user)) {
            $this->redirect(Helper::options()->adminUrl);
            return;
        }

        $options = Helper::options()->plugin('OidcLogin');
        $url = $options->authorize_url
            .'?client_id='.$options->client_id
            .'&redirect_uri='.$this->redirect_uri()
            .'&scope='.$options->scope
            .'&response_type=code'
            .'&state='.$this->rand_string();

        $this->redirect($url);
    }

    private function rand_string() {
        return function_exists('openssl_random_pseudo_bytes') ? bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Common::randString(20));
    }

    static function curl_post($url, $postData) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $content = curl_exec($ch);
        $header = curl_getinfo($ch);
        curl_close($ch);
        // return array($header,$content);
        return $content;
    }

    private function redirect_uri() {
        return Helper::options()->adminUrl.'OidcLogin/callback';
    }

    private function redirect($url) {
        header('Location: '.$url, true, 307);
        exit();
    }
}
