<?php

namespace TypechoPlugin\OidcLogin;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Checkbox;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Layout;
use Widget\Options;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * OpenID Connect 登录插件
 *
 * @package OidcLogin
 * @author Jim Kirisame
 * @version 0.2.0
 * @link https://github.com/Lotlab/OidcLogin
 */
class Plugin implements PluginInterface
{
    public static function redirect_uri()
    {
        return Helper::options()->adminUrl . 'OidcLogin/callback';
    }

    public static function activate()
    {
        \Typecho\Plugin::factory('admin/footer.php')->end = __CLASS__ . '::render';

        Helper::addRoute('oidc_callback', __TYPECHO_ADMIN_DIR__ . 'OidcLogin/callback', 'TypechoPlugin\OidcLogin\Callback', 'callback');
        Helper::addRoute('oidc_login', __TYPECHO_ADMIN_DIR__ . 'OidcLogin/login', 'TypechoPlugin\OidcLogin\Callback', 'login');
    }

    public static function deactivate()
    {
    }

    public static function config(Form $form)
    {
        $client_id = new Text('client_id', null, '', _t('Client ID'), _t('客户端ID'));
        $form->addInput($client_id);
        $client_sk = new Password('client_sk', null, '', _t('Client Secret Key'), _t('客户端密钥'));
        $form->addInput($client_sk);
        $scope = new Text('scope', null, 'email profile openid groups', _t('OpenID Scope'), _t('请求的权限，如 email profile openid groups'));
        $form->addInput($scope);
        $authorize_url = new Text('authorize_url', null, 'https://example.org/oauth/authorize', _t('Authorization URL'));
        $form->addInput($authorize_url);
        $token_url = new Text('token_url', null, 'https://example.org/oauth/token', _t('Token URL'));
        $form->addInput($token_url);

        // OpenID connect callback URL
        $callback = new Layout('div', ['class' => 'typecho-option']);
        $callback->addItem((new Layout('label', ['class' => 'typecho-label']))->html(_t('回调 URL')));
        $callback->addItem((new Layout('p', ['class' => 'description']))->html(self::redirect_uri()));
        $form->addItem($callback);
        
        // account association
        $identity_method = new Radio('id_method', [_t('电子邮件'), _t('用户名')], 1, _t('账户关联方法'), _t('根据邮件或用户名决定关联到哪个已有的账户'));
        $form->addInput($identity_method);

        // user creation
        $create_user = new Radio('create_user', [_t('不创建'), _t('创建')], 0, _t('自动创建用户'), _t('当用户不存在时是否要自动创建用户'));
        $form->addInput($create_user);

        $btn_name = new Text('btn_name', null, 'OIDC 登录', _t('按钮文本'));
        $form->addInput($btn_name);
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function render()
    {
        if (!self::user()->hasLogin()) {
            echo '<script>
                function add_oidc_login() {
                    const forms = document.getElementsByName("login");
                    if (forms.length < 0) return;

                    const link = document.createElement("button");
                    link.innerText = "' . Helper::options()->plugin('OidcLogin')->btn_name . '";
                    link.classList.add("btn", "primary");
                    link.onclick = oidc_login;

                    forms[0].after(link);
                }

                function oidc_login() {
                    document.location = "' . Helper::options()->adminUrl . 'OidcLogin/login";
                }

                add_oidc_login();
            </script>';
        }
    }

    public static function options()
    {
        return Helper::options()->plugin('OidcLogin');
    }

    public static function user()
    {
        return Widget::widget('Widget_User');
    }
}
