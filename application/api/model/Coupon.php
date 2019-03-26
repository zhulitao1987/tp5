<?php

namespace app\api\model;

use think\Model;
use think\Db;

/**
 * 优惠券操作类
 *
 * @author zhult
 */
class Coupon extends Model {

    //优惠券记录ID
    public $id;
    //优惠券ID
    public $cid;
    //优惠券类型，发放类型 1 按订单发放 2 注册 3 邀请 4 按用户发放
    public $type;
    //用户ID
    public $uid;
    //优惠券状态
    public $status = 1;
    //有效时间(时间戳)
    public $valid_time;
    //分页
    public $page = 0;
    public $page_size = 20;

    /**
     * @param array $params 更新的字段域
     * @param int $cid 优惠券ID 
     * @return boolean true|false
     */
    public function updateCouponInfo($params = array(), $cid = 0) {
        return Db::name('coupon_list')->where('id', $cid)->update($params);
    }

    /**
     * @function 根据优惠券id查询单条记录
     * @param int $id 
     * @return array 优惠券单条记录
     */
    public function selectOne($id) {
        return Db::name("coupon_list")->where("id", $id)->find();
    }
    
    /**
     * @function 根据优惠券原始ID，查询优势信息
     * @param int $coupon_origin_id 
     * @return array 原始优惠券单条记录
     */
    public function selectCouponOriInfo($coupon_origin_id = 0) {
        return Db::name("coupon")->where("id", $coupon_origin_id)->find();
    }

    /**
     * @function 根据优惠券id查询单条记录
     * @return array 优惠券单条记录
     */
    public function selectList() {
        $sql = "SELECT "
                . "A.*,"
                . "B.`name`,"
                . "B.type,"
                . "B.money,"
                . "B.`condition`,"
                . "B.createnum,"
                . "B.send_num,"
                . "B.use_num,"
                . "B.send_start_time,"
                . "B.send_end_time,"
                . "B.use_start_time,"
                . "B.use_end_time "
                . "FROM yzt_coupon_list AS A "
                . "LEFT JOIN yzt_coupon AS B "
                . "ON A.cid = B.id "
                . "WHERE A.`status` = $this->status ";
        if ($this->uid) {
            $sql .= "AND A.uid = $this->uid ";
        }
        if ($this->cid) {
            $sql .= "AND A.cid = $this->cid ";
        }
        if ($this->type) {
            $sql .= "AND A.type = $this->type ";
        }
        if ($this->valid_time) {
            $sql .= "AND ($this->valid_time <= B.use_start_time "
                    . "OR $this->valid_time >= B.use_end_time) ";
        }
        $pageIndex = $this->page * $this->page_size;
        $sql .= "LIMIT $pageIndex,$this->page_size";
        $return = Db::name("coupon_list")->query($sql);
        return $return;
    }

}
