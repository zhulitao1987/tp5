<?php

namespace app\api\model;

use think\Model;
use think\Db;
use think\Log;
class Notice extends Model {

    /**
     * @param   $user_info提供的查询信息
     * @return  array     对应的信息
     */
    public function selectOne($where) {
        try {
            $result = Db::name('arrival_remind')->where($where)->find();
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return $result;
    }

    /**
     * @function    保存信息；
     * @param       $user_info用户信息；
     * @return      int|string|void
     */
    public function save_info($info) {
        if (empty($info)) {
            return -1;
        }
        try {
            $result =  Db::name('arrival_remind')->insertGetId($info);
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return empty($result) ? -1 : $result;
    }

    /**
     * @function 信息更改；
     * @param    $info array()
     * @param    $where array();
     */
    public function updateInfo ($info = array(), $where = array()) {
        if (empty($info) || empty($where)) {
            return -1;
        }
        try {
            $result = Db::name('arrival_remind')->where($where)->update($info);
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return $result;
    }

}
