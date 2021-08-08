<?php

namespace app\weixin\controller;

use think\facade\Cache;
use think\facade\Session;
use app\common\library\weixin\WXBizMsgCrypt;

class Auth extends Common {
    public function index(){
        $postData = file_get_contents("php://input");
        if(empty($postData)){
        	echo 'null';
        	exit;
        }
        $timeStamp = empty($_GET ['timestamp']) ? '' : trim($_GET ['timestamp']);
        $nonce = empty($_GET ['nonce']) ? '' : trim($_GET ['nonce']);
        $msg_sign = empty($_GET ['msg_signature']) ? "" : trim($_GET ['msg_signature']);
        $pc = new wxBizMsgCrypt(config('weixin.token'), config('weixin.encodingaeskey'), config('weixin.appid'));
        $msg = '';
        $postData = str_replace('AppId>', 'ToUserName>', $postData);
        $errCode = $pc->decryptMsg($msg_sign, $timeStamp, $nonce, $postData, $msg);
        if ($errCode  ==  0) {
        	$xml = new \DOMDocument();
			$xml->loadXML($msg);
        	$infoType     =    $xml->getElementsByTagName('InfoType')->item(0)->nodeValue;
        	if($infoType == 'component_verify_ticket'){
        	    $componentVerifyTicket = $xml->getElementsByTagName('ComponentVerifyTicket')->item(0)->nodeValue;
        		cache('component_verify_ticket', $componentVerifyTicket);
        		echo 'success';
        	} elseif($infoType == 'authorized'){ 
        		$appid = $msg->AuthorizerAppid;
        		echo 'success';
        	} elseif($infoType == 'unauthorized'){
        		$appid = $msg->AuthorizerAppid;
        		echo 'success';
        	} elseif($infoType == 'updateauthorized'){
        		$appid = $msg->AuthorizerAppid;
        		echo 'success';
        	}
        } else {
            echo $errCode;
        }
      	
    }
    
    public function create()
    {   
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';  

        $token = $this->getToken();
        if ($token == false) {
            $this->error('第三方授权失败！');
        }
        $api = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token=' . $token;
        $data = [
            'component_appid' => config('weixin.appid')
        ];
        $shop_id = Session::get('shop_id');
        $result = json_decode(http_request($api, 'POST', json_encode($data)));
        $redirect_url = urlencode($http_type.$_SERVER['HTTP_HOST'].'/weixin/auth/first?shop_id=' . $shop_id);
        if (!empty($result->pre_auth_code)) {
            $url = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid=' . config('weixin.appid') . '&pre_auth_code=' . $result->pre_auth_code . '&redirect_uri=' . $redirect_url;
            header("Location:" . $url);
            die;
        } else {
            $this->error('第三方授权失败#1');
        }

    }
    public function first()
    {
        $auth_code = $this->request->get('auth_code');
        if (empty($auth_code)) {
            $this->error('授权失败c1！');
        }
        $shop_id = $this->request->get('shop_id');
        if (empty($shop_id)) {
            $this->error('授权失败c2！');
        }
        $token = $this->getToken();
        if ($token == false) {
            $this->error('第三方授权失败#002！');
        }
        $api = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token=' . $token;
        $data = [
            'component_appid' => config('weixin.appid'),
            'authorization_code' => $auth_code,
        ];
        $result = json_decode(http_request($api, 'POST', json_encode($data)), true);
        $datas = [];
        // var_dump($result);die;
        if (empty($result['authorization_info']['authorizer_appid'])) {
            $this->error('第三方授权失败#003！');
        }
        $datas['authorizer_appid'] = $result['authorization_info']['authorizer_appid'];

        $auth = $this->app->db->name('weixin_account')->where(['authorizer_appid' => $datas['authorizer_appid'], 'shop_id' => $shop_id])->find();
        if (!empty($auth)) {
            $this->error('已经授权过了！');
        }

        $datas['authorizer_access_token'] = $result['authorization_info']['authorizer_access_token'];
        $datas['authorizer_refresh_token'] = $result['authorization_info']['authorizer_refresh_token'];
        $datas['authorizer_refresh_token_expire_time'] = time() + 7000;

        $api = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token=' . $token;
        $data = [
            'component_appid' => config('weixin.appid'),
            'authorizer_appid' => $datas['authorizer_appid'],
        ];
        $result = json_decode(http_request($api, 'POST', json_encode($data)), true);
        if (!empty($result['authorizer_info'])) {
            $datas['nick_name'] = $result['authorizer_info']['nick_name'];
			//$datas['nick_dllogo'] = $result['authorizer_info']['nick_dllogo'];
			//$datas['nick_name'] = $result['authorizer_info']['nick_name']; 
            $datas['head_img'] = empty($result['authorizer_info']['head_img']) ? '' :$result['authorizer_info']['head_img'];
            $datas['user_name'] = $result['authorizer_info']['user_name'];
            $datas['qrcode_url'] = $result['authorizer_info']['qrcode_url'];
            $datas['principal_name'] = $result['authorizer_info']['principal_name'];
            $datas['signature'] = $result['authorizer_info']['signature'];
            $datas['service_type_info'] = $result['authorizer_info']['service_type_info']['id'];
            $datas['verify_type_info'] = $result['authorizer_info']['verify_type_info']['id'];
            $datas['app_info'] = json_encode($result['authorizer_info']);
            $datas['shop_id'] = $shop_id;
            $datas['app_key'] = md5(microtime());
            $this->app->db->name('weixin_account')->insert($datas);
            $this->success('添加成功', url('/index/index'));
        }
        $this->error('授权失败');
    }
    public function refresh() {
        
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';  

        $token = $this->getToken();
        if ($token == false) {
            $this->error('第三方授权失败！');
        }
        $miniapp_id = (int) $this->request->param('id');
        if(!$miniapp = $this->app->db->name('weixin_account')->find($miniapp_id)){
             $this->error('不存在小程序',null,101);
        }
        $shop_id = Session::get('shop_id');
        if($miniapp['shop_id'] != $shop_id){
            $this->error('不存在小程序',null,101);
        }
        $api = 'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token=' . $token;
        $data = [
            'component_appid' => config('weixin.appid'),

        ];
        $redirect_url = urlencode($http_type.$_SERVER['HTTP_HOST'].'/weixin/auth/refreshcallback?id='.$miniapp_id.'&shop_id='.$shop_id);
        $result = json_decode(http_request($api, 'POST', json_encode($data)));
        if (!empty($result->pre_auth_code)) {
            $url = 'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid=' . config('weixin.appid') . '&pre_auth_code=' . $result->pre_auth_code . '&redirect_uri=' . $redirect_url;
            header("Location:" . $url);
            die;
        } else {
            $this->error('第三方授权失败#1');
        }
    }
    public function refreshcallback()
    {
        $auth_code = $this->request->get('auth_code');
        if (empty($auth_code)) {
            $this->error('授权失败1！');
        }
        $shop_id = $this->request->get('shop_id');
        if (empty($shop_id)) {
            $this->error('授权失败2！');
        }
        $miniapp_id = (int) $this->request->get('id');
        if(!$miniapp = $this->app->db->name('weixin_account')->find($miniapp_id)){
            $this->error('授权失败4',null,101);
        }
        if($miniapp['shop_id'] != $shop_id){
             $this->error('不存在小程序',null,101);
        }
        $token = $this->getToken();
        if ($token == false) {
            $this->error('第三方授权失败！');
        }
        $api = 'https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token=' . $token;
        $data = [
            'component_appid' => config('weixin.appid'),
            'authorization_code' => $auth_code,
        ];
        $result = json_decode(http_request($api, 'POST' json_encode($data)), true);
        $datas = [];
        // var_dump($result);
        if (empty($result['authorization_info']['authorizer_appid'])) {
            $this->error('第三方授权失败！');
        }
        $datas['authorizer_appid'] = $result['authorization_info']['authorizer_appid'];
        $datas['authorizer_access_token'] = $result['authorization_info']['authorizer_access_token'];
        $datas['authorizer_refresh_token'] = $result['authorization_info']['authorizer_refresh_token'];
        $datas['authorizer_refresh_token_expire_time'] = time() + 7000;
        $api = 'https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token=' . $token;
        $data = [
            'component_appid' => config('weixin.appid'),
            'authorizer_appid' => $datas['authorizer_appid'],
        ];
        $result = json_decode(http_request($api, 'POST', json_encode($data)), true);
        if (!empty($result['authorizer_info'])) {
            $datas['nick_name'] = $result['authorizer_info']['nick_name'];
			 //$datas['nick_dllogo'] = $result['authorizer_info']['nick_dllogo'];
            $datas['head_img'] = empty($result['authorizer_info']['head_img']) ? '' : $result['authorizer_info']['head_img'];
            $datas['user_name'] = $result['authorizer_info']['user_name'];
            $datas['qrcode_url'] = $result['authorizer_info']['qrcode_url'];
            $datas['principal_name'] = $result['authorizer_info']['principal_name'];
            $datas['signature'] = $result['authorizer_info']['signature'];
            $datas['service_type_info'] = $result['authorizer_info']['service_type_info']['id'];
            $datas['verify_type_info'] = $result['authorizer_info']['verify_type_info']['id'];
            $datas['app_info'] = json_encode($result['authorizer_info']);
            $datas['shop_id'] = $shop_id;
            $datas['app_key'] = md5(microtime());
            $this->app->db->name('weixin_account')->where(['id'=>$miniapp['id']])->update($datas);
            $this->success('添加成功', url('/manage/miniapp/index',['member_miniapp_id'=>$miniapp_id]));
        }
        $this->error('授权失败');
    }
}
