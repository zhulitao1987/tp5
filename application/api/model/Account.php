<?php

namespace app\api\model;

use think\Model;
use think\Db;

/**
 * 账户资金流水
 *
 * @author zhult
 */
class Account extends Model {

    public $log_id;
    public $user_id;
    public $order_sn;
    public $order_id;
    public $page = 0;
    public $page_size = 20;

    /**
     * @function 添加订单金额流水
     * @params array 插入的参数
     * @return Boolean true|false
     */
    public function addAccountLog($params = array()) {
        return Db::name("account_log")->insert($params);
    }

    /**
     * @function 查询订单金额单条记录
     * @params int $log_id 记录ID
     * @return array 订单金额单条记录
     */
    public function selectOne($log_id = 0) {
        return Db::name("account_log")->where("log_id", $log_id)->find();
    }

    /**
     * @function 查询订单金额多条记录
     * @return array 订单金额多条记录
     */
    public function selectList() {
        $params = array();
        if ($this->user_id) {
            $params['user_id'] = array('eq', $this->user_id);
        }
        $pageIndex = $this->page * $this->page_size;
        if (!empty($params)) {
            return Db::name("account_log")->where($params)->limit($pageIndex, $this->page_size)->select();
        }
        return Db::name("account_log")->limit($pageIndex, $this->page_size)->select();
    }

}
