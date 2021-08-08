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
class  Sellerlog extends BaseSeller
{
    public function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
        Lang::load(base_path() . 'home/lang/'.config('lang.default_lang').'/sellerlog.lang.php');
    }

    public function log_list()
    {
        $sellerlog_model = model('sellerlog');
        $condition = array();
        $condition[]=array('sellerlog_store_id','=',session('store_id'));
        $seller_name = input('seller_name');
        $log_content = input('log_content');
        $add_time_from = input('add_time_from');
        $add_time_to = input('add_time_to');
        if (!empty($seller_name)) {
            $condition[] = array('sellerlog_seller_name', 'like','%' . input('seller_name') . '%');
        }
        if (!empty($log_content)) {
            $condition[]=array('sellerlog_content','like','%' . $log_content . '%');
        }
        if(!empty($add_time_from)||$add_time_to){
        $condition[] = array('sellerlog_time','between',[strtotime($add_time_from),strtotime($add_time_to)]);
        }
        $log_list = $sellerlog_model->getSellerlogList($condition, 10, 'sellerlog_id desc');
        View::assign('log_list', $log_list);
         View::assign('show_page', $sellerlog_model->page_info->render());

        /* 设置卖家当前菜单 */
        $this->setSellerCurMenu('sellerlog');
        /* 设置卖家当前栏目 */
        $this->setSellerCurItem('log_list');
        return View::fetch($this->template_dir.'seller_log');
    }

    /**
     * 用户中心右边，小导航
     *
     * @param string $menu_key 当前导航
     * @return
     */
    public function getSellerItemList()
    {
        $menu_array = array();
        $menu_array[] = array(
            'name' => 'log_list',
            'text' => '日志列表',
            'url' => url('Sellerlog/log_list')
        );
        return $menu_array;
    }
}