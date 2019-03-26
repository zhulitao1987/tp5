<?php

namespace app\api\controller\v1;

use app\api\controller\Base;
use app\api\model\Coupon as CouponModel;

/**
 * @function 优惠券类
 *
 * @author zhult
 */
class Coupon extends Base {

    /**
     * @function 修改优惠券
     * @author zhult
     */
    public function updateCouponInfo() {
        $this->postRule = [
            'user_id' => [1, 'num', '用户id', 'user_id', 0],
            'token' => [1, 'string', 'token字段', 'token', ""],
            'coupon_id' => [1, 'num', '优惠券id', 'coupon_id', 1],
            'order_id' => [1, 'num', '对应的订单记录id', 'order_id', 1],
            'get_order_id' => [0, 'num', '来自的订单记录id', 'get_order_id', 1],
            'use_time' => [0, 'string', '使用时间（时间戳）', 'use_time', getRequestTime(1)],
            'status' => [0, 'num', '优惠券状态（0 未使用；1 已使用；2 已过期）', 'status', 0],
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $coupon_id = $params['coupon_id'];
        //TOKEN验证
        $token_arr  =   [
                            "user_id"   => $params["user_id"],
                            "token"     => $params["token"]
                        ];
        checkSign($token_arr);
        $CouponModel = new CouponModel();
        unset($params['user_id']);
        unset($params['token']);
        unset($params['coupon_id']);
        $ret = $CouponModel->updateCouponInfo($params, $coupon_id);
        if (empty($ret)) {
            outJson(114, errs_out(114));
        }
        outJson(0, "更新成功");
    }

    /**
     * @function 查询单条优惠券信息
     * @author zhult
     */
    public function selectOne() {
        $this->postRule = [
            'coupon_id' => [1, 'num', '优惠券id', 'coupon_id', 1],
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $coupon_id = $params['coupon_id'];
        $CouponModel = new CouponModel();
        $return = $CouponModel->selectOne($coupon_id);
        if (empty($return)) {
            outJson(113, errs_out(113));
        }
        outJson(0, $return);
    }

    /**
     * @function 查询优惠券列表
     * @author zhult
     */
    public function selectList() {
        $this->postRule = [
            'user_id' => [1, 'num', '用户id', 'user_id', 0],
            'token' => [1, 'string', 'token字段', 'token', ""],
            'page' => [0, 'num', '分页第?页', 'page', 0],
            'page_size'   => [0, 'num', '每页记录数', 'page_size', 20],
            'coupon_status'   => [0, 'num', '优惠券状态（0 未使用；1 已使用；2 已过期）', 'coupon_status', ""]
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $timestamp = getRequestTime(1);
        $CouponModel = new CouponModel();
        if (isset($params['user_id'])) {
            $CouponModel->uid = $params['user_id'];
        }
        if (isset($params['page'])) {
            $CouponModel->page = $params['page'];
        }
        if (isset($params['page_size'])) {
            $CouponModel->page_size = $params['page_size'];
        }
        if (isset($params['coupon_status']) && $params['coupon_status'] != 2) {
            $CouponModel->status = $params['coupon_status'];
        } 
        if (isset($params['coupon_status']) && $params['coupon_status'] == 2) {
            $CouponModel->valid_time = $timestamp;
        }
        //TOKEN验证
        $token_arr  =   [
                            "user_id"   => $params["user_id"],
                            "token"     => $params["token"]
                        ];
        checkSign($token_arr);
        $list = $CouponModel->selectList();
        if (empty($list)) {
            outJson(113, errs_out(113));
        }
        outJson(0, $list);
        
    }

}
