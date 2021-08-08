<?php

/**
 * 微信配置
 */
namespace app\admin\controller;
use think\facade\View;
use think\facade\Db;
use app\api\controller\WechatApi;
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
class  Wechat extends BaseSeller {

    public function initialize() {
        parent::initialize(); // TODO: Change the autogenerated stub
        Lang::load(base_path() . 'admin/lang/'.config('lang.default_lang').'/wechat.lang.php');
    }

    //公众号配置
    public function setting() {
        $wechat_model = model('wechat');
        if (!request()->isPost()) {
            //获取公众号配置信息
            $wx_config = $wechat_model->getOneWxconfig();
            View::assign('wx_config', $wx_config);
            //接口地址
            $wx_apiurl = HTTP_TYPE . $_SERVER['HTTP_HOST'] . substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], 'index.php')) . 'api/Wechat/index';
            View::assign('wx_apiurl', $wx_apiurl);
            return View::fetch();
        } else {
            $data = [
                'token' => input('post.wx_token'),
                'appid' => input('post.wx_appid'),
                'appsecret' => input('post.wx_AppSecret'),
                'wxname' => input('post.wx_name'),
                'xcx_appid' => input('post.xcx_appid'),
                'xcx_appsecret' => input('post.xcx_AppSecret'),
            ];
            //公众号二维码图片待处理
            $id = input('param.wx_id');
            if (empty($id)) {
                $res = $wechat_model->addWxconfig($data);
            } else {
                $res = $wechat_model->editWxconfig(array('id' => $id),$data);
            }
            if ($res) {
                $this->success(lang('ds_common_op_succ'));
            } else {
                $this->error(lang('ds_common_op_fail'));
            }
        }
    }
   

    //公众号菜单
    public function menu() {
        //获取顶级菜单
        $wechat_model = model('wechat');
        $p_menu = $wechat_model->getWxmenuList(array('pid'=>0),'sort ASC');
        //获取二级菜单
        $child_menu = array();
        foreach ($p_menu as $k => $v) {
            $child_list = $wechat_model->getWxmenuList(array('pid'=>$v['id']),'sort desc');
            $child_menu[$v['id']] = $child_list;
        }
        $menu_type = array('view' => lang('menu_type_1'), 'click' => lang('menu_type_2'), 'view_limited' => lang('menu_type_3'));
        View::assign('menu_type', $menu_type);
        View::assign('p_menu', $p_menu);
        View::assign('c_menu', $child_menu);
        $this->setAdminCurItem('menu');
        return View::fetch();
    }

    //菜单编辑
    public function menu_edit() {
        $wechat_model = model('wechat');
        $menu_id = intval(input('param.id'));
        if (empty($menu_id)) {
            $this->error(lang('param_error'));
        }
        if (!request()->isPost()) {
            $parents = $wechat_model->getWxmenuList(array('pid'=>0),'id desc','name,id');
            View::assign('parents', $parents);
            $condition = array('id'=>$menu_id);
            $menu = $wechat_model->getOneWxmenu($condition);
            View::assign('menu', $menu);
            return View::fetch('menu_form');
        } else {
            $menu_name = input('post.menu_name');
            $menu_value = input('post.menu_value');
            $menu_sort = input('post.menu_sort');
            $menu_type = input('post.menu_type');
            $menu_pid = input('post.menu_pid');
            $data = [
                'name' => $menu_name,
                'value' => $menu_value,
                'sort' => $menu_sort,
                'type' => $menu_type,
                'pid' => $menu_pid,
            ];
            //添加顶级菜单时判断是否超过限定数量
            if ($data['pid'] == '0' && $menu_id > 0) {
                $num = $wechat_model->getWxmenuCount(array('pid'=>0));
                if ($num > 3) {
                    $this->error('顶级菜单只能有三个');
                }
            }

            $wechat_validate = ds_validate('wechat');
            if (!$wechat_validate->scene('menu_edit')->check($data)) {
                $this->error($wechat_validate->getError());
            }

            $result = $wechat_model->editWxmenu(array('id'=>$menu_id),$data);
            if ($result) {
                dsLayerOpenSuccess(lang('ds_common_op_succ'));
            } else {
                $this->error(lang('ds_common_save_fail'));
            }
        }
    }

    //菜单添加
    public function menu_add() {
        $wechat_model = model('wechat');
        if (!request()->isPost()) {
            $data = [
                'pid' => '',
                'type' => 'view',
            ];
            $parents = $wechat_model->getWxmenuList(array('pid'=>0),'id desc','name,id');
            View::assign('parents', $parents);
            View::assign('menu', $data);
            return View::fetch('menu_form');
        } else {
            $menu_name = input('post.menu_name');
            $menu_value = input('post.menu_value');
            $menu_sort = input('post.menu_sort');
            $menu_type = input('post.menu_type');
            $menu_pid = input('post.menu_pid');
            $data = [
                'name' => $menu_name,
                'value' => $menu_value,
                'sort' => $menu_sort,
                'type' => $menu_type,
                'pid' => $menu_pid,
            ];
            //添加顶级菜单时判断是否超过限定数量
            if ($data['pid'] == '0') {
                $num = $wechat_model->getWxmenuCount(array('pid'=>0));
                if ($num > 2) {
                    $this->error('顶级菜单只能有三个');
                }
            }
            $wechat_validate = ds_validate('wechat');
            if (!$wechat_validate->scene('menu_add')->check($data)) {
                $this->error($wechat_validate->getError());
            }

            $result = $wechat_model->addWxmenu($data);
            if ($result) {
                dsLayerOpenSuccess(lang('ds_common_save_succ'));
            } else {
                $this->error(lang('ds_common_save_fail'));
            }
        }
    }

    //菜单删除
    public function menu_drop() {
        $wechat_model = model('wechat');
        $menu_id = input('param.id');
        if (empty($menu_id)) {
            $this->error(lang('param_error'));
        }
        $res = $wechat_model->delWxmenu(array('id'=>$menu_id));
        if ($res) {
            ds_json_encode(10000, lang('ds_common_del_succ'));
        } else {
            ds_json_encode(10001, lang('ds_common_del_fail'));
        }
    }

    //更新公众号菜单
    public function pub_menu() {
        //获取菜单
        $config = model('wechat')->getOneWxconfig();
        //获取父级菜单
        $wechat_model = model('wechat');
        $p_menus = $wechat_model->getWxmenuList(array('pid' => 0),'id ASC');
        $p_menus = ds_change_arraykey($p_menus, 'id');

        $post_str = $this->convert_menu($p_menus);

        // http post请求
        if (!count($p_menus) > 0) {
            $this->error('没有菜单可发布', 'Wechat/menu');
            exit;
        }
        //查看access_token是否过期
        $wechat = new WechatApi($config);
        $expire_time = $config['expires_in'];
        if ($expire_time > TIMESTAMP) {
            //有效期内
            $wechat->access_token_ = $config['access_token'];
        } else {
            $access_token = $wechat->checkAuth();
            if($access_token == FALSE){
                $this->success('获取Token失败', 'Wechat/menu');
            }
            $web_expires = TIMESTAMP + 7000; // 提前200秒过期
            $condition = array();
            $condition[] = array('id', '=', $config['id']);
            $data = array('access_token' => $access_token, 'expires_in' => $web_expires);
            $wechat_model->editWxconfig($condition,$data);
        }
        $return = $wechat->createMenu($post_str);
        if ($return) {
            $this->success('菜单已成功生成', 'Wechat/menu');
        } else {
            $this->error("错误代码;" . $wechat->errCode.$wechat->errMsg);
        }
    }

    //菜单转换
    private function convert_menu($p_menus) {
        $wechat_model = model('wechat');
        $new_arr = array();
        $count = 0;
        foreach ($p_menus as $k => $v) {
            $new_arr[$count]['name'] = $v['name'];

            //获取子菜单
            $c_menus = $wechat_model->getMenulist(array('pid' => $k));
            if ($c_menus) {
                foreach ($c_menus as $kk => $vv) {
                    $add = array();
                    $add['name'] = $vv['name'];
                    $add['type'] = $vv['type'];
                    // click类型
                    if ($add['type'] == 'click') {
                        $add['key'] = $vv['value'];
                    } elseif ($add['type'] == 'view') {
                        $add['url'] = $vv['value'];
                    } else {
                        $add['key'] = $vv['value'];
                    }
                    $add['sub_button'] = array();
                    if ($add['name']) {
                        $new_arr[$count]['sub_button'][] = $add;
                    }
                }
            } else {
                $new_arr[$count]['type'] = $v['type'];
                // click类型
                if ($new_arr[$count]['type'] == 'click') {
                    $new_arr[$count]['key'] = $v['value'];
                } elseif ($new_arr[$count]['type'] == 'view') {
                    //跳转URL类型
                    $new_arr[$count]['url'] = $v['value'];
                } else {
                    //其他事件类型
                    $new_arr[$count]['key'] = $v['value'];
                }
            }
            $count++;
        }

        return array('button' => $new_arr);
    }

    /**
     * 关键字文本回复
     */
    public function k_text() {
        $wechat_model = model('wechat');
        $wechat = $wechat_model->getOneWxconfig();
        if (empty($wechat)) {
            $this->error('请先在公众号配置添加公众号，才能进行文本回复管理', 'Wechat/setting');
        }
        $lists = $wechat_model->getWxkeywordList(array('type' => 'TEXT'),'k.id,k.keyword,t.text',10,'t.createtime DESC');
        View::assign('lists', $lists);
        View::assign('show_page', $wechat_model->page_info->render());
        return View::fetch();
    }

    /*
     * 添加文本回复
     */

    public function text_form() {
        $wechat_model = model('wechat');
        $wechat = $wechat_model->getOneWxconfig();
        if (empty($wechat)) {
            $this->error('请先在公众号配置添加公众号，才能添加文本回复', 'Wechat/setting');
        }
        if (request()->isPost()) {
            $kid = input('param.id');
            $add['keyword'] = input('param.keyword');
            $add['text'] = input('param.text');
            if (empty($kid)) {
                //添加模式
                $add['createtime'] = TIMESTAMP;
                $add['pid'] = $wechat_model->addWxtext($add);
                unset($add['text']);
                unset($add['createtime']);
                $add['type'] = 'TEXT';
                $row = $wechat_model->addWxkeyword($add);
            } else {
                //编辑模式
                $data = $wechat_model->getOneWxkeyword(array('id' => $kid));
                if ($data) {
                    $update['keyword'] = $add['keyword'];
                    $wechat_model->editWxkeyword(array('id' => $kid),$update);
                    $row = $wechat_model->editWxtext(array('id' => $data['pid']),$add);
                }
            }
            $row>=0 ? dsLayerOpenSuccess(lang('ds_common_op_succ')) : $this->error("添加失败", 'Wechat/k_text');
            exit;
        } else {
            //编辑状态
            $id = intval(input('param.id'));;
            $key = array();
            if ($id) {
                $where = "k.id={$id} AND k.type='TEXT'";
                $res = Db::name('wxkeyword')->alias('k')->join('wxtext t', 't.id=k.id', 'LEFT')->where($where)->field('k.id,k.keyword,t.text')->find();
                View::assign('key', $res);
            }
            return View::fetch();
        }
    }

    /*
     * 删除文本回复
     */

    public function del_text() {
        $wechat_model = model('wechat');
        $id = input('param.id');
        $row = $wechat_model->getOneWxkeyword(array('id' => $id));
        if ($row) {
            $wechat_model->delWxkeyword(array('id' => $id));
            $wechat_model->delWxtext(array('id' => $row['pid']));
            ds_json_encode(10000, lang('ds_common_del_succ'));
        } else {
            ds_json_encode(10001, lang('ds_common_del_fail'));
        }
    }
    
    /**
     * 删除消息推送
     */
    public function del_wxmsg(){
        $wechat_model = model('wechat');
        $id = input('param.id');
        $id_array = ds_delete_param($id);
        if($id_array === FALSE){
            ds_json_encode(10001, lang('param_error'));
        }
        $condition = array(array('id','in', $id_array));
        $result =$wechat_model->delWxmsg($condition);
        if($result){
            ds_json_encode(10000, lang('ds_common_del_succ'));
        } else {
            ds_json_encode(10001, lang('ds_common_del_fail'));
        }
        
    }

    /*     * 微信注册会员列表 */

    public function member() {
        $wechat_model = model('wechat');
        $wxmember_list = $wechat_model->getWxmemberList();
        View::assign('show_page', $wechat_model->page_info->render());
        View::assign('wxmember_list', $wxmember_list);
        return View::fetch('member');
    }

    /*     * 消息推送 */

    public function msend() {
        $touser = input('param.openid');
        $id = input('param.member_id');
        if (request()->isPost()) {
            $config = model('wechat')->getOneWxconfig();
            $wechat = new WechatApi($config);
            $type = input('param.type');
            if ($type == 'text') {
                //发送文本消息
                $content = input('param.text');
                $send = array(
                    'touser' => $touser, 'msgtype' => 'text', 'text' => array('content' => $content)
                );
            } else {
                //发送图文消息
                $title = input('param.title');
                $description = input('param.description');
                $url = input('param.url');
                $picUrl = '';
                if (!empty($_FILES['s_pic']['name'])) {
                    $prefix = $id;
                    $file_path = BASE_UPLOAD_PATH . DIRECTORY_SEPARATOR . DIR_ADMIN . DIRECTORY_SEPARATOR . 'wechat';
                    $file_name = $prefix . '_' . date('YmdHis') . rand(10000, 99999).'.png';
                    $file = request()->file('s_pic');


                    $file_config = array(
                        'disks' => array(
                            'local' => array(
                                'root' => $file_path
                            )
                        )
                    );
                    config($file_config, 'filesystem');
                    try {
                        validate(['image' => 'fileSize:' . ALLOW_IMG_SIZE . '|fileExt:' . ALLOW_IMG_EXT])
                                ->check(['image' => $file]);
                        $file_name = \think\facade\Filesystem::putFileAs('', $file, $file_name);
                    } catch (\Exception $e) {
                        $this->error($e->getMessage());
                    }
                    $picUrl = UPLOAD_SITE_URL . DIRECTORY_SEPARATOR . DIR_ADMIN . DIRECTORY_SEPARATOR . 'wechat' . DIRECTORY_SEPARATOR . $file_name;
                }
                $content = array(
                    array(
                        'title' => $title, 'description' => $description, 'url' => $url, 'picurl' => $picUrl
                    )
                );
                $send = array(
                    'touser' => $touser, 'msgtype' => 'news', 'news' => array('articles' => $content)
                );
            }

            $SendInfo = serialize($send);
            $data['member_id'] = $id;
            $data['content'] = $SendInfo;
            $data['createtime'] = TIMESTAMP;
            $ret = $wechat->sendCustomMessage($send);
            if ($ret) {
                //添加至推送列表
                $data['issend'] = '1';
                model('wechat')->addWxmsg($data);
                dsLayerOpenSuccess('发送成功');
            }else {
                $data['issend'] = '0';
                model('wechat')->addWxmsg($data);
                $this->error('发送失败,错误代码:' . $wechat->errCode);
            }
        } else {
            return View::fetch();
        }
    }

    /*     * 消息推送列表 */

    public function SendList() {
        $wechat_model=model('wechat');
        $list = $wechat_model->getWxmsgList();
        foreach ($list as $key => $val) {
            $info = unserialize($val['content']);
            $type = $info['msgtype'];
            $list[$key]['type'] = $type == 'text' ? '文本' : '图文';
            if ($type == 'text') {
                $list[$key]['content'] = $info['text']['content'];
            } else {
                $content = $info['news']['articles']['0'];
                $content = json_encode($content);
                $list[$key]['content'] = "<a href='javascript:void(0);' class='news' content=''>查看图文消息</a>";
                /* View::assign('title',$content['title']);
                  View::assign('description',$content['description']);
                  View::assign('url',$content['url']);
                  echo View::fetch('news'); */
            }
        }
        View::assign('show_page', $wechat_model->page_info->render());
        View::assign('lists', $list);
        return View::fetch('list');
    }

    /*     * 消息群发 */

    public function Sendgroup() {
        if (request()->isPost()) {
            $m_info = model('wechat')->getWxmemberList();
            $openid = '';
            foreach ($m_info as $k => $val) {
                $openid .= $val['member_wxopenid'] . ',';
            }
            $openid = explode(',', $openid);
            $content = input('param.text');
            $send = array(
                'touser' => $openid,
                'msgtype' => 'text',
                'text' => array('content' => $content)
            );
            $config = model('wechat')->getOneWxconfig();
            $wechat = new WechatApi($config);
            $res = $wechat->massSend($send);
            if ($res) {
                dsLayerOpenSuccess('群发成功');
            }else{
                $this->error('发送失败,错误代码:' . $wechat->errCode);
            }
        }else{
            return View::fetch('sendgroup');
        }
        
    }

    protected function getAdminItemList() {
        $menu_array = array(
            array(
                'name' => 'menu',
                'text' => '菜单',
                'url' => url('Wechat/menu')
            ),
            array(
                'name' => 'menu_add',
                'text' => '新增自定义菜单',
                'url' => "javascript:dsLayerOpen('" . url('Wechat/menu_add') . "','新增自定义菜单')"
            ),
        );
        return $menu_array;
    }

}