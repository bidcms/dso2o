<?php

namespace app\seller\controller;

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
class Sellerjoinin extends BaseMember {

    private $joinin_detail = NULL;
    
    public function initialize() {

        parent::initialize();
        Lang::load(base_path() . 'home/lang/' . config('lang.default_lang') . '/sellerjoinin.lang.php');

        $this->checkLogin();
        $seller_model = model('seller');
        $seller_info = $seller_model->getSellerInfo(array('member_id' => session('member_id')));
        if (!empty($seller_info)) {
            $this->success(lang('already_sub_account'), (string) url('Sellerlogin/login'));
            exit;
        }

        if (request()->action() != 'check_seller_name_exist' && request()->action() != 'checkname') {
            $this->check_joinin_state();
        }
        
        $store_joinin_open=config('ds_config.store_joinin_open');

        if(!$this->joinin_detail || !$this->joinin_detail['joinin_state']){//已经填写过入驻资料的则不跳转
            if($store_joinin_open==0){
                $this->error(lang('store_joinin_close'));
            }elseif($store_joinin_open==3){
                $this->redirect('Sellerjoininc2c/'.request()->action());
            }
        }
        
        $phone_array = explode(',', config('ds_config.site_phone'));
        View::assign('phone_array', $phone_array);
        $help_model = model('help');
        $condition = array();
        $condition[] = array('helptype_id', '=', '99'); //默认显示入驻流程;
        $help_list = $help_model->getShowStoreHelpList($condition);
        View::assign('help_list', $help_list); //左侧帮助类型及帮助
        View::assign('show_sign', 'joinin');
        View::assign('html_title', config('ds_config.site_name') . ' - ' . lang('tenants'));
        View::assign('article_list', ''); //底部不显示文章分类
    }

    private function check_joinin_state() {
        $storejoinin_model = model('storejoinin');
        $joinin_detail = $storejoinin_model->getOneStorejoinin(array('member_id' => session('member_id')));
        if (!empty($joinin_detail)) {
            $this->joinin_detail = $joinin_detail;
            switch (intval($joinin_detail['joinin_state'])) {
                case STORE_JOIN_STATE_NEW:
                    $this->dostep4();
                    $this->show_join_message(lang('apply_submit_success'), FALSE, '3');
                    break;
                case STORE_JOIN_STATE_PAY:
                    $this->show_join_message(lang('pay_submit_success'), FALSE, '4');
                    break;
                case STORE_JOIN_STATE_VERIFY_SUCCESS:
                    if (!in_array(request()->action(), array('pay', 'pay_save'))) {
                        $this->pay();
                    }
                    break;
                case STORE_JOIN_STATE_VERIFY_FAIL:
                    if (!in_array(request()->action(), array('step1', 'step2', 'step3', 'step4'))) {
                        $this->show_join_message(lang('verify_fail') . ':' . $joinin_detail['joinin_message'], (string) url('Sellerjoinin/step1'));
                    }
                    break;
                case STORE_JOIN_STATE_PAY_FAIL:
                    if (!in_array(request()->action(), array('pay', 'pay_save'))) {
                        $this->show_join_message(lang('pay_verify_fail') . ':' . $joinin_detail['joinin_message'], (string) url('Sellerjoinin/pay'));
                    }
                    break;
                case STORE_JOIN_STATE_FINAL:
                    $this->success(lang('store_already_open'), (string) url('Sellerlogin/login'));
                    break;
            }
        }
    }

    public function index() {
        echo $this->step0();
        exit;
    }

    public function step0() {
        $document_model = model('document');
        $document_info = $document_model->getOneDocumentByCode('open_store');
        View::assign('agreement', htmlspecialchars_decode($document_info['document_content']));
        View::assign('step', '0');
        View::assign('sub_step', 'step0');
        echo View::fetch($this->template_dir . 'step0');
        exit;
    }

    public function step1() {
        View::assign('step', '1');
        View::assign('sub_step', 'step1');
        View::assign('baidu_ak', config('ds_config.baidu_ak'));
        echo View::fetch($this->template_dir . 'step1');
        exit;
    }

    public function step2() {
        if (request()->isPost()) {
            $param = array();
            $param['member_name'] = session('member_name');
            $param['company_name'] = input('post.company_name');
            $param['store_longitude'] = input('post.longitude');
            $param['store_latitude'] = input('post.latitude');
            $param['company_province_id'] = intval(input('post.district_id') ? input('post.district_id') : (input('post.city_id') ? input('post.city_id') : (input('post.province_id') ? input('post.province_id') : 0)));
            $param['company_address'] = input('post.company_address');
            $param['company_address_detail'] = input('post.company_address_detail');
            $param['company_registered_capital'] = intval(input('post.company_registered_capital'));
            $param['contacts_name'] = input('post.contacts_name');
            $param['contacts_phone'] = input('post.contacts_phone');
            $param['contacts_email'] = input('post.contacts_email');
            $param['business_licence_number'] = input('post.business_licence_number');
            $param['business_licence_address'] = input('post.business_licence_address');
            $param['business_licence_start'] = input('post.business_licence_start');
            $param['business_licence_end'] = input('post.business_licence_end');
            $param['business_sphere'] = input('post.business_sphere');
            $param['business_licence_number_electronic'] = $this->upload_image('business_licence_number_electronic');


            $this->step2_save_valid($param);

            $storejoinin_model = model('storejoinin');
            $joinin_info = $storejoinin_model->getOneStorejoinin(array('member_id' => session('member_id')));
            if (empty($joinin_info)) {
                $param['member_id'] = session('member_id');
                $storejoinin_model->addStorejoinin($param);
            } else {
                $storejoinin_model->editStorejoinin($param, array('member_id' => session('member_id')));
            }
        }
        View::assign('step', '2');
        View::assign('sub_step', 'step2');
        echo View::fetch($this->template_dir . 'step2');
        exit;
    }

    private function step2_save_valid($param) {
        $sellerjoinin_validate = ds_validate('sellerjoinin');
        if (!$sellerjoinin_validate->scene('step2_save_valid')->check($param)) {
            $this->error($sellerjoinin_validate->getError());
        }
    }

    public function step3() {
        if (request()->isPost()) {
            $param = array();
            $param['bank_account_name'] = input('post.bank_account_name');
            $param['bank_account_number'] = input('post.bank_account_number');
            $param['bank_name'] = input('post.bank_name');
            $param['bank_address'] = input('post.bank_address');
            $is_settlement_account = input('post.is_settlement_account');
            if (!empty($is_settlement_account)) {
                $param['is_settlement_account'] = 1;
                $param['settlement_bank_account_name'] = input('post.bank_account_name');
                $param['settlement_bank_account_number'] = input('post.bank_account_number');
                $param['settlement_bank_name'] = input('post.bank_name');
                $param['settlement_bank_address'] = input('post.bank_address');
            } else {
                $param['is_settlement_account'] = 2;
                $param['settlement_bank_account_name'] = input('post.settlement_bank_account_name');
                $param['settlement_bank_account_number'] = input('post.settlement_bank_account_number');
                $param['settlement_bank_name'] = input('post.settlement_bank_name');
                $param['settlement_bank_address'] = input('post.settlement_bank_address');
            }

            $this->step3_save_valid($param);

            $storejoinin_model = model('storejoinin');
            $storejoinin_model->editStorejoinin($param, array('member_id' => session('member_id')));
        }

        //商品分类
        $gc = model('goodsclass');
        $gc_list = $gc->getGoodsclassListByParentId(0);
        View::assign('gc_list', $gc_list);

        //店铺等级
        $grade_list = rkcache('storegrade', true);
        View::assign('grade_list', $grade_list);

        //店铺分类

        $storeclass_model = model('storeclass');
        $store_class = $storeclass_model->getStoreclassList(array(), '', false);
        View::assign('store_class', $store_class);

        View::assign('step', '3');
        View::assign('sub_step', 'step3');
        echo View::fetch($this->template_dir . 'step3');
        exit;
    }

    private function step3_save_valid($param) {
        $sellerjoinin_validate = ds_validate('sellerjoinin');
        if (!$sellerjoinin_validate->scene('step3_save_valid')->check($param)) {
            $this->error($sellerjoinin_validate->getError());
        }
    }

    public function check_seller_name_exist() {
        $condition = array();
        $condition[] = array('seller_name', '=', input('get.seller_name'));

        $seller_model = model('seller');
        $result = $seller_model->isSellerExist($condition);

        if ($result) {
            echo 'true';
        } else {
            echo 'false';
        }
    }

    public function step4() {
        $store_class_ids = array();
        $store_class_names = array();
        $store_class_ids_array = input('post.store_class_ids/a'); #获取数组
        if (!empty($store_class_ids_array)) {
            foreach ($store_class_ids_array as $value) {
                $store_class_ids[] = $value;
            }
        }

        $store_class_names_array = input('post.store_class_names/a'); #获取数组
        if (!empty($store_class_names_array)) {
            foreach ($store_class_names_array as $value) {
                $store_class_names[] = $value;
            }
        }
        //取最小级分类最新分佣比例
        $sc_ids = array();
        foreach ($store_class_ids as $v) {
            $v = explode(',', trim($v, ','));
            if (!empty($v) && is_array($v)) {
                $sc_ids[] = end($v);
            }
        }
        $store_class_commis_rates = array();
        if (!empty($sc_ids)) {
            $goods_class_list = model('goodsclass')->getGoodsclassListByIds($sc_ids);
            if (!empty($goods_class_list) && is_array($goods_class_list)) {
                $sc_ids = array();
                foreach ($goods_class_list as $v) {
                    $store_class_commis_rates[] = $v['commis_rate'];
                }
            }
        }
        $param = array();
        $param['seller_name'] = input('post.seller_name');
        $param['store_name'] = input('post.store_name');
        $param['store_type'] = 0;
        $param['store_class_ids'] = serialize($store_class_ids);
        $param['store_class_names'] = serialize($store_class_names);
        $param['joinin_year'] = intval(input('post.joinin_year'));
        $param['joinin_state'] = STORE_JOIN_STATE_NEW;
        $param['store_class_commis_rates'] = implode(',', $store_class_commis_rates);

        //取店铺等级信息
        $grade_list = rkcache('storegrade', true);
        $storegrade_id = intval(input('post.storegrade_id'));
        if ($storegrade_id <= 0) {
            $this->error(lang('param_error'));
        }
        if (!empty($grade_list[$storegrade_id])) {
            $param['storegrade_id'] = $storegrade_id;
            $param['storegrade_name'] = $grade_list[$storegrade_id]['storegrade_name'];
            $param['sg_info'] = serialize(array('storegrade_price' => $grade_list[$storegrade_id]['storegrade_price']));
        }

        //取最新店铺分类信息
        $store_class_info = model('storeclass')->getStoreclassInfo(array('storeclass_id' => intval(input('post.storeclass_id'))));
        if ($store_class_info) {
            $param['storeclass_id'] = $store_class_info['storeclass_id'];
            $param['storeclass_name'] = $store_class_info['storeclass_name'];
            $param['storeclass_bail'] = $store_class_info['storeclass_bail'];
        }

        //店铺应付款
        $param['paying_amount'] = floatval($grade_list[$storegrade_id]['storegrade_price']) * $param['joinin_year'] + floatval($param['storeclass_bail']);
        $this->step4_save_valid($param);

        $storejoinin_model = model('storejoinin');
        $storejoinin_model->editStorejoinin($param, array('member_id' => session('member_id')));

        @header('location: ' . (string) url('Sellerjoinin/index'));
        exit;
    }

    private function step4_save_valid($param) {
        $sellerjoinin_validate = ds_validate('sellerjoinin');
        if (!$sellerjoinin_validate->scene('step4_save_valid')->check($param)) {
            $this->error($sellerjoinin_validate->getError());
        }
    }

    public function pay() {
        if (!empty($this->joinin_detail['sg_info'])) {
            $store_grade_info = model('storegrade')->getOneStoregrade($this->joinin_detail['storegrade_id']);
            $this->joinin_detail['storegrade_price'] = $store_grade_info['storegrade_price'];
        } else {
            $this->joinin_detail['sg_info'] = @unserialize($this->joinin_detail['sg_info']);
            if (is_array($this->joinin_detail['sg_info'])) {
                $this->joinin_detail['storegrade_price'] = $this->joinin_detail['sg_info']['storegrade_price'];
            }
        }
        View::assign('joinin_detail', $this->joinin_detail);
        View::assign('step', '4');
        View::assign('sub_step', 'pay');
        echo View::fetch($this->template_dir . 'pay');
        exit;
    }

    public function pay_save() {
        $param = array();
        $param['paying_money_certificate'] = $this->upload_image('paying_money_certificate');
        $param['paying_money_certificate_explain'] = input('post.paying_money_certificate_explain');
        $param['joinin_state'] = STORE_JOIN_STATE_PAY;
        if (empty($param['paying_money_certificate'])) {
            $this->error(lang('paying_money_certificate_empty'));
        }
        $storejoinin_model = model('storejoinin');
        $storejoinin_model->editStorejoinin($param, array('member_id' => session('member_id')));
        @header('location:' . (string) url('Sellerjoinin/index'));
        exit;
    }

    private function dostep4() {
        if (!empty($this->joinin_detail['sg_info'])) {
            $store_grade_info = model('storegrade')->getOneStoregrade($this->joinin_detail['storegrade_id']);
            $this->joinin_detail['storegrade_price'] = $store_grade_info['storegrade_price'];
        } else {
            $this->joinin_detail['sg_info'] = @unserialize($this->joinin_detail['sg_info']);
            if (is_array($this->joinin_detail['sg_info'])) {
                $this->joinin_detail['storegrade_price'] = $this->joinin_detail['sg_info']['storegrade_price'];
            }
        }
        View::assign('joinin_detail', $this->joinin_detail);
    }

    private function show_join_message($message, $btn_next = FALSE, $step = '2') {
        View::assign('joinin_detail', $this->joinin_detail);
        View::assign('joinin_message', $message);
        View::assign('btn_next', $btn_next);
        View::assign('step', $step);
        View::assign('sub_step', 'step4');
        echo View::fetch($this->template_dir . 'step4');
        exit;
    }

    private function upload_image($file) {
        //上传文件保存路径
        $pic_name = '';
        $upload_file = BASE_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . 'store_joinin' . DIRECTORY_SEPARATOR;
        if (!empty($_FILES[$file]['name'])) {
            $file_object = request()->file($file);
            //设置特殊图片名称
            $file_name = session('member_id') . '_' . date('YmdHis') . rand(10000, 99999).'.png';


            $file_config = array(
                'disks' => array(
                    'local' => array(
                        'root' => $upload_file
                    )
                )
            );
            config($file_config, 'filesystem');
            try {
                validate(['image' => 'fileSize:' . ALLOW_IMG_SIZE . '|fileExt:' . ALLOW_IMG_EXT])
                        ->check(['image' => $file_object]);
                $file_name = \think\facade\Filesystem::putFileAs('', $file_object, $file_name);
                $pic_name = $file_name;
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }
        return $pic_name;
    }

    /**
     * 检查店铺名称是否存在
     *
     * @param
     * @return
     */
    public function checkname() {
        /**
         * 实例化卖家模型
         */
        $store_model = model('store');
        $store_name = input('get.store_name');
        $store_info = $store_model->getStoreInfo(array('store_name' => $store_name));
        if (!empty($store_info['store_name']) && $store_info['member_id'] != session('member_id')) {
            echo 'false';
        } else {
            echo 'true';
        }
    }

}

?>