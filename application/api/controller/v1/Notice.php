<?php

namespace app\api\controller\v1;

use app\api\model\Notice as NoticeModel;
use app\api\controller\Base;
use think\cache\driver\Redis;
/**
 * @function 通知类
 */
class Notice extends Base {

    /**
     * @function    到货提醒
     * @author      xuwb@ylfcf.com
     * @return      string msg
     */
    public function arrivalRemind(){
        $this->postRule = [
            'user_id'       =>  [1, 'string',  '用户ID',   'user_id',    '111'],
            'goods_id'      =>  [1, 'string',  '商品id',   'goods_id',   '1152'],
            'goods_name'    =>  [1, 'string', '商品名称', 'goods_name', '大碗'],
            'mobile'        =>  [0, 'string', '手机号码', 'mobile',     '13910001000'],
            'token'         =>  [1, 'string', 'TOKEN',    'token',      'YZT_46645879864864']
        ];
        $get_data = must_post($this->postRule, $this->req, 1);

        ///实例化
        $mobile         =   "";
        $redis_model    =   new Redis();
        $notice_model   =   new NoticeModel();

        ///验证TOKEN；
        $token_arr  =   array(
                            "user_id"   => $get_data["user_id"],
                            "token"     => $get_data["token"]
                        );
        checkSign($token_arr);

        ///判断用户是否登陆；
        $user_id            =   htmlspecialchars($get_data["user_id"], ENT_NOQUOTES);
        $redis_key_by_id    =   getRedisKey($user_id);
        $redis_result       =   $redis_model->get($redis_key_by_id);
        $redis_result       =   json_decode($redis_result, true);
        if (empty($redis_result)) {
            outJson(1003, errs_out(1003));
        }

        ///初始值赋值；
        $goods_id       =   htmlspecialchars($get_data["goods_id"], ENT_NOQUOTES);
        $goods_name     =   htmlspecialchars($get_data["goods_name"], ENT_NOQUOTES);

        ///判断2分钟之内是否有添加过到货提醒过；
        $is_out =   $redis_model->get(getRedisKey($user_id . "_arrival_remind"));
        if (!empty($is_out)) {
            outJson(1110, errs_out(1110));
        }

        ///判断手机号是否为空，若为空值则调取Redis里面的用户注册手机号；
        if (!isset($get_data["mobile"]) || empty($get_data["mobile"])) {
            $mobile =   $redis_result["mobile"];
        } else {
            $mobile =   htmlspecialchars($get_data["mobile"], ENT_NOQUOTES);
        }

        ///保存到货信息，先进行查找是否有提出过此申请，申请过更新时间，若无则添加记录；
        ///查找是否已经添加过记录；
        $find_data  =   array(
                                'user_id'           =>  $user_id,
                                'goods_id'          =>  $goods_id,
                                'mobile'            =>  $mobile
                            );
        $ret        =   $notice_model->selectOne($find_data);
        ///根据查出的结果进行相应存入或更新数据；
        $now_time   =   getRequestTime(1);
        if (empty($ret)) {
            $insert_arr =   array(
                                "user_id"       => $user_id,
                                "mobile"        => $mobile,
                                "goods_id"      => $goods_id,
                                "goods_name"    => $goods_name,
                                "add_time"      => $now_time,
                                "update_time"   => $now_time,
                            );
            $ret        =   $notice_model->save_info($insert_arr);
            if ($ret === false || $ret < 0) {
                outJson(112, errs_out(112));
            } else {
                $redis_model->set(getRedisKey($user_id . "_arrival_remind"), "JUST_WRITE");
                $redis_model->expire(getRedisKey($get_data['user_id'] . "_arrival_remind"), 120);
                outJson(0, "成功！");
            }
        } else {
            $update_arr =   ["update_time"  => $now_time];
            $update_id  =   ["id"  => $ret["id"] ];
            $ret        =   $notice_model->updateInfo($update_arr, $update_id);
            ///输出结果；
            if ($ret == -1) {
                outJson(100, errs_out(100));
            } else if ($ret > 0) {
                $redis_model->set(getRedisKey($user_id . "_arrival_remind"), "JUST_WRITE");
                $redis_model->expire(getRedisKey($user_id . "_arrival_remind"), 120);
                outJson(0, "成功！");
            } else if($ret === 0){
                outJson(115, errs_out(115));
            } else {
                outJson(116, $ret);
            }
        }
    }

}
