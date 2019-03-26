<?php

namespace app\api\model;

use think\Model;
use think\session\driver\Redis;
use think\Db;
use think\Log;
/**
 * 平台用户信息处理类
 *
 * @author zhult
 */
class Users extends Model {

    /**
     * @param   $user_info  用户提供的查询信息array()
     * @param   $column     排序的字段；
     * @param   $order      排序方式；
     * @return  array       对应的用户信息
     */
    public function selectOne($user_info = array(), $column = "user_id", $order = "DESC") {
        try {
            $result = Db::name('users')->where($user_info)->order($column, $order)->find();
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return empty($result) ? -1 : $result;
    }

    /**
     * @function    保存用户信息；
     * @param       $user_info用户信息；
     * @return      int|string|void
     */
    public function save_user_info($user_info) {
        if (empty($user_info)) {
            return -1;
        }
        try {
            $result =  Db::name('users')->insertGetId($user_info);
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return empty($result) ? -1 : $result;
    }

    /**
     * @param $user_info用户提供的查询信息array()
     * @param $column   排序的字段；
     * @param $order    排序方式；
     * @return array    对应的用户信息
     */
    public function selectList( $user_info = array(), $page = 0, $page_size = 20, $column = "user_id", $order = "DESC"  ) {
        try {
            $result = Db::name('users')->where($user_info)->order($column, $order)
                ->limit($page * $page_size, $page_size)->select();
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return empty($result) ? -1 : $result;
    }

    /**
     * @function 用户信息更改；
     * @param    $user_info array()
     * @param    $user_id 用户Id
     */
    public function updateInfo ($user_info = array(), $user_where = array()) {
        if (empty($user_info) || empty($user_where)) {
            return -1;
        }
        try {
            $result = Db::name('users')->where($user_where)->update($user_info);
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return $result;
    }
}
