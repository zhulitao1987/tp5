<?php

namespace app\api\model;

use think\Exception;
use think\Model;
use think\Db;
use think\Log;
/**
 * 收获地址操作类
 *
 * @author zhult
 */
class Address extends Model {

    //收获地址ID
    public $address_id;
    //用户ID
    public $user_id;
    //是否默认地址,默认为否(0)
    public $is_default = 0;

    /**
     * @function 添加收获地址
     * @return boolean true|false
     */
    public function addAddress($params = array()) {
        return Db::name("user_address")->insert($params);
    }

    /**
     * @param array $params 更新的字段域
     * @param int $address_id 地址记录ID 
     * @return boolean true|false
     */
    public function updateAddress($params = array(),$user_id='') {
        $address_id =$params['address_id'];
        try{
            $result=Db::name("user_address")->where(['user_id'=>$user_id,'address_id'=>$address_id])->update($params);
        }catch (Exception $e){
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return $result;
    }

    /**
     * @function 删除收获地址
     * @return boolean true|false
     */
    public function deleteAddress() {
        if (empty($this->user_id) || empty($this->address_id)) {
            return -1;
        }
        try {
            $where_arr      =   ['user_id'=>$this->user_id, 'address_id'=>$this->address_id];
//            $find_result    =   Db::name('user_address')->where($where_arr)->find();
//            if (empty($find_result)){
//                return -1;
//            }
//            $is_default =   $find_result["is_default"];
            $result     =   Db::name('user_address')->where($where_arr)->delete();
//            if ($is_default == 1) {
//                $update_where   =   ["user_id" => $this->user_id];
//                $update_arr     =   ["is_default" => 1];
//                $update_result  =   Db::name("user_address")->where($update_where)->limit(1)->order("address_id","DESC")->update($update_arr);
//                $result         =   $update_result;
//            }
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return $result;
    }

    /**
     * @function 查询单条收获地址记录
     * @return array
     */
    public function selectOne($address_id) {
        return Db::name("user_address")->where("address_id", $address_id)->find();
    }

    /**
     * @function 查询多条收获地址记录
     * @return array
     */
    public function selectList() {
        if (empty($this->user_id)) {
            return -1;
        }
        try {
            if ($this->is_default == 2) {
                $where_arr  =   ['user_id'=>$this->user_id];
                $order_arr  =   ["is_default" => "DESC", "update_time" => "DESC"];
                $result     =   Db::name('user_address')->where($where_arr)->order($order_arr)->select();
            } else {
                $where_arr  =   ['user_id'=>$this->user_id, 'is_default'=>$this->is_default];
                $result         =   Db::name('user_address')->where($where_arr)->order("update_time", "DESC")->select();
            }
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return empty($result) ? -1 : $result;
    }

}
