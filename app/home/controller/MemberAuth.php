<?php
namespace app\home\controller;
use think\facade\View;
use think\facade\Lang;
/**
 * ============================================================================
 * DSO2O多用户商城
 * ============================================================================
 * 版权所有 2014-2028 长沙德尚网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.csdeshang.com
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * 控制器
 */
class MemberAuth extends BaseMember
{
    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        Lang::load(base_path() . 'home/lang/'.config('lang.default_lang').'/member_auth.lang.php');
    }

    /**
     * 会员升级
     *
     * @param
     * @return
     */
    public function index()
    {
        $member_model = model('member');

        if (request()->isPost()) {
            $member_array = array();
            $member_array['member_auth_state'] = 1;
            $member_array['member_idcard'] = input('post.member_idcard');
            $member_array['member_truename'] = input('post.member_truename');
            $member_validate = ds_validate('member');
                if (!$member_validate->scene('auth')->check($member_array)) {
                    ds_json_encode(10001,$member_validate->getError());
                }
            if(!$this->member_info['member_idcard_image1']){
              ds_json_encode(10001,lang('member_idcard_image1_require'));
            }    
            if(!$this->member_info['member_idcard_image2']){
              ds_json_encode(10001,lang('member_idcard_image2_require'));
            }  
            if(!$this->member_info['member_idcard_image3']){
              ds_json_encode(10001,lang('member_idcard_image3_require'));
            }  
            $update = $member_model->editMember(array(array('member_id' ,'=', $this->member_info['member_id']),array('member_auth_state','in',array(0,2))), $member_array,$this->member_info['member_id']);

            $message = $update ? lang('ds_common_save_succ') : lang('ds_common_save_fail');
            
            if($update){
                ds_json_encode(10000,$message);
            }else{
                ds_json_encode(10001,$message);
            }
        }

        View::assign('member_info', $this->member_info);
        /* 设置买家当前菜单 */
        $this->setMemberCurMenu('member_auth');
        /* 设置买家当前栏目 */
        $this->setMemberCurItem('index');
        return View::fetch($this->template_dir.'index');
    }
    
    public function image_upload() {
        $file_name = input('param.id');
            if (!empty($_FILES[$file_name]['name'])) {

                $res=ds_upload_pic(ATTACH_IDCARD_IMAGE,$file_name);
                if(!$res['code']){
                    ds_json_encode(10001,$res['msg']);
                }
                if(!in_array(substr($file_name,0,20),array('member_idcard_image1','member_idcard_image2','member_idcard_image3'))){
                    ds_json_encode(10001,lang('param_error'));
                }
                $member_array=array();
                $member_array[substr($file_name,0,20)] = $res['data']['file_name'];
                $member_model = model('member');
                if(!$member_model->editMember(array(array('member_id' ,'=', $this->member_info['member_id']),array('member_auth_state','in',array(0,2))), $member_array,$this->member_info['member_id'])){
                    ds_json_encode(10001,lang('ds_common_save_fail'));
                }
                ds_json_encode(10000,'',array('file_name'=>$res['data']['file_name'],'file_path'=>get_member_idcard_image($res['data']['file_name'])));
            }
            ds_json_encode(10001,lang('param_error'));
    }
    public function image_drop(){
        $file_name=input('param.file_name');
        if(!in_array($file_name,array('member_idcard_image1','member_idcard_image2','member_idcard_image3'))){
            ds_json_encode(10001,lang('param_error'));
        }
        @unlink(BASE_UPLOAD_PATH . DIRECTORY_SEPARATOR . ATTACH_IDCARD_IMAGE . DIRECTORY_SEPARATOR . $this->member_info[$file_name]);
        $member_array=array();
        $member_array[$file_name] = '';
        $member_model = model('member');
        if(!$member_model->editMember(array(array('member_id' ,'=', $this->member_info['member_id']),array('member_auth_state','in',array(0,2))), $member_array,$this->member_info['member_id'])){
                    ds_json_encode(10001,lang('ds_common_save_fail'));
                }
        ds_json_encode(10000);        
    }

    /**
     * 用户中心右边，小导航
     *
     * @param string $menu_type 导航类型
     * @param string $menu_key 当前导航的menu_key
     * @return
     */
    public function getMemberItemList()
    {
                $menu_array = array(
                     array(
                        'name' => 'index',
                        'text' => lang('member_auth'),
                        'url' => url('MemberAuth/index')
                    ),
                );

        return $menu_array;
    }
}