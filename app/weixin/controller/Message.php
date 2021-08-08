<?php

namespace app\weixin\controller;

use app\common\library\weixin\WXBizMsgCrypt;
use think\Log;

class Message extends Common {

    public function index() {
        $xml =  file_get_contents("php://input");
        $timeStamp = empty($_GET ['timestamp']) ? '' : trim($_GET ['timestamp']);
        $nonce = empty($_GET ['nonce']) ? '' : trim($_GET ['nonce']);
        $msg_sign = empty($_GET ['msg_signature']) ? "" : trim($_GET ['msg_signature']);
        $encrypt_type = empty($_GET ['encrypt_type']) ? "" : trim($_GET ['encrypt_type']);
        
        if(empty($xml)){
            echo 'null';
            die;
        }
        //file_put_contents('b'.rand(0,100).'.txt', $xml ."\n".  var_export($_GET,true));
        // die;
 
        //GET的参数暂时忽略了 因为我暂时只做全网发布的功能
        $WXBizMsgCrypt = new WXBizMsgCrypt(config('weixin.token'), config('weixin.encodingaeskey'), config('weixin.appid'));
        $msg = '';
        $errcode = $WXBizMsgCrypt->decryptMsg($msg_sign,$timeStamp, $nonce, $xml, $msg);
        if ($errcode == 0) {
            $res = $this->xmlToArr($msg);
            
            //file_put_contents('a'.rand(0,100).'.txt', var_export($res,true));
            if (empty($res['ToUserName']))
                die;
            if ( ($res['ToUserName'] == 'gh_3c884a361561') || ($res['ToUserName'] == 'gh_b818bd6dc834') ) { //全网发布逻辑 小程序和微信公众账号的设置
                if ($res['MsgType'] == 'event') {
                    $event = $res['Event'] . 'from_callback';
                    $replyMsg = $this->getMessage($res['FromUserName'], $res['ToUserName'], time(), 'text', $event);
                    $encryptMsg = '';
                    $WXBizMsgCrypt->encryptMsg($replyMsg, $timeStamp, $nonce, $encryptMsg);
                    echo  $encryptMsg;
                } else {
                    if($res['Content'] == 'TESTCOMPONENT_MSG_TYPE_TEXT'){
                        $content =  'TESTCOMPONENT_MSG_TYPE_TEXT_callback';
                        $replyMsg = $this->getMessage($res['FromUserName'], $res['ToUserName'], time(), 'text', $content);
                        $encryptMsg = '';
                        $WXBizMsgCrypt->encryptMsg($replyMsg, $timeStamp, $nonce, $encryptMsg);
                        echo  $encryptMsg;
                    }else if( strpos($res['Content'], "QUERY_AUTH_CODE") > -1){ //API
                        $token = $this->getToken();
                        $url = "https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token=".$token;
                       //  Log::write($url);
                       // echo $url;
                        $curlPost=[];
                        $authorization_code = trim(str_replace("QUERY_AUTH_CODE:", "", $res['Content']));
                        $curlPost['component_appid'] = config('weixin.appid');
                        $curlPost['authorization_code'] = $authorization_code;
                        $curlPost = json_encode($curlPost);
                        $result = json_decode(http_request($url, 'POST', $curlPost), true);
                        // Log::write($result);
                       // var_dump($result);
                        $authorizer_access_token = $result['authorization_info']['authorizer_access_token'];
                        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$authorizer_access_token;
                        $curlPost = [];
                        $curlPost['touser'] = $res['FromUserName'];
                        $curlPost['msgtype'] = "text";

                        $curlPost['text']['content'] = $ticket."_from_api";
                        $curlPost = json_encode($curlPost);
                        $result = json_decode(http_request( $url, 'POST', $curlPost), true);
                      //  Log::write('___________________________________________');
                        //Log::write($result);
                        echo 'wxb';
                    }
                    
                }
            }else{ //非全网发布的功能
                if ($res['MsgType'] == 'event') {
                    if($res['Event'] == 'weapp_audit_success'){ //小程序审核通过
                        $this->app->db->name('weixin_publish')->save([
                            'status'=>3
                        ],['app_id'=>$res['ToUserName']]);
                    }
                    if($res['Event'] == 'weapp_audit_fail'){ //小程序审核失败
                        $this->app->db->name('weixin_publish')->save([
                            'status'=>4,
                            'error' => $res['Reason'],
                        ],['app_id'=>$res['ToUserName']]);
                    }
                }
            }
        }
        //提取密文
    }

    private function getMessage($fromUsername, $toUsername, $sendtime, $sendMsgType, $sendContentStr) {
        $sendtextTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";

        $sendResultStr = sprintf($sendtextTpl, $fromUsername, $toUsername, $sendtime, $sendMsgType, $sendContentStr);
        return $sendResultStr;
    }

    //腾讯服务器会检查3个步骤
    public function test() {
        $WXBizMsgCrypt = new WXBizMsgCrypt(config('weixin.token'), config('weixin.encodingaeskey'), config('weixin.appid'));
        

        $msg = '';
        $errcode = $WXBizMsgCrypt->decryptMsg($_GET['msg_signature'], $_GET['timestamp'], $_GET['nonce'], $str1, $msg);
        echo $errcode;exit;
        if ($errcode == 0) {
            $res = $this->xmlToArr($msg);
            print_r($res);
        }
        $_GET = array(
            'signature' => 'b2b8b05dc3e26dbfc23f6432b923773bbdc2e6e7',
            'timestamp' => '1501813615',
            'nonce' => '1744479432',
            'openid' => 'ozy4qt5QUADNXORxCVipKMV9dss0',
            'encrypt_type' => 'aes',
            'msg_signature' => '1c0f0331905c96f6f4fc44fdf8758246e30f4eb7',
        );
        $str1 = '<xml>
            <ToUserName><![CDATA[gh_b818bd6dc834]]></ToUserName>
            <Encrypt><![CDATA[AUPzrK0T22KNgD4iacMaXqSwN/6mQXCDfeVAtnqQDkbCWFFWrAswtLwJCRZXpVajDI1r9x6utCRCgMefJVAvo7UZYW+fA8Jr/pgSgBcKC64IBh2zZB5hDQauJqAsT1i3bWXzihyDr/UpRy46p0ZcAxc03avGjC0+p8l42PQXCZzvIvhJ7NAQOsOvJmTWqwwry5BXFwLewX5wBJ/CsHPVyvl8r+hLT+/Rra1VmWYONKs2k//z0rqOn+Zvcu/PrY+mHotH+NaIVkvnihiBph8J1k8EpXJTSc2egLTTPqH+L5ftyDY6YZgTUfZWbxbPvC75QUJVv4KiSEMPWqMDoHc6BIx/BdCJMmJezFkg1Kx/tZANDy7TTnOIXZkKkYuUD9/4NvU/ffWWu2q+UOiD6wyJ7p0gcmR+KG+6frBX1XD6sdBX+RrZmdIjuIC4uQD4siYd94aG+F6/Ipsl/Amg0mWKs07N2ZIGD1YC+6+t4W7NEvwVdlDxexJ/1ogwx9CdxiJp/NcUyjtwESTMGJ0hy7kaWWt7NAP/UcnlHnN0UYXgSBz4qM+AbP+obGXiylJ0bTYuRz0xcOq4FfjbGD4w5ZuANQ==]]></Encrypt>
        </xml>
        ';

        $msg = '';
        $errcode = $WXBizMsgCrypt->decryptMsg($_GET['msg_signature'], $_GET['timestamp'], $_GET['nonce'], $str1, $msg);
        if ($errcode == 0) {
            $res = $this->xmlToArr($msg);
            print_r($res);
        }
        $_GET = array(
            'signature' => '49a1f9c8255d7de6320fafd1c2a9a529e078d71c',
            'timestamp' => '1501813625',
            'nonce' => '717702015',
            'openid' => 'ozy4qt5QUADNXORxCVipKMV9dss0',
            'encrypt_type' => 'aes',
            'msg_signature' => '59ee14dc08404a83075a358edf199af044dc9123',
        );
        $str1 = '<xml>
            <ToUserName><![CDATA[gh_b818bd6dc834]]></ToUserName>
            <Encrypt><![CDATA[EW5/mS0e6E67cIr+Dm3r9Y/oBeNB/M7DefWUTymCqhVmFK1t+Jrf5FrP09KiffCtqEAtYYP5OHX3mpP8k78bhW/AjGuEDr56sim8y3NV0C2e46ScXAk+3Jf+AL7KPTzKQ0aJsmBpe+IFprTCkFJFrtPJxJH0hl6uNkthQ9lOd13DjCEtuV0z0KyPz8WrhSBMGq//KKxsOLaORSdTUkkOf3M8zXxc0svgD8ZO9pQO6Vpnocex9lskwpkrWrk8c2yuXtxCSFAYTG1iuaIxNDORj+hNmTn/rwQnf6yUmhIDPP9bVkZ77tNeSrXeItj8DxMeuipnzKSDEGVLmesIaMBLLb5NzZ/N7DH08HysZ+S47uIAszkpjCC0NKkEQLAL9AIZQonBn5FVmoAQDEpZ7C/QkClX/cjUUenKa2+moY6AaeCEaNbwX5anMg7dckcIIs7b/rqjdChGyupiF8uugRl1kg==]]></Encrypt>
        </xml>
        ';

        $msg = '';
        $errcode = $WXBizMsgCrypt->decryptMsg($_GET['msg_signature'], $_GET['timestamp'], $_GET['nonce'], $str1, $msg);
        if ($errcode == 0) {
            $res = $this->xmlToArr($msg);
            print_r($res);
        }
        Log::write($str1);
    }

    private function xmlToArr($xml) {
        $res = @simplexml_load_string($xml, NULL, LIBXML_NOCDATA);
        $res = json_decode(json_encode($res), true);
        return $res;
    }

}
