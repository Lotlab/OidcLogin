<?php

namespace TypechoPlugin\OidcLogin;

use Typecho\Widget;
use Typecho\Common;
use Typecho\Db;
use Typecho\Cookie;
use Utils\Helper;
use Utils\PasswordHash;

use Exception;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class Callback extends Widget
{
    public function callback()
    {
        if (isset($_GET['error'])) {
            throw new Exception($_GET['error'] . '. ' . $_GET['error_description']);
        }

        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            throw new Exception(_t('invalid request'));
        }

        $options = Plugin::options();

        // request token
        $code = $_GET['code'];
        $ret = self::curl_post($options->token_url, array(
            'grant_type' => 'authorization_code',
            'client_id' => $options->client_id,
            'client_secret' => $options->client_sk,
            'redirect_uri' => Plugin::redirect_uri(),
            'code' => $code
        ));
        $obj = json_decode($ret);

        // parse token
        if (isset($obj->error)) {
            throw new Exception($obj->error . '. ' . $obj->error_description);
        }

        $sub_arr = preg_split('/\./', $obj->id_token);
        $auth_obj = json_decode(base64_decode($sub_arr[1]));

        // 查找用户
        $user = NULL;
        $db = Db::get();
        $method = $options->id_method;
        switch ($method) {
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

        // 用户不存在
        if (empty($user)) {
            if (!$options->create_user) {
                throw new Exception(_t('用户不存在'));
            }

            if (!$auth_obj->email_verified) {
                throw new Exception(_t('用户邮箱未验证'));
            }

            // 注册用户
            $user = [
                'name' => $auth_obj->preferred_username,
                'mail' => $auth_obj->email,
                'screenName' => $auth_obj->name,
                'group' => 'subscriber'
            ];
            $user = $this->register($user);
        }

        Plugin::user()->simpleLogin($user['uid'], false);
        $this->redirect(Helper::options()->adminUrl);
        echo 'login success!';
    }

    function register($user)
    {
        if (Plugin::user()->mailExists($user['mail']) || Plugin::user()->nameExists($user['name'])) {
            throw new Exception(_t('邮箱或用户名重复，无法创建用户'));
        }

        $hasher = new PasswordHash(8, true);
        $generatedPassword = Common::randString(7);

        $user['password'] = $hasher->hashPassword($generatedPassword);
        $user['created'] = $this->options->time;
        $user['screenName'] = empty($user['screenName']) ? $user['name'] : $user['screenName'];
        $user['uid'] = Plugin::user()->insert($user);

        return $user;
    }

    public function login()
    {
        if (Widget::widget('Widget_User')->hasLogin()) {
            $this->redirect(Helper::options()->adminUrl);
            return;
        }

        $options = Plugin::options();
        $url = $options->authorize_url
            . '?client_id=' . $options->client_id
            . '&redirect_uri=' . Plugin::redirect_uri()
            . '&scope=' . $options->scope
            . '&response_type=code'
            . '&state=' . $this->rand_string();

        $this->redirect($url);
    }

    private function rand_string()
    {
        return function_exists('openssl_random_pseudo_bytes') ? bin2hex(openssl_random_pseudo_bytes(16)) : sha1(Common::randString(20));
    }

    static function curl_post($url, $postData)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $content = curl_exec($ch);
        $header = curl_getinfo($ch);
        curl_close($ch);
        return $content;
    }

    private function redirect($url)
    {
        echo '<meta http-equiv="refresh" content="1;url=' . $url . '"><a href="' . $url . '">Redirecting...</a>';
        header('Location: ' . $url, true, 307);
        exit();
    }
}
