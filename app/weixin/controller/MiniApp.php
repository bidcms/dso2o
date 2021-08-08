<?php

namespace app\weixin\controller;

use think\admin\extend\HttpExtend;
use app\common\library\weixin\WxOpen;
use think\Log;

class MiniApp extends Common
{
    
    function index(){
        $id = 1;
        $info = $this->app->db->name('weixin_account')->where('id', $id)->find();
        $wxapp = new WxOpen($info, $this->getToken());
        print_r($wxapp->getPage());
    }
	function minqrcode_action(){
		global $input;
		header("content-type:image/jpeg");
		$shopid=intval($input['pid']);
		$logo=urldecode($input['logo']);
		if($shopid>0){
			$file=ROOT_PATH.'data/upload/qrcode/min_'.$shopid.'.jpg';
			if(!file_exists($file)){
				include ROOT_PATH.'inc/classes/wxapp.class.php';
				$info=$this->app->db->name('weixin_account')->get_one(array('and shop_id=:sid and service_type_info=0',array(':sid'=>$shopid)));
				if(empty($info)){
					$info=$this->app->db->name('weixin_account')->get_one(array('and authorizer_appid=:appid',array(':appid'=>$GLOBALS['min_appid'])));
				}
				$wxapp=new WxOpen($info);
				$c=$wxapp->getQr('pages/index/index',array('scene'=>$shopid),'B');
				if(!empty($c)){
					file_put_contents($file,$c);
					if(!empty($logo)){
						include ROOT_PATH.'inc/classes/image.class.php';
						$image=new Image();
						$image->filename=$file;
						$image->merge(array('path'=>$file),array(array('content'=>$logo,'type'=>'image','w'=>115,'h'=>115)));
					}
				}
			}
			echo file_get_contents($file);
		}
	}
	//提交小程序
	public function release_action(){
		global $input;
		$store_id=$this->request->param('pid');
	    include ROOT_PATH.'inc/classes/wxapp.class.php';
		$id=$input['id'];
		$res=null;
		if(!empty($id)){
			$publish_info=$this->app->db->name('weixin_publish')->get_one(array('and id=:id',array(':id'=>$id)));
			if($publish_info && $publish_info['status']==3 && $publish_info['shop_id'] == $store_id){
				$info=$this->app->db->name('weixin_account')->get_one(array('and shop_id=:sid and authorizer_appid=:appid',array(':sid'=>$publish_info['shop_id'],':appid'=>$publish_info['app_id'])));
				if(!empty($info) && $info['id']>0){
					$wxapp=new WxOpen($info);
					$result=$wxapp->release(); //提交代码
					if($result['errcode']=='0'){
						$this->app->db->name('weixin_publish')->update_data(array('and id=:id',array(':id'=>$publish_info['id'])),array('status'=>4));
					}
					$this->bidcms_json($result);
				}
			}
		}
		echo '{"errcode":-1,"errmsg":"发布失败"}';
	}
	//取消发布
	public function unpublish_action(){
		global $input;
	    $store_id=$input['pid'];
		$id=$input['id'];
		$res=null;
		if(!empty($id)){
			$publish_info=$this->app->db->name('weixin_publish')->get_one(array('and id=:id',array(':id'=>$id)));
			if($publish_info && $publish_info['shop_id'] == $store_id){
				if($input['unaudit'] == 1) {
	    			include ROOT_PATH.'inc/classes/wxapp.class.php';
					$info=$this->app->db->name('weixin_account')->get_one(array('and shop_id=:sid and authorizer_appid=:appid',array(':sid'=>$publish_info['shop_id'],':appid'=>$publish_info['author_appid'])));
					$wxapp=new WxOpen($info);
					//取消上版本审核 
			        $result = $wxapp->undocodeAudit();
			        if($result['errcode']=='0'){
						$this->app->db->name('weixin_publish')->update_data(array('and id=:id',array(':id'=>$publish_info['id'])),array('status'=>-2));
					}
					echo $this->bidcms_json($result);
					exit;
			    } else {
			    	if($publish_info['status']==2) {
			    		$this->app->db->name('weixin_publish')->delete_data(array('and id=:id',array(':id'=>$publish_info['id'])));
			    	} else {
			    		echo '{"errcode":-1,"errmsg":"非审核失败状态不可删除"}';
			    		exit;
			    	}
				}
			}
		}
		echo '{"errcode":0,"errmsg":"删除成功"}';
	}
	//发布小程序
	public function publish(){
		$store_id=$this->request->param('pid'); //商城id
		$desc = $this->request->param('desc'); //发布备注
		$app_id= $this->request->param('appid'); //绑定小程序appid
		$res=null;
		if($store_id > 0 && $app_id > 0){
			//获取小程序帐号
			$info = $this->app->db->name('weixin_account')->where(array('id'=>$app_id))->find();
			if(!empty($info) && $info['id']>0){
			    $shop_info = $this->app->db->name('system_shops')->where('id', $store_id)->find();
		        $version_info=$this->app->db->name('system_apps')->where('id', $shop_info['packId'])->find();
				$publish_info=$this->app->db->name('weixin_publish')->where(array('shop_id'=>$store_id,'template_id'=>$version_info['id'],'app_id'=>$info['id']))->find();
				if(empty($publish_info) || in_array($publish_info['status'],array(2,-2))){
					//添加发布记录
					$publish_data = array(
						'shop_id' => $store_id,
						'app_id' => $info['id'],
						'template_id' => $version_info['id'],
						'user_version' => $version_info['version'],
						'user_desc' => $desc,
						'updatetime' => time()
					);
					$publish_info = $publish_data;
					$publish_info['id'] = $this->app->db->name('weixin_publish')->insert($publish_data);
				}
				if(!isset($publish_info['id']) || $publish_info['id']<1) {
					$this->bidcms_error('发布小程序失败');
				}
		        $wxapp = new WxOpen($info, $this->getToken());
				$data['tpl_id'] = $version_info['template_id'];
				$data['version'] = $version_info['version'];
				$data['pid'] = $store_id;
				$data['desc'] = $desc;
				$result = $wxapp->publishApp($data); //提交代码
				print_r($result);
				/*if(isset($result['errcode']) && $result['errcode'] == '0'){ //提交审核
					$category=$wxapp->getCategory();
					$page=$wxapp->getPage();
					$params=array(
						array(
						'address'=>$page['page_list'][0],
						'tag'=>'房产 汽车 婚礼',
						'first_class'=>$category['category_list'][0]['first_class'],
						'second_class'=>$category['category_list'][0]['second_class'],
						'first_id'=>$category['category_list'][0]['first_id'],
						'second_id'=>$category['category_list'][0]['second_id'],
						'title'=>$info['nick_name']
						)
					);
					$result=$wxapp->publishAudit($params); //提交审核 
					if($result['errcode']=='0'){
						$this->app->db->name('weixin_publish')->update_data(array('and id=:id',array(':id'=>$publish_info['id'])),array('audit_id'=>$result['auditid'],'status'=>1,'updatetime'=>time(),'user_desc'=>$desc));
						echo '{"errcode":0,"errmsg":"已成功提交到微信审核，请等待微信通知","data":{"qrcode":"'.$info['qrcode_url'].'","wantAudit":false,"addTask":true,"message":"提交成功","h5Url":"'.SITE_URL.'site/index.php?id='.$store_id.'","publishId":"'.$result['auditid'].'","status":1}}';
						exit;
					} else {
						$this->app->db->name('weixin_publish')->update_data(array('and id=:id',array(':id'=>$publish_info['id'])),array('reason'=>$result['errmsg'],'status'=>2,'updatetime'=>time()));
					}
				} else {
				   $this->app->db->name('weixin_publish')->update_data(array('and id=:id',array(':id'=>$publish_info['id'])),array('reason'=>$result['errmsg'],'status'=>2,'updatetime'=>time()));
				}
				$this->bidcms_json($result);*/
			} else {
				$this->bidcms_error('请绑定小程序');
			}
		}
	}
	function bindMiniprogTester_action(){
		global $input;
		$pid=$input['pid'];
		$appid=$input['appid'];
		$wechatid=$input['wechatid'];
		$res=null;
		if(!empty($pid) && !empty($appid)){
			$info=$this->app->db->name('weixin_account')->get_one(array('and shop_id=:sid and authorizer_appid=:appid',array(':sid'=>$pid,':appid'=>$appid)));
			if(!empty($info) && $info['id']>0){
				$tester = '';
				if(!empty($info['wechatid'])){
					$str=strpos('a,'.$info['wechatid'].',b',','.$wechatid.',');
					if($str == 0){
						$tester=$info['wechatid'].','.$wechatid;
					}
				} else {
					$tester=$wechatid;
				}
				$res = array('errcode' => -1, 'errmsg' => '体验号已经存在');
				if(!empty($tester)){
					include ROOT_PATH.'inc/classes/wxapp.class.php';
					$wxapp=new WxOpen($info);
					$res=$wxapp->bindMiniprogTester($wechatid);
					if($res['errcode']=='0') {
					$this->app->db->name('weixin_account')->update_data(array('and id=:id',array(':id'=>$info['id'])),array('wechatid'=>$tester));	
					}
				}
			}
		}
		$this->bidcms_json($res);
	}
	public function unbindMiniprogTester_action(){
		global $input;
		$pid=$input['pid'];
		$appid=$input['appid'];
		$wechatid=$input['wechatid'];
		$res=null;
		if(!empty($pid) && !empty($appid)){
			$info=$this->app->db->name('weixin_account')->get_one(array('and shop_id=:sid and authorizer_appid=:appid',array(':sid'=>$pid,':appid'=>$appid)));
			if(!empty($info) && $info['id']>0){
		        include ROOT_PATH.'inc/classes/wxapp.class.php';
				$wxapp=new WxOpen($info);
				$res=$wxapp->unbindMiniprogTester($wechatid);
				if($res['errcode']=='0'){
					$tester=str_replace(','.$wechatid.',',',',','.$info['wechatid'].',');
					$tester =substr($tester,1,strlen($tester)-2);
					$this->app->db->name('weixin_account')->update_data(array('and id=:id',array(':id'=>$info['id'])),array('wechatid'=>$tester));
				}
			}
		}
		$this->bidcms_json($res);
	}
	public function getChannelForUnbind_action(){
		global $input;
		
		$pid=$input['pid'];
		$res=array('errorcode'=>0,'errormsg'=>'成功');
		if($pid>0){
			$list=$this->app->db->name('weixin_account')->get_page(array('and shop_id=:sid',array(':sid'=>$pid)));
			$l=array();
			foreach($list as $k=>$v){
				$l[]=array('appid'=>$v['authorizer_appid'],'channelType'=>($v['service_type_info']==0?1:0),'nickName'=>$v['nick_name']);
			}
			$res['data']['unbindInfos']=$l;
		}
		$this->bidcms_json($res);
	}
	public function unBindPush_action(){
		global $input;
		$appid = $input['appid'];
		$pid=$input['pid'];
		if(!empty($appid) && $pid>0){
			$info=$this->app->db->name('weixin_account')->get_one(array('and shop_id=:shop_id and authorizer_appid=:appid',array(':shop_id'=>$pid,':appid'=>$appid)));
			if(!empty($info)) {
				include ROOT_PATH.'inc/classes/wxapp.class.php';
				$wxapp=new WxOpen($info);
				$wxapp->unbind();
				$this->app->db->name('weixin_account')->delete_data(array('and authorizer_appid=:appid',array(':appid'=>$appid)));
			}
			die('{"errcode":"0","errmsg":"取消成功"}');
		}
	}
}
