<?php

namespace TypechoPlugin\OidcLogin;

use Typecho\Plugin\PluginInterface;
use Typecho\Widget;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Password;
use Typecho\Widget\Helper\Form\Element\Checkbox;
use Typecho\Widget\Helper\Form\Element\Radio;
use Widget\Options;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * OpenID Connect 登录插件
 *
 * @package OidcLogin
 * @author jim-kirisame
 * @version 0.1.0
 * @link https://lotlab.org
 */
class Plugin implements PluginInterface
{
    public static function activate()
    {
        \Typecho\Plugin::factory('admin/footer.php')->end = __CLASS__ . '::render';

        Helper::addRoute('oidc_callback', __TYPECHO_ADMIN_DIR__.'OidcLogin/callback', 'TypechoPlugin\OidcLogin\Callback', 'callback');
        Helper::addRoute('oidc_login', __TYPECHO_ADMIN_DIR__.'OidcLogin/login', 'TypechoPlugin\OidcLogin\Callback', 'login');
    }

    public static function deactivate(){}

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

        // account association
        $identity_method = new Radio('id_method', [_t('电子邮件'), _t('用户名')], 1, _t('账户关联方法'), _('根据邮件或用户名决定关联到哪个已有的账户'));
        $form->addInput($identity_method);

        // user creation
        $create_user = new Radio('create_user', [_t('不创建'), _t('创建')], 0, _t('自动创建用户'), _('当用户不存在时是否要自动创建用户'));
        $form->addInput($create_user);

        $btn_name = new Text('btn_name', null, 'OIDC 登录', _t('按钮文本'));
        $form->addInput($btn_name);
    }

    public static function personalConfig(Form $form){}

    public static function render() {
        if (!Widget::widget('Widget_User')->hasLogin()) {
            echo '<script>
                function add_oidc_login() {
                    const forms = document.getElementsByName("login");
                    if (forms.length < 0) return;

                    const link = document.createElement("button");
                    link.innerText = "'.Helper::options()->plugin('OidcLogin')->btn_name.'";
                    link.classList.add("btn", "primary");
                    link.onclick = oidc_login;

                    forms[0].after(link);
                }

                function oidc_login() {
                    document.location = "'.Helper::options()->adminUrl.'OidcLogin/login";
                }

                add_oidc_login();
            </script>';
        }
    }
}
