<?php
/*
	[Bidcms.com!] (C)2009-2011 Bidcms.com.
	This is NOT a freeware, use is subject to license terms
	$author limengqi
	$Id: showcase.class.php 2010-08-24 10:42 $
*/
namespace app\common\library\weixin;
use think\admin\extend\HttpExtend;
use think\facade\Db;

class WxOpen
{
    const WEIXIN_API = 'https://api.weixin.qq.com/cgi-bin/';
	const WEIXIN_OAUTH_API = 'https://api.weixin.qq.com/sns/';
	public $hint=array('42001' => 'access_token过期，请重新申请',
		'85001'=>'微信号不存在或微信号设置为不可搜索','85002'=>'小程序绑定的体验者数量达到上限','85003'=>'微信号绑定的小程序体验者达到上限','85004'=>'微信号已经绑定','85013'=>'无效的自定义配置','85014'=>'无效的模版编号','85017'=>'域名不在业务列表中','85043'=>'模版错误','85044'=>'代码包超过大小限制','85045'=>'ext_json有不存在的路径','85046'=>'tabBar中缺少path','85047'=>'pages字段为空','85048'=>'ext_json解析失败','86000'=>'不是由第三方代小程序进行调用','86001'=>'不存在第三方的已经提交的代码','86000'=>'不是由第三方代小程序进行调用','86001'=>'不存在第三方的已经提交的代码','85006'=>'标签格式错误','85007'=>'页面路径错误','85008'=>'类目填写错误','85009'=>'已经有正在审核的版本','85010'=>'item_list有项目为空','85011'=>'标题填写错误','85023'=>'审核列表填写的项目数不在1-5以内','85077'=>'小程序类目信息失效（类目中含有官方下架的类目，请重新选择类目）','86002'=>'小程序还未设置昵称、头像、简介。请先设置完后再重新提交。','86000'=>'不是由第三方代小程序进行调用','86001'=>'不存在第三方的已经提交的代码','85012'=>'无效的审核id','85019'=>'没有审核版本','85020'=>'审核状态未满足发布','85015'=>'版本输入错误','85066'=>'链接错误','85068'=>'测试链接不是子链接','85069'=>'校验文件失败','85070'=>'链接为黑名单','85071'=>'已添加该链接，请勿重复添加','85072'=>'该链接已被占用','85073'=>'二维码规则已满','85074'=>'小程序未发布, 小程序必须先发布代码才可以发布二维码跳转规则','85075'=>'个人类型小程序无法设置二维码规则','85076'=>'链接没有ICP备案','87011'=>'现网已经在灰度发布，不能进行版本回退','87012'=>'该版本不能回退，可能的原因：1:无上一个线上版用于回退 2:此版本为已回退版本，不能回退 3:此版本为回退功能上线之前的版本，不能回退','87013'=>'撤回次数达到上限（每天一次，每个月10次）','85052'=>'已经发布成功'
	);
	public function __construct($info, $token){
		$this->info = $info;
		$this->component_appid = config('weixin.appid');
		$this->component_token = $token;
	}
	public function refresh_token(){
		if($this->info['updatetime']+7000 < time() && !empty($this->component_token) && !empty($this->info['authorizer_refresh_token'])){
			
			$url='https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$this->component_token;
			$json='{"component_appid":"'.$this->component_appid.'","authorizer_appid":"'.$this->info['authorizer_appid'].'","authorizer_refresh_token":"'.$this->info['authorizer_refresh_token'].'"}';
			$content=HttpExtend::post($url,$json);
			if(!empty($content)){
				$token=json_decode($content,true);
				if(isset($token['authorizer_access_token'])){
				    $data['updatetime'] = time();
					$data['authorizer_access_token']=$token['authorizer_access_token'];
					$data['authorizer_refresh_token']=$token['authorizer_refresh_token'];
					Db::name('weixin_account')->where('id', $this->info['id'])->update($data);
				}
			}
			return $token;
		}
		return false;
	}
	//发送文本信息
	public function messageTextSend($openid,$content){
		$url='https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$this->info['authorizer_access_token'];
		$pushMsg='{"touser":"'.$openid.'","msgtype":"text","text":{"content":"'.$content.'"}}';
		$result=HttpExtend::get($url);
		return $this->hint($result);
	}
	//公众号登录
	public function mplogin($code){
		$token_file=ROOT_PATH.'data/cache/weixin/component_access_token.txt';
		$tokenData=json_decode(file_get_contents($token_file),true);
		if(isset($this->component_token)){
			$url='https://api.weixin.qq.com/sns/oauth2/component/access_token?appid='.$this->info['authorizer_appid'].'&code='.$code.'&grant_type=authorization_code&component_appid='.$this->component_appid.'&component_access_token='.$this->component_token;
			
			$result=HttpExtend::get($url);
			if(!empty($result)){
				$token=json_decode($result,true);
				if(!isset($token['errcode']) && !empty($token['openid'])){
					$url='https://api.weixin.qq.com/sns/userinfo?access_token='.$token['access_token'].'&openid='.$token['openid'].'&lang=zh_CN';
					$result=HttpExtend::get($url);
				}
			}
			return $this->hint($result);
		}
		return false;
	}
	//小程序登录
	public function login($code){
		if(!empty($this->component_token)){
			$url='https://api.weixin.qq.com/sns/component/jscode2session?appid='.$this->info['authorizer_appid'].'&js_code='.$code.'&grant_type=authorization_code&component_appid='.$this->component_appid.'&component_access_token='.$this->component_token;
			$result=HttpExtend::get($url);
			return $this->hint($result);
		}
		return false;
	}
	//修改服务器
	public function modifyDomain($domain,$type='set'){
		$url='https://api.weixin.qq.com/wxa/modify_domain?access_token='.$this->info['authorizer_access_token'];
		$domain=str_replace('#type#',$type,$domain);
		$result=HttpExtend::post($url,$domain);
		return json_decode($result,true);
	}
	//设置业务域名
	public function setWebviewDomain($domain,$type='set'){
		$url='https://api.weixin.qq.com/wxa/setwebviewdomain?access_token='.$this->info['authorizer_access_token'];
		$domain=str_replace('#type#',$type,$domain);
		$result=HttpExtend::post($url,$domain);
		return json_decode($result,true);
	}
	//为授权的小程序帐号上传小程序代码
	public function publishApp($data){
		$extJson = array(
			  'extEnable'=>true,
			  'extAppid' => $this->info['authorizer_appid'],
			  'ext' => array(
				'appid' => $this->info['authorizer_appid'],
				'pid' => $data['pid']
			  ),
			  'extPages' => array(
			  ),
			  'tabBar' => array(
			  ),
			  'networkTimeout' => array(
				  'request'    => 10000,
				  'uploadFile'  => 10000,
				  'downloadFile' => 10000,
				  'connectSocket' => 10000
			  )
		);
		if(!empty($data['properties'])){
			$extJson['windows']=array(
				  'navigationBarTextStyle'  => $data['properties']['navigationBarTextStyle'],
				  "navigationBarTitleText"  => $data['properties']['navigationBarTitleText'],
				  'navigationBarBackgroundColor' => $data['properties']['navigationBarBackgroundColor']
			);
		}
		$params = array(
			'template_id'  => $data['tpl_id'],
			'user_version' => $data['version'],
			'user_desc'   => $data['desc'],
			'ext_json'   => json_encode( $extJson, JSON_UNESCAPED_UNICODE )
		);
		$url='https://api.weixin.qq.com/wxa/commit?access_token='.$this->info['authorizer_access_token'];
		$json=json_encode( $params, JSON_UNESCAPED_UNICODE );
		$result=HttpExtend::post($url,$json);
		return $this->hint($result);

	}
	//获取小程序的第三方提交代码的页面配置（仅供第三方开发者代小程序调用）
	public function getPage(){
		$url='https://api.weixin.qq.com/wxa/get_page?access_token='.$this->info['authorizer_access_token'];
		$result=HttpExtend::get($url);
		return $this->hint($result);
	}
	//将第三方提交的代码包提交审核（仅供第三方开发者代小程序调用）
	public function publishAudit($params){
		$url='https://api.weixin.qq.com/wxa/submit_audit?access_token='.$this->info['authorizer_access_token'];
		if(is_array($params)){
			$data['item_list']=$params;
			$json=json_encode($data, JSON_UNESCAPED_UNICODE );
			$result=HttpExtend::post($url,$json);
			return $this->hint($result);
		}
		return false;
	}
	//撤消审核
	public function undocodeAudit(){
		$url='https://api.weixin.qq.com/wxa/undocodeaudit?access_token='.$this->info['authorizer_access_token'];
		$result=HttpExtend::get($url);
		return $this->hint($result);
	}
	//发布已通过审核的小程序（仅供第三方代小程序调用）
	public function release(){
		$url='https://api.weixin.qq.com/wxa/release?access_token='.$this->info['authorizer_access_token'];
		$result=HttpExtend::post($url,'{}');
		return $this->hint($result);
	}
	
	//创建 开放平台帐号并绑定公众号/小程序
	public function openCreate(){
		$url='https://api.weixin.qq.com/cgi-bin/open/create?access_token='.$this->info['authorizer_access_token'];
		$json='{"appid": "'.$this->info['authorizer_appid'].'"}';
		$res=HttpExtend::post($url,$json);
		return $this->hint($res);
	}
	//将公众号/小程序绑定到开放平台帐号下
	public function bind($open_appid){
		if(!empty($open_appid)){
			$url='https://api.weixin.qq.com/cgi-bin/open/bind?access_token='.$this->info['authorizer_access_token'];
			$json='{"appid": "'.$this->info['authorizer_appid'].'","open_appid": "'.$open_appid.'"}';
			$result=HttpExtend::post($url,$json);
			return $this->hint($result);
		}
		return false;
	}
	//将公众号/小程序从开放平台帐号下解绑
	public function unbind(){
		if(!empty($this->info['open_appid'])){
			$url='https://api.weixin.qq.com/cgi-bin/open/unbind?access_token='.$this->info['authorizer_access_token'];
			$json='{"appid": "'.$this->info['authorizer_appid'].'","open_appid": "'.$this->info['open_appid'].'"}';
			$result=HttpExtend::post($url,$json);
			return $this->hint($result);
		}
		return false;
	}
	//获取授权小程序帐号的可选类目
	public function getCategory(){
		$url='https://api.weixin.qq.com/cgi-bin/wxopen/getallcategories?access_token='.$this->info['authorizer_access_token'];
		$result=HttpExtend::get($url);
		return $this->hint($result);
	}
	
	//获取体验小程序的体验二维码
	public function getQr($path, $ext, $type='A'){
		if('A'==$type){
			$json='{"path":"'.$path.'"}';
			$url='https://api.weixin.qq.com/wxa/getwxacode?access_token='.$this->info['authorizer_access_token'];
		} elseif('B'==$type){
			$json='{"page":"'.$path.'","scene":"'.$ext['scene'].'"}';
			$url='https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.$this->info['authorizer_access_token'];
		} elseif('C'==$type){
			$json='{"path":"'.$path.'"}';
			$url='https://api.weixin.qq.com/cgi-bin/wxaapp/createwxaqrcode?access_token='.$this->info['authorizer_access_token'];
		}
		$content=HttpExtend::post($url,$json);
		return $content;
	}
	//解绑体验者
	public function unbindMiniprogTester($wechatid){
		$url='https://api.weixin.qq.com/wxa/unbind_tester?access_token='.$this->info['authorizer_access_token'];
		$result=HttpExtend::post($url,'{"wechatid":"'.$wechatid.'"}');
		return $this->hint($result);
	}
	//绑定体验者
	public function bindMiniprogTester($wechatid){
		$url='https://api.weixin.qq.com/wxa/bind_tester?access_token='.$this->info['authorizer_access_token'];
		$result=HttpExtend::post($url,'{"wechatid":"'.$wechatid.'"}');
		return $this->hint($result);
	}
	public function queryPaymentChannelList_action(){
		echo '{"errcode":0,"errmsg":""}';
	}
	
	/*
	时间：
	功能：
	作者：
	参数：
	返回值：
	*/
	public function createMenu($json){
		$url='https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->info['authorizer_access_token'];
		$res=HttpExtend::post($url,$json);
		return $this->hint($res);
	}
	private function hint($result){
		if(!empty($result)){
			$res=json_decode($result,true);
			if(isset($res['errcode']) && isset($this->hint[$res['errcode']])){
				$res['errmsg']=$this->hint[$res['errcode']];
			}
			return $res;
		}
		return false;
	}
	
}
