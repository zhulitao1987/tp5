<?php

namespace app\api\controller\v1;

use app\api\model\Order as OrderModel;
use app\api\model\Coupon as CouponModel;
use app\api\model\Users as UserModel;
use app\api\model\Goods as GoodsModel;
use app\api\model\Account as AccountModel;
use app\api\model\Address as AddressModel;
use app\api\controller\Base;
use think\Db;
use think\Exception;

/**
 * 订单API
 *
 * @author zhult
 */
class Order extends Base {
    
    /**
     * @function 用户下单
     * @author zhult
     */
    public function addOrder() {
        $this->postRule = [
            'user_id' => [1, 'num', '用户id', 'user_id', 1],
            'goods_id' => [1, 'num', '商品id', 'goods_id', 1],
            'goods_num' => [1, 'num', '购买的商品数量', 'goods_num', 1],
            'token' => [1, 'string', 'token字段', 'token', ""],
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $user_id  = $params['user_id'];
        $timestamp = getRequestTime(1);
        $order_sn = getOrderNum();
        $goods_num = $params['goods_num'];
        if ($goods_num <= 0) {
            outJson(1115, errs_out(1115));
        }
        //TOKEN验证
        $token_arr = [
                        "user_id" => $user_id,
                        "token"   => $params["token"]
        ];
        checkSign($token_arr);
        //参数验证
        $goods_id = $params['goods_id'];
        if (empty($user_id) || empty($goods_id)) {
            outJson(97, errs_out(97));
        }
        //model
        $OrderModel = new OrderModel();
        $UserModel = new UserModel();
        $GoodsModel = new GoodsModel();
        $AddressModel = new AddressModel();
        //判断用户是否存在
        $user_info = $UserModel->selectOne($user_id);
        if (empty($user_info)) {
            outJson(97, errs_out(97));
        }
        //获取商品信息
        $goods_info = $GoodsModel->selectOne($goods_id);
        if (empty($goods_info)) {
            outJson(97, errs_out(97));
        }
        //获取用户收货地址信息
        $AddressModel->user_id = $user_id;
        $address_info = $AddressModel->selectList();
        if (empty($address_info)) {
            outJson(97, errs_out(97));
        }
        //订单信息预处理
        $goods_price = $goods_info['shop_price'] * $goods_num;
        $total_amount = $goods_info['shop_price'] * $goods_num;
        //启动事务
        Db::startTrans();
        //excute
        try {
            //添加订单信息
            $order_params = array(
                'order_sn'         => $order_sn,
                'user_id'          => $user_id, // 用户id
                'consignee'        => $address_info['consignee'], // 收货人
                'province'         => $address_info['province'],//'省份id',
                'city'             => $address_info['city'],//'城市id',
                'district'         => $address_info['district'],//'县',
                'twon'             => $address_info['town'],// '街道',
                'address'          => $address_info['address'],//'详细地址',
                'mobile'           => $address_info['mobile'],//'手机',
                'zipcode'          => $address_info['zipcode'],//'邮编',
                'email'            => $address_info['email'],//'邮箱',
                'goods_price'      => $goods_price,//'商品价格',
                'total_amount'     => $total_amount,// 订单总额
                'add_time'         => $timestamp, // 下单时间
            );
            $order_ret = $OrderModel->addOrder($order_params);
            if (empty($order_ret)) {
                Db::rollback();
                outJson(114, "添加订单失败");
            }
            //添加订单商品数据
            $order_gooods_parama = array(
                'order_id' => $order_ret,
                'goods_id' => $goods_id,
                'goods_name' => $goods_info['goods_name'],
                'goods_sn' => $goods_info['goods_sn'],
                'goods_num' => $params['goods_num'],
                'final_price' => $total_amount,
                'goods_price' => $goods_info['shop_price'],
                'cost_price' => $goods_price,
                'add_time' => $timestamp
            );
            $order_gooods_ret = $OrderModel->addOrderGoods($order_gooods_parama);
            if (empty($order_gooods_ret)) {
                Db::rollback();
                outJson(114, "添加订单商品信息失败");
            }
            //记录订单操作行为
            $order_action_params = array(
                'order_id'    => $order_ret,
                'action_user' => 0,
                'order_status'=> 0,
                'shipping_status' => 0,
                'pay_status'  => 0,
                'action_note' => '您提交了订单，请等待系统确认',
                'log_time'    => $timestamp,
                'status_desc' => '提交订单'
            );
            $order_action_ret = $OrderModel->addOrderAction($order_action_params);
            if (empty($order_action_ret)) {
                Db::rollback();
                outJson(114, "添加订单行为失败");
            }
            Db::commit();
            outJson(0, "下单成功");
        } catch (Exception $ex) {
            Db::rollback();
            outJson(500, $ex->getMessage());
        }
    }
    
    /**
     * @function 用户修改订单(支付成功回调)
     * @author zhult
     */
    public function paySuccessOrder(){
        $this->postRule = [
            'user_id' => [1, 'num', '用户id', 'user_id', 1],
            'token' => [1, 'string', 'token字段', 'token', ""],
            'order_id' => [1, 'num', '订单id', 'order_id', 1],
            'coupon_id' => [0, 'num', '优惠券id', 'coupon_id', 0]
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $user_id  = $params['user_id'];
        $order_id = $params['order_id'];
        $timestamp = getRequestTime(1);
        //TOKEN验证
        $token_arr = [
                        "user_id" => $user_id,
                        "token"   => $params["token"]
        ];
        checkSign($token_arr);
        //model
        $OrderModel = new OrderModel();
        $CouponModel = new CouponModel();
        $AccountModel = new AccountModel();
        $GoodsModel = new GoodsModel();
        $UserModel = new UserModel();
        //判断用户是否存在
        $user_info = $UserModel->selectOne($user_id);
        if (empty($user_info)) {
            outJson(97, errs_out(97));
        }
        //订单信息预处理
        $order_info = $OrderModel->selectOne($order_id);
        if (empty($order_info)) {
            outJson(97, errs_out(97));
        }
        //查询订单对应的订单商品数据
        $orderGoodsInfo = $OrderModel->selectOrderGoodsInfo($order_id);
        if (empty($orderGoodsInfo)) {
            outJson(97, errs_out(97));
        }
        $goods_id = $orderGoodsInfo['goods_id'];
        //查询对应的商品数据
        $goods_info = $GoodsModel->selectOne($goods_id);
        if (empty($goods_info)) {
            outJson(97, errs_out(97));
        }
        //更新库存数据
        $store_count = $goods_info['store_count'] - $orderGoodsInfo['goods_num'];
        //使用优惠券减价
        if (!empty($params['coupon_id'])) {
            $coupon_info = $CouponModel->selectOne($params['coupon_id']);
            $coupon_origin_id = $coupon_info['cid'];
            $coupon_origin_info = $CouponModel->selectCouponOriInfo($coupon_origin_id);
            if ($timestamp < $coupon_origin_info['use_start_time'] || $timestamp > $coupon_origin_info['use_end_time']) {
                outJson(1111, errs_out(1111));
            }
            if ($total_amount >= $coupon_origin_info['condition']) {
                $total_amount = bcsub($total_amount, $coupon_origin_info['money'], 2);
            }
        }
        //启动事务
        Db::startTrans();
        //excute
        try {
            //修改订单状态
            $order_update_params = array(
                'order_status' => 1,
                'shipping_status' => 1,
                'pay_status' => 1
            );
            $order_update_ret = $OrderModel->updateOrderInfo($order_update_params, $order_id);
            if (empty($order_update_ret)) {
                Db::rollback();
                outJson(114, "订单付款失败");
            }
            //修改商品库存数据
            $goods_update_params = array(
                'store_count' => $store_count
            );
            $goods_update_ret = $GoodsModel->updateGoodsInfo($goods_update_params, $goods_id);
            if (empty($goods_update_ret)) {
                Db::rollback();
                outJson(114, "订单付款失败");
            }
            //记录订单操作行为
            $order_action_params = array(
                'order_id'    => $order_info['order_id'],
                'action_user' => 0,
                'order_status'=> 0,
                'shipping_status' => 0,
                'pay_status'  => 0,
                'action_note' => '订单付款成功',
                'log_time'    => $timestamp,
                'status_desc' => '付款成功'
            );
            $order_action_ret = $OrderModel->addOrderAction($order_action_params);
            if (empty($order_action_ret)) {
                Db::rollback();
                outJson(114, "添加订单行为失败");
            }
            //添加账户流水
            $order_account_params = array(
                'user_id'  => $user_id,
                'change_time' => $timestamp,
                'order_sn' => $order_info['order_sn'],
                'order_id' => $order_info['order_id']
            );
            $order_account_ret = $AccountModel->addAccountLog($order_account_params);
            if (empty($order_account_ret)) {
                Db::rollback();
                outJson(114, "添加订单账户金额失败");
            }
            //优惠券信息处理
            if (!empty($params['coupon_id'])) {
                $update_params = array(
                    'order_id' => $order_info['order_id'],
                    'use_time' => $timestamp,
                    'status' => 1
                );
                $coupon_ret = $CouponModel->updateCouponInfo($update_params, $params['coupon_id']);
                if (empty($coupon_ret)) {
                    Db::rollback();
                    outJson(114, "优惠券信息修改失败");
                }
            }
            Db::commit();
            outJson(0, "下单成功");
        } catch (Exception $ex) {
            Db::rollback();
            outJson(500, $ex->getMessage());
        }
    }

    /**
     * @function 用户修改订单(取消订单)
     * @author zhult
     */
    public function cancelOrder() {
        $this->postRule = [
            'user_id' => [1, 'num', '用户id', 'user_id', 1],
            'order_id' => [1, 'num', '订单id', 'order_id', 1],
            'token' => [1, 'string', 'token字段', 'token', ""],
            'coupon_id' => [0, 'num', '优惠券id', 'coupon_id', 0]
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $user_id  = $params['user_id'];
        $order_id = $params['order_id'];
        if (empty($user_id) || empty($order_id)) {
            outJson(97, errs_out(97));
        }
        $timestamp = getRequestTime(1);
        //TOKEN验证
        $token_arr = [
                        "user_id" => $user_id,
                        "token"   => $params["token"]
        ];
        checkSign($token_arr);
        //model
        $OrderModel = new OrderModel();
        $CouponModel = new CouponModel();
        //查询订单信息
        $order_info = $OrderModel->selectOne($order_id);
        if (empty($order_info)) {
            outJson(97, errs_out(97));
        }
        //查询订单对应的使用优惠券信息
        $conpon_info = $CouponModel->selectOne($order_id);
        //启动事务
        Db::startTrans();
        //excute
        try {
            //修改订单状态
            $order_update_params = array(
                'order_status' => 3
            );
            $order_update_ret = $OrderModel->updateOrderInfo($order_update_params, $order_id);
            if (empty($order_update_ret)) {
                Db::rollback();
                outJson(114, "取消订单失败");
            }
            //如果使用了优惠券,修改优惠券状态
            if (!empty($conpon_info)) {
                $conpon_update_params = array(
                    'status' => 0
                );
                $conpon_update_ret = $CouponModel->updateCouponInfo($conpon_update_params, $conpon_info['id']);
                if (empty($conpon_update_ret)) {
                    Db::rollback();
                    outJson(114, "优惠券信息异常，取消订单失败");
                }
             }
            //记录订单操作行为
            $order_action_params = array(
                'order_id'    => $order_id,
                'action_note' => '您取消了订单',
                'log_time'    => $timestamp,
                'status_desc' => '用户取消订单'
            );
            $order_action_ret = $OrderModel->addOrderAction($order_action_params);
            if (empty($order_action_ret)) {
                Db::rollback();
                outJson(114, "添加订单行为失败");
            }
            Db::commit();
            outJson(0, "取消订单成功");
        } catch (Exception $ex) {
            Db::rollback();
            outJson(500, $ex->getMessage());
        }
    }
    
    /**
     * @function 用户删除订单
     * @author zhult
     */
    public function delOrder() {
        $this->postRule = [
            'user_id' => [1, 'num', '用户id', 'user_id', 1],
            'order_id' => [1, 'num', '订单id', 'order_id', 1],
            'token' => [1, 'string', 'token字段', 'token', ""],
            'coupon_id' => [0, 'num', '优惠券id', 'coupon_id', 0]
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $user_id  = $params['user_id'];
        $order_id = $params['order_id'];
        if (empty($user_id) || empty($order_id)) {
            outJson(97, errs_out(97));
        }
        $timestamp = getRequestTime(1);
        //TOKEN验证
        $token_arr = [
                        "user_id" => $user_id,
                        "token"   => $params["token"]
        ];
        checkSign($token_arr);
        //model
        $OrderModel = new OrderModel();
        //查询订单信息
        $order_info = $OrderModel->selectOne($order_id);
        if (empty($order_info)) {
            outJson(97, errs_out(97));
        }
        //启动事务
        Db::startTrans();
        //excute
        try {
            //删除订单
            $order_update_params = array(
                'order_status' => 6,
                'deleted' => 1
            );
            $order_update_ret = $OrderModel->updateOrderInfo($order_update_params, $order_id);
            if (empty($order_update_ret)) {
                Db::rollback();
                outJson(114, "删除订单失败");
            }
            //记录订单操作行为
            $order_action_params = array(
                'order_id'    => $order_id,
                'action_note' => '您删除了订单',
                'log_time'    => $timestamp,
                'status_desc' => '用户删除订单'
            );
            $order_action_ret = $OrderModel->addOrderAction($order_action_params);
            if (empty($order_action_ret)) {
                Db::rollback();
                outJson(114, "添加订单行为失败");
            }
            Db::commit();
            outJson(0, "删除订单成功");
        } catch (Exception $ex) {
            Db::rollback();
            outJson(500, $ex->getMessage());
        }
    }

    /**
     * @function 根据订单号查询订单详细新
     * @author Zhu Litao
     */
    public function selectOne() {
        $this->postRule = [
            'order_id' => [1, 'num', '订单id', 'order_id', 1],
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $order_id = $params['order_id'];
        $orderModel = new OrderModel();
        $return = $orderModel->selectOne($order_id);
        if (empty($return)) {
            outJson(113, errs_out(113));
        }
        outJson(0, $return);
    }

    /**
     * @function 查询订单列表
     * @author zhult
     */
    public function selectList() {
        $this->postRule = [
            'user_id' => [1, 'num', '用户id', 'user_id', 0],
            'token' => [1, 'string', 'token字段', 'token', ""],
            'order_status' => [0, 'num', '订单状态', 'order_status', 0],
            'shipping_status' => [0, 'num', '发货状态', 'shipping_status', 0],
            'pay_status' => [0, 'num', '支付状态', 'pay_status', 0],
            'page' => [0, 'num', '分页第?页', 'page', 0],
            'page_size'   => [0, 'num', '每页记录数', 'page_size', 20]
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $orderModel = new OrderModel();
        if (isset($params['user_id'])) {
            $orderModel->user_id = $params['user_id'];
        }
        if (isset($params['order_status'])) {
            $orderModel->order_status = $params['order_status'];
        }
        if (isset($params['shipping_status'])) {
            $orderModel->shipping_status = $params['shipping_status'];
        }
        if (isset($params['pay_status'])) {
            $orderModel->pay_status = $params['pay_status'];
        }
        if (isset($params['page'])) {
            $orderModel->page = $params['page'];
        }
        if (isset($params['page_size'])) {
            $orderModel->page_size = $params['page_size'];
        }
        //TOKEN验证
        $token_arr  =   [
                            "user_id"   => $params["user_id"],
                            "token"     => $params["token"]
                        ];
        checkSign($token_arr);
        $list = $orderModel->selectList();
        if (empty($list)) {
            outJson(113, errs_out(113));
        }
        $count = $orderModel->selectCount();
        $return_data = array(
            'list' => $list,
            'count' => $count
        );
        outJson(0, $return_data);
    }

}
