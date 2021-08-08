<?php
namespace app\weixin\controller;

use think\admin\Controller;
use think\admin\service\AdminService;
use think\admin\service\MenuService;
use think\exception\HttpResponseException;
use think\admin\extend\HttpExtend;

class Common extends Controller
{
    public function getToken(){
        $token = cache('component_access_token');
        $ticket = cache('component_verify_ticket');
        if(empty($ticket)){
            return false;
        }
        if(!isset($token['token']) || $token['t'] < time()-7000){ //重新获取TOKEN
            $api = 'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
            $datas = [
                'component_appid'         =>  config("weixin.appid"),
                'component_appsecret'     =>  config("weixin.appsecret"),
                'component_verify_ticket' =>  $ticket,
            ];
            $result = HttpExtend::post($api, json_encode($datas));
            $result = json_decode($result,true);
            if(!empty($result['component_access_token'])){
                $datas = [
                    't' => time(),
                    'token' => $result['component_access_token']
                ];
                cache('component_access_token', $datas);
                return  $datas['token'];
            }else{
                return false;
            }
        }else{
            return $token['token'];
        }
        
    }
}
