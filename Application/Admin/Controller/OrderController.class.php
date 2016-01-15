<?php
namespace Admin\Controller;
use Admin\Model\AuthGroupModel;
use Think\Page;

/**
 * 后台内容控制器
 * @author huajie <banhuajie@163.com>
 */
class OrderController extends AdminController {
    private $site_id        =   null;
    /**
     * 显示左边菜单，进行权限控制
     * @author huajie <banhuajie@163.com>
     */
    protected function getMenu(){
        //获取站点id
        $site_id        =   I('param.site_id', 0, 'intval');
        //获取动态分类
        $site_auth  =   AuthGroupModel::getAuthSiteies(UID); //获取当前用户所有的内容权限节点
        $site_auth  =   $site_auth == null ? array() : $site_auth;
        $site_list  =   C('SITE_LIST');
        if (!IS_ROOT && !in_array($site_id, $site_auth)) {
            $site_id = 0;
        }
        //没有权限的站点则不显示
        $nodes = array();
        foreach ($site_list as $key=>$val){
            if(IS_ROOT || in_array($key, $site_auth)){
                $nodes[$key]['title']   =  $val . '线路';
                $nodes[$key]['url']   =   U('Order/index', array('site_id'=>$key));
                if($site_id && $site_id == $key){
                    $nodes[$key]['current'] = 1;
                }else{
                    $nodes[$key]['current'] = 0;
                }
            }
        }
        if (!IS_ROOT && empty($site_id)) {
            if (count($nodes)) {
                $i = 1;
                foreach ($nodes as $key => $value) {
                    if ($i == 1) {
                        $site_id = $key;
                        $nodes[$key]['current'] = 1;
                        break;
                    }
                    $i++;
                }
            } else {
                $this->redirect('Visa/index');
            }
        }

        // echo '<pre>'; print_r($nodes); echo '</pre>';
        // 扩展菜单
        // $this->assign('_extra_menu', array('旅游线路'=>$nodes));
        $this->assign('nodes', $nodes);
        $this->site_id = $site_id;
        $this->assign('site_id', $site_id);
    }

    /**
     * 线路列表
     * @param integer $cate_id 分类id
     * @param integer $model_id 模型id
     * @param integer $position 推荐标志
     * @param integer $group_id 分组id
     */
    public function index(){
        //获取左边菜单
        $this->getMenu();
        if ($this->site_id) {
            $map['site_id'] = $this->site_id;
        }
        $truename = I('truename');
        if(!empty($truename)){
            $map['order_id|truename|mobile'] = array(array('like','%'.$truename.'%'), array('like','%'.$truename.'%'), array('like','%'.$truename.'%'),'_multi'=>true);
        }
        $order_lists = $this->lists('Order', $map, 'order_id desc');
        foreach ($order_lists as $key => $value) {
            $order_lists[$key]['reserve_info'] = unserialize($value['reserve_info']);
            $order_lists[$key]['order_status_text'] = order_status_text($value['order_status']);
			$order_lists[$key]['pay_status_text'] = pay_status_text($value['pay_status']);
            switch ($value['order_type']) {
                case 'line':
                    $line = M('Line')->field('title,images,starting')->where(array('line_id'=>$value['product_id']))->find();
                    if ($line) {
                        $order_lists[$key]['title'] = $line['title'];
                        $order_lists[$key]['image'] = get_cover(array_shift(explode(',', $line['images'])), 'path');
                        $order_lists[$key]['starting'] = $line['starting'];
                    } else {
                        $order_lists[$key]['title'] = '不存在';
                        $order_lists[$key]['image'] = '';
                        $order_lists[$key]['starting'] = '';
                    }
                    break;

                default:
                    break;
            }
        }
        // echo '<pre>'; print_r($order_lists); echo '</pre>';
        $this->assign('_list', $order_lists);
        $this->meta_title = '线路列表';
        $this->display();
    }

    // 订单详情页
    public function show($order_id = '') {
        if (empty($order_id)) {
            $this->error('非法参数...');
        }
        $order_info = M('Order')->where(array('order_id'=>$order_id))->find();
        if (empty($order_info)) {
            $this->error('订单不存在...');
        }
        $order_info['reserve_info'] = unserialize($order_info['reserve_info']);
        $order_info['order_status_text'] = order_status_text($order_info['order_status']);
        $order_info['pay_status_text'] = pay_status_text($order_info['pay_status']);
        switch ($order_info['order_type']) {
            case 'line':
                $line = M('Line')->field('title,images,starting')->where(array('line_id'=>$order_info['product_id']))->find();
                if ($line) {
                    $order_info['title'] = $line['title'];
                    $order_info['image'] = get_cover(array_shift(explode(',', $line['images'])), 'path');
                    $order_info['starting'] = $line['starting'];
                } else {
                    $order_info['title'] = '不存在';
                    $order_info['image'] = '';
                    $order_info['starting'] = '';
                }
                break;

            default:
                break;
        }
        // echo '<pre>'; print_r($order_info); echo '</pre>';
		$this->assign('order_info', $order_info);
        $this->meta_title = '线路列表';
        $this->display();
    }

    // 修改订单金额
    public function updateMoney($order_id = 0, $order_price = 0) {
        if (empty($order_id) || empty($order_price)) {
            $this->error('非法参数...');
        }
        $data = array(
            'order_id' => $order_id,
            'order_price' => $order_price,
            'update_time' => NOW_TIME
        );
        $result = M('Order')->save($data);
        if ($result) {
            $this->success('成功');
        } else {
            $this->error('失败');
        }
    }

    /**
     * 订单修改
     */
    public function edit($order_id = 0){
        if (empty($order_id)) {
            $this->error('非法参数...');
        }
        $Order = M('Order');
        $order_info = $Order->find($order_id);
        if (empty($order_info)) {
            $this->error('订单不存在...');
        }
        $data = array('order_id'=>$order_id, 'create_time'=>NOW_TIME);
        switch (I('order_status', 1, 'intval')) {
            case '3':
                $data['order_status'] = 3;
                $data['kefu_intro'] = I('kefu_intro');
                $result = $Order->save($data);
                break;
            case '4':
                $pay_status = I('pay_status', 1, 'intval');
                if ($pay_status == 2) {
                    $data['order_status'] = 5;
                } else {
                    $data['order_status'] = 4;
                }
                $data['pay_status'] = $pay_status;
                $data['kefu_intro'] = I('kefu_intro');
                $result = $Order->save($data);
                break;
            case '8':
                $data['order_status'] = 8;
                $data['kefu_intro'] = I('kefu_intro');
                $result = $Order->save($data);
                break;
            case '9':
                $data['order_status'] = 9;
                $data['kefu_intro'] = I('kefu_intro');
                $result = $Order->save($data);
                break;
            default:
                $data['kefu_intro'] = I('kefu_intro');
                $result = $Order->save($data);
                break;
        }
        if ($result) {
            $this->success('成功');
        } else {
            $this->error('失败');
        }
    }

    public function edit2(){
        $line_id     =   I('line_id');
        if(empty($line_id)){
            $this->error('参数不能为空！');
        }
        if (IS_POST) {

        } else  {
            $this->getMenu();
            // 操作步骤
            $setp = array(
                1 => array('title'=>'线路描述', 'url'=>U('edit', array('site_id'=>$this->site_id,'line_id'=>$line_id)), 'current'=>0),
                2 => array('title'=>'线路价格', 'url'=>U('edit2', array('site_id'=>$this->site_id,'line_id'=>$line_id)), 'current'=>1),
                3 => array('title'=>'行程内容', 'url'=>U('edit3', array('site_id'=>$this->site_id,'line_id'=>$line_id)), 'current'=>0)
            );
            $this->assign('setp', $setp);
            $line_info = M('Line')->find($line_id);
            // 线路套餐
            $line_tc = M('LineTc')->where(array('line_id'=>$line_id))->select();
            foreach ($line_tc as $key => $value) {
                if ($value['end_time'] < strtotime('+'.$line_info['earlier_date'].'day')) {
                    $line_tc[$key]['status'] = 0;
                }
            }
            $this->assign('line_tc', $line_tc);
            $this->assign('line_id', $line_id);
            $this->meta_title   =   '编辑线路2';
            $this->display();
        }
    }

    public function edit3(){
        $line_id     =   I('line_id');
        if(empty($line_id)){
            $this->error('参数不能为空！');
        }
        $Line = M('Line');
        if (IS_POST) {
            $data = $Line->create();
            $data['xingcheng'] = serialize($data['xingcheng']);
            $data['remark'] = serialize($data['remark']);
            $data['update_time'] = NOW_TIME;
            $result = $Line->save($data);
            if ($result) {
                $this->success('成功');
            } else {
                $this->error('失败');
            }
        } else  {
            $this->getMenu();
            // 操作步骤
            $setp = array(
                1 => array('title'=>'线路描述', 'url'=>U('edit', array('site_id'=>$this->site_id,'line_id'=>$line_id)), 'current'=>0),
                2 => array('title'=>'线路价格', 'url'=>U('edit2', array('site_id'=>$this->site_id,'line_id'=>$line_id)), 'current'=>0),
                3 => array('title'=>'行程内容', 'url'=>U('edit3', array('site_id'=>$this->site_id,'line_id'=>$line_id)), 'current'=>1)
            );
            $this->assign('setp', $setp);

            $data = $Line->where(array('line_id'=>$line_id))->find();
            $data['xingcheng'] = unserialize($data['xingcheng']);
            $data['remark'] = unserialize($data['remark']);
            $this->assign('data', $data);
            $this->meta_title   =   '编辑线路3';
            $this->display();
        }
    }

    /**
     *  套餐修改
     */
    public function tcEdit(){
        $line_id     =   I('line_id');
        $tc_id     =   I('tc_id');
        if(empty($line_id)){
            $this->error('参数不能为空！');
        }
        $Line = D('Line');
        $LineTc = D('LineTc');
        if (IS_POST) {
            $result = $LineTc->update();
            if ($result) {
                $Line->where(array('line_id'=>$line_id))->setField('status', NOW_TIME);
                $this->success('成功', U('Line/edit2', array('line_id'=>$line_id)));
            } else {
                $this->error($Line->getError());
            }
        } else {
            // 线路套餐
            $data = $LineTc->where(array('tc_id'=>$tc_id))->find();
            if (empty($data)) {
                $data['line_id'] = $line_id;
            }
            $data['earlier_date'] = $Line->where(array('line_id'=>$line_id))->getField('earlier_date');
            $this->assign('data', $data);
            $this->meta_title   =   '价格方案';
            $this->display();
        }
    }
    // 删除套餐操作
    public function tcDel($tc_id = 0){
        if (empty($tc_id)) {
            $this->error('参数错误');
        }
        $LineTc = D('LineTc');
        $map = array('tc_id' => $tc_id);
        $line_tc = $LineTc->where($map)->field('is_default,line_id')->find();
        $result = $LineTc->where($map)->delete();
        if ($result) {
            if ($line_tc['is_default']) {
                $LineTc->where(array('line_id'=>$line_tc['line_id']))->limit(1)->save(array('is_default'=>1));
            }
            $this->success('成功');
        } else {
            $this->error('失败');
        }

    }
    // 设置默认套餐
    public function tcChangeIsDefault($line_id, $tc_id){
        if (empty($line_id) || empty($tc_id)) {
            $this->error('参数错误');
        }
        $LineTc = D('LineTc');
        $map = array('line_id'=>$line_id);
        $LineTc->where(array('line_id'=>$line_id))->save(array('update_time'=>NOW_TIME,'is_default'=>0));
        $result = $LineTc->where(array('tc_id'=>$tc_id))->save(array('update_time'=>NOW_TIME,'is_default'=>1));
        if ($result) {
            $this->success('成功');
        } else {
            $this->error('失败');
        }
    }

}
