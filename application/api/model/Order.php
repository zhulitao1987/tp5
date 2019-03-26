<?php

namespace app\api\model;

use think\Model;
use think\Db;

/**
 * @function 订单中心底层操作类
 *
 * @author zhult
 */
class Order extends Model {

    public $order_id;
    public $order_sn;
    public $user_id;
    public $order_status = 0;
    public $shipping_status = 0;
    public $pay_status = 0;
    public $deleted = 0;
    public $page = 0;
    public $page_size = 20;

    /**
     * @function 添加订单
     * @return int 返回订单记录的ID
     */
    public function addOrder($params = array()) {
        return Db::name("order")->insertGetId($params);
    }

    /**
     * @function 修改订单订单信息
     * @paran arrat $params 修改参数
     * @param int $order_id 订单ID
     * @return boolean true|false
     */
    public function updateOrderInfo($params = array(), $order_id = 0) {
        return Db::name("order")->where("order_id", $order_id)->update($params);
    }

    /**
     * @function 查询单条订单记录
     * @param int $order_id 订单ID
     * @return array
     */
    public function selectOne($order_id) {
        return Db::name("order")->where("order_id", $order_id)->find();
    }

    /**
     * @function 查询订单计数统计
     * @return int 
     * @author zhult
     */
    public function selectCount() {
        $params['order_status'] = array('eq', $this->order_status);
        $params['shipping_status'] = array('eq', $this->shipping_status);
        $params['pay_status'] = array('eq', $this->pay_status);
        $params['deleted'] = array('eq', $this->deleted);
        if ($this->user_id) {
            $params['user_id'] = array('eq', $this->user_id);
        }
        $list = Db::name("order")->where($params)->select();
        return count($list);
    }

    /**
     * @function 查询订单列表
     * @return array
     * @author zhult
     */
    public function selectList() {
        $params['order_status'] = array('eq', $this->order_status);
        $params['shipping_status'] = array('eq', $this->shipping_status);
        $params['pay_status'] = array('eq', $this->pay_status);
        $params['deleted'] = array('eq', $this->deleted);
        if ($this->user_id) {
            $params['user_id'] = array('eq', $this->user_id);
        }
        return Db::name("order")->where($params)->limit($this->page * $this->page_size, $this->page_size)->select();
    }

    /**
     * @function 添加订单商品
     * @param array $params 
     * @return boolean true|false
     */
    public function addOrderGoods($params = array()) {
        return Db::name("order_goods")->insert($params);
    }

    /**
     * @function 根据订单ID查询对应的订单商品信息
     * @param int $order_id 订单ID
     * @return array 订单商品数据
     * @author zhult
     */
    public function selectOrderGoodsInfo($order_id = 0) {
        return Db::name("order_goods")->where("order_id", $order_id)->find();
    }

    /**
     * @function 添加订单商品
     * @param array $params 
     * @return boolean true|false
     */
    public function addOrderAction($params = array()) {
        return Db::name("order_action")->insert($params);
    }

}
