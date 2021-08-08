<?php

/**
 * 微信配置
 */

namespace app\admin\controller;
use think\facade\View;
use think\facade\Lang;
use think\Validate;
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
class  O2oComplaint extends AdminControl {

    public function initialize() {
        parent::initialize(); // TODO: Change the autogenerated stub
        Lang::load(base_path() . 'admin/lang/' . config('lang.default_lang') . '/o2o_complaint.lang.php');
    }

    public function index() {
        $o2o_complaint_model = model('o2o_complaint');
        $condition = array();
        $condition[]=array('store_id','=',0);
        $select_complain_state=input('param.select_complain_state');
        if($select_complain_state!==null && $select_complain_state!==''){
            $condition[]=array('o2o_complaint_state','=',intval($select_complain_state));
        }

        $complain_list = $o2o_complaint_model->getO2oComplaintList($condition,'*',10);
        View::assign('complain_list', $complain_list);
        View::assign('show_page', $o2o_complaint_model->page_info);

        return View::fetch();
    }
    

    public function edit() {
        $o2o_complaint_model=model('o2o_complaint');
        $order_id = intval(input('o2o_complaint_id'));
        if ($order_id < 1) {//参数验证
            $this->error(lang('param_error'));
        }

        //检查是不是正在进行投诉
        if (!$o2o_complaint_model->getO2oComplaintInfo(array('o2o_complaint_id'=>$order_id,'store_id'=>0,'o2o_complaint_state'=>0))) {
            $this->error(lang('seller_o2o_complaint_not_exists')); //'您已经投诉了该订单请等待处理'
        }

        if(request()->isPost()){
            $content=input('post.content');
            if(!$content){
                $this->error(lang('o2o_complaint_content_required'));
            }
            $o2o_complaint_model->editO2oComplaint(array(
                'o2o_complaint_reply'=>$content,
                'o2o_complaint_state'=>1,
            ),array('o2o_complaint_id'=>$order_id,'store_id'=>0,'o2o_complaint_state'=>0));
            dsLayerOpenSuccess(lang('ds_common_op_succ'));
        }else{
            return View::fetch('form');
        }
    }



}
