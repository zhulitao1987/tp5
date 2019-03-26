<?php
namespace app\api\controller\v1;

use app\api\model\Users as UsersModel;
use think\Controller;
use think\cache\driver\Redis;
use think\Page;
use think\Session;
use think\Config;
use think\Validate;
use think\Db;
use think\Ase;
use think\captcha\Captcha;

class Users extends Controller{

    protected $postRule = []; //需要传递的参数
    public function __construct() {
        $this->req = $_POST;
    }
    /**
     * @function    用户注册第一步：验证短信验证码是否正确；
     * @author      xuwb@ylfcf.com
     * @return      string msg
     */
    public function regCheckSms(){
        $this->postRule = [
            'phone'         =>  [1, 'string', '用户电话',   'phone', ''],
            'sms'           =>  [1, 'string', '短信验证码', 'sms',   ''],
        ];

        ///过滤参数，查看是否传递必须的数据
        $get_data       =   must_post($this->postRule, $this->req, 1);

        ///初使值处理；
        $phone          =   isset($get_data['phone']) && !empty($get_data['phone']) ? $get_data['phone']    : outJson(100, errs_out(100));
        $sms_code       =   isset($get_data['sms']) && !empty($get_data['sms'])     ? $get_data['sms']      : outJson(100, errs_out(100));

        ///数据验证；
        $rules      =   array( 'phone'  =>  'require|length:11|regex:/^1[345789]\d{9}$/ims' );
        $message    =   array(
                            'phone.require'     =>  '请输入正确的手机号',
                            'phone.length'      =>  '请输入正确的手机号',
                        );
        $validate   =   new Validate($rules, $message);
        $data       =   [ 'phone'  => $phone ];
        if (!$validate->check($data)) {
            outJson(1101, $validate->getError());
        }

        ///根据手机号码来查询Redis中是否有手机验证码；
        $redis_key      =   getRedisKey($phone . "1");
        $redis          =   new Redis();
        $redis_result   =   $redis->get( $redis_key );
        if (empty($redis_result) || $sms_code != $redis_result) {
            outJson(1106, errs_out(1106));
        }
        outJson(0, "验证成功!");
    }

    /**
     * @function 注册发送短信验证码；
     * @param    phone: 手机号码； scene: 发送场景；
     * @param    场景备注：用户注册：1；用户登陆：8；
     */
    public function sms(){
        $this->postRule = [
            'phone'     =>  [1, 'string', '用户电话',   'phone', ''],
            'scene'     =>  [1, 'string', '发送场景',   'scene', ''],
        ];
        $get_data       =   must_post($this->postRule, $this->req, 1);

        ///实例化与初始值；
        $redis          =   new Redis();

        ///得到随机数；
        $rand_number    =   get_rand_sms();

        ///发送短信；


        ///Redis信息存储；
        $redis->set( getRedisKey( $get_data["phone"] . $get_data["scene"] ),       $rand_number );
        $redis->expire( getRedisKey( $get_data["phone"] . $get_data["scene"] ),    60 );

        ///短信发送成功保存进表；

        outJson(0, '发送成功！');
    }

    /**
     * @function    用户注册第二步验证密码和存表；
     * @return      True: json ；False: msg
     * @author      xuwb@ylfcf.com
     * @date        2018-06-29
     */
    public function register () {
        $this->postRule = [
            'phone'         =>  [1, 'string', '用户电话', 'phone', ''],
            'pwd'           =>  [1, 'string', '密码',     'pwd', ''],
        ];

        ///过滤参数，查看是否传递必须的数据
        $get_data       =   must_post($this->postRule, $this->req, 1);
        $user_info      =   $get_data;

        ///用户数据校验；
        $check_result   =    $this->checkRegInfo($user_info);
        if ($check_result !== true) {
            outJson(1101, $check_result);
        }

        ///动态数据先查询是否注册过，若没有注册过再存入数据表；
        $redis_model            =   new Redis();
        $user_model             =   new UsersModel();
        $redis_key_by_phone     =   getRedisKey($user_info['phone']);
        Db::startTrans();

        ///先查询Redis中是否有值；
        $result_by_phone        =   $redis_model->get($redis_key_by_phone);
        $is_register_by_redis   =   json_decode($result_by_phone, true);
        if (!empty($is_register_by_redis)) { ///有值退出；
            Db::rollback();
            outJson(1102, errs_out(1102));
        }

        ///Redis中没有值再去数据表中查询，若无则认为没有注册过。
        $find_where             =   array( 'mobile' => $user_info['phone'] );
        $is_register_by_db      =   $user_model->selectOne( $find_where );
        if (is_array($is_register_by_db) && !empty($is_register_by_db)) {
            Db::rollback();
            outJson(1102, errs_out(1102));
        }

        ///没有注册过，进行数据保存；
        $now_time       =   getRequestTime(1);
        $insert_data    =   array(
                                'mobile'            =>  $user_info['phone'],
                                'password'          =>  getEncryptedPassword($user_info['pwd']),
                                'phone_province'    =>  getProvinceByPhone($user_info['phone']),
                                'reg_time'          =>  $now_time,
                                'last_login'        =>  $now_time,
                                'last_ip'           =>  getIp()
                            );
        $ret = $user_model->save_user_info($insert_data);
        if ($ret === false || $ret < 0) {
            Db::rollback();
            outJson(112, errs_out(112));
        }

        ///数据存入Redis;
        $redis_model                =   new Redis();
        $redis_key_by_user_id       =   getRedisKey($ret);
        $insert_data['user_id']     =   $ret;
        unset($insert_data['phone_province']);
        unset($insert_data['reg_time']);
        unset($insert_data['last_login']);
        unset($insert_data['last_ip']);
        $redis_model->set($redis_key_by_user_id,  json_encode($insert_data));
        $redis_model->set($redis_key_by_phone,    json_encode($insert_data));

        ///用户注册的基本信息已经存表，注册时如有活动优惠等信息活动 START ;

        ///用户注册的基本信息已经存表，注册时如有活动优惠等信息活动 END ;
        Db::commit();

        ///返回值的数据输出；
        $return_array   =   array(
                                "token"     =>  $redis_key_by_user_id,
                                "user_id"   =>  $ret
                            );
        outJson(0, $return_array);
    }

    /**
     * @function    用户登录；
     * @return \think\response\Json
     */
    public function login () {
        header("Access-Control-Allow-Origin: *");
        $this->postRule = [
            'phone'         =>  [1, 'string', '用户电话', 'phone', 1],
            'pwd'           =>  [1, 'string', '密码', 'pwd', 1],
        ];
        //过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $user_info  =   $get_data;
        ////数据验证；
        $rules      =   array(
            'phone' =>  'require|max:11|regex:/^1[345789]\d{9}$/ims',
            'pwd'   =>  'require'
        );
        $message    =   array(
            'phone.require'     =>  '手机号不能为空',
            'phone.max'         =>  '手机号的长度为11个字符',
            'phone.regex'       =>  '手机号码格式不正确',
            'pwd.require'       =>  '密码不能为空'
        );
        $validate   =   new Validate($rules, $message);
        $data       =   [
            'phone' =>  $user_info['phone'],
            'pwd'   =>  $user_info['pwd']
        ];
        if (!$validate->check($data)) {
            outJson(-1,$validate->getError());
        };
        //实例化redis对象
        $redis=new Redis();
        //根据phone获取key
        $redis_key_by_phone     =   getRedisKey($data['phone']);
        //根据phone获取redis存储的用户信息
        $redis_user_info        =   json_decode($redis->get($redis_key_by_phone),true);
        //如果redis有效期已过
        if(empty($redis_user_info)){
            $where_data    =    [
                'mobile'  =>$user_info['phone'],
            ];
            $user_arr=Db::name('users')->where($where_data)->find();
            if(empty($user_arr)){
                outJson(1103,errs_out(1103));
            }
            if ($user_arr['password']!=getEncryptedPassword($user_info['pwd'])){
                outJson(1104,errs_out(1104));
            }
            $user_id    =   $user_arr['user_id'];
            ////数据存入数据库；
            $redis_key_by_user_id =   getRedisKey($user_id);
            $redis_key_by_phone   =   getRedisKey($user_arr['mobile']);
            $user_arr_show        =   [
                'user_id' =>$user_arr['user_id'],
                'mobile'  =>$user_arr['mobile'],
                'password'=>$user_arr['password']
            ];
            $redis->set($redis_key_by_phone,    json_encode($user_arr_show));
            $redis->set($redis_key_by_user_id,  json_encode($user_arr_show));
        }else{
            $user_id    =   $redis_user_info['user_id'];
            //根据userid获取key
            $redis_key_by_user_id   =   getRedisKey($user_id);
            //根据userid rediskey获取用户信息
            $redis_key_by_user_info =   json_decode($redis->get($redis_key_by_user_id), true);

            if($redis_user_info['password']!=getEncryptedPassword($user_info['pwd'])){
                outJson(1104,errs_out(1104));
            }
            $redis->set($redis_key_by_phone,    json_encode($redis_user_info));
            $redis->set($redis_key_by_user_id,  json_encode($redis_key_by_user_info));
        }

        $update_state    =   $this->updateLoginLog($user_id);
        if(!$update_state){
            outJson(112,errs_out(112));
        }
        $arr=[
            "token"     =>$redis_key_by_user_id,
            "user_id"   =>$user_id
        ];
        outJson(0,$arr);
    }

    /*
     * @author:yangyh
     * @date:20180706
     * @function:短信验证码快捷登录
     * */
    public function codeLogin(){
        header("Access-Control-Allow-Origin: *");
        $this->postRule = [
            'phone'         =>  [1, 'string', '用户电话', 'phone', 1],
            'code'          =>  [1, 'string', '验证码', 'code', 1],
        ];
        //过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $user_info  =   $get_data;
        ////数据验证；
        $rules      =   array(
            'phone' =>  'require|max:11|regex:/^1[345789]\d{9}$/ims',
            'code'  =>  'require'
        );
        $message    =   array(
            'phone.require'     =>  '手机号不能为空',
            'phone.max'         =>  '手机号的长度为11个字符',
            'phone.regex'       =>  '手机号码格式不正确',
            'code.require'      =>  '验证码不能为空'
        );
        $validate   =   new Validate($rules, $message);
        $data       =   [
            'phone' =>  $user_info['phone'],
            'code'  =>  $user_info['code']
        ];
        if (!$validate->check($data)) {
            outJson(-1,$validate->getError());
        };
        //手机号赋值
        $phone=$user_info['phone'];
        //验证码赋值
        $code =$user_info['code'];
        $redis=new Redis();
        //获取以phone.8设置的短信key,存入的值为code
        $phone_code =getRedisKey($get_data["phone"].'8');
        //判断短信验证码的key是否存在于redis里面
        if(!$redis->exists($phone_code)){
            outJson(1116,errs_out(1116));
        }
        $redis_code=$redis->get($phone_code);
        //判断redis的短信code和接收过来的code是否相等
        if($redis_code!=$code){
            outJson(1106,errs_out(1106));
        }
        //根据phone获取token
        $redis_phone=getRedisKey($phone);
        //判断token是否存在于redis里面
        if($redis->exists($redis_phone)){
            $user_info      =$redis->get($phone);
            $user_id        =$user_info['user_id'];
            $arr=[
                "user_id"   =>$user_id,
                "token"     =>getRedisKey($user_id)
            ];
        }else{
            $user_info=Db::name("users")->where(['mobile'=>$phone])->find();
            $user_id  =$user_info['user_id'];
            if(!isset($user_info) && empty($user_info)){
                outJson(1103,errs_out(1103));
            }
            $redis_key_by_user_id =   getRedisKey($user_id);
            $redis_key_by_phone   =   getRedisKey($user_info['mobile']);
            $user_arr_show        =   [
                'user_id' =>$user_id,
                'mobile'  =>$user_info['mobile'],
                'password'=>$user_info['password']
            ];
            $redis->set($redis_key_by_phone,    json_encode($user_arr_show));
            $redis->set($redis_key_by_user_id,  json_encode($user_arr_show));
            $arr=[
                'user_id'=>$user_id,
                'token'  =>$redis_key_by_user_id
            ];
        }
        $update_state    =   $this->updateLoginLog($user_id);
        if(!$update_state){
            outJson(112,errs_out(112));
        }
        outJson(0,$arr);
    }
     /**
      * @function    根据用户的某一信息搜索用户；
      * @author      xuwb@ylfcf.com
      * @return      array
     */
    public function selectOne() {
        $this->postRule = [
            'user_id'       =>  [1, 'string', '用户ID',   'user_id',  ''],
            'phone'         =>  [0, 'string', '用户电话', 'phone',    ''],
            'mail'          =>  [0, 'string', '邮箱',     'mail',     ''],
            'gender'        =>  [0, 'string', '性别',     'gender',   ''],
            'token'         =>  [1, 'string', 'token',    'token',    'YZT_233323392939393939392848568']
        ];

        ///过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $user_info  =   $get_data;

        ///验证TOKEN；
        $token_arr  =   array(
            "user_id"   => $get_data["user_id"],
            "token"     => $get_data["token"]
        );
        checkSign($token_arr);

        ///定义初始值；
        $where      =   [];
        if(isset($user_info['user_id']))   $where['user_id']  =    $user_info['user_id'];
        if(isset($user_info['phone']))     $where['mobile']   =    $user_info['phone'];
        if(isset($user_info['mail']))      $where['email']    =    $user_info['mail'];
        if(isset($user_info['gender']))    $where['sex']      =    $user_info['gender'];

        ///用户信息查询
        $user_model     =   new UsersModel();
        $ret            =   $user_model->selectOne($where);

        ///输出值判断；
        if ($ret === -1) { outJson(113, errs_out(113)); }
        if (is_array($ret) && $ret) {
            outJson(0, $ret);
        } else {
            outJson(116, $ret);
        }
    }

    /**
     * @function    根据用户的某些特征查询用户列表；
     * @author      xuwb@ylfcf.com
     * @return      array
     */
    public function selectList() {
        $this->postRule = [
            'user_id'       =>  [1, 'string', '用户ID',   'user_id',      1],
            'phone'         =>  [0, 'string', '用户电话', 'phone',        1],
            'mail'          =>  [0, 'string', '邮箱',     'mail',         1],
            'gender'        =>  [0, 'string', '性别',     'gender',       1],
            'page'          =>  [0, 'string', '页码',     'page',         0],
            'page_size'     =>  [0, 'string', '每页条数', 'page_size',    10],
            'order_column'  =>  [0, 'string', '排序字段', 'order_column',   'user_id'],
            'order_type'    =>  [0, 'string', '排序方式', 'order_type',     'DESC'],
            'token'         =>  [1, 'string', 'token',    'token',    'YZT_233323392939393939392848568']
        ];
        $get_data   =   must_post($this->postRule, $this->req, 1);

        ///验证TOKEN；
        $token_arr  =   array(
            "user_id"   => $get_data["user_id"],
            "token"     => $get_data["token"]
        );
        checkSign($token_arr);

        ///定义初始值；
        $where          =   [];
        $user_info      =   $get_data;
        $page           =   isset($user_info['page'])           ?   $user_info['page']           :   0;
        $page_size      =   isset($user_info['page_size'])      ?   $user_info['page_size']      :   10;
        $order_column   =   isset($user_info['order_column'])   ?   $user_info['order_column']   :   "user_id";
        $order_type     =   isset($user_info['order_type'])     ?   $user_info['order_type']     :   "DESC";
        if(isset($user_info['user_id']))    $where['user_id']   =   $user_info['user_id'];
        if(isset($user_info['phone']))      $where['mobile']    =   $user_info['phone'];
        if(isset($user_info['mail']))       $where['email']     =   $user_info['mail'];
        if(isset($user_info['gender']))     $where['sex']       =   $user_info['gender'];

        ///用户信息查询
        $user_model     =   new UsersModel();
        $ret            =   $user_model->selectList($where, $page, $page_size, $order_column, $order_type);

        ///输出结果
        if ($ret === -1) { outJson(113, errs_out(113)); }
        if (is_array($ret) && $ret) {
            outJson(0, $ret);
        } else {
            outJson(116, $ret);
        }
    }

    /**
     * @function    修改用户信息；
     * @author      xuwb@ylfcf.com
     * @return      string msg
     */
    public function updateUserInfo(){
        $this->postRule = [
            'user_id'   =>  [1, 'string', '用户ID',     'user_id',  1],
            'phone'     =>  [0, 'string', '联系方式',   'phone',    '18506526315'],
            'mail'      =>  [0, 'string', '邮箱',       'mail',     '1@1.com'],
            'token'     =>  [1, 'string', 'token',      'token',    'YZT_233323392939393939392848568']
        ];
        $get_data   =   must_post($this->postRule, $this->req, 1);

        ///验证TOKEN；
        $token_arr  =   array(
                            "user_id"   => $get_data["user_id"],
                            "token"     => $get_data["token"]
                        );
        checkSign($token_arr);

        ///需要更新的数据处理；
        $update_arr     =   array();
        if(isset($get_data['phone']))     $update_arr['mobile']   =    $get_data['phone'];
        if(isset($get_data['mail']))      $update_arr['email']    =    $get_data['mail'];

        ///更新动作处理；
        $user_model     =   new UsersModel();
        $ret            =   $user_model->updateInfo($update_arr, array("user_id" => $get_data['user_id']));

        ///输出结果；
        if ($ret == -1) {
            outJson(100, errs_out(100));
        } else if ($ret > 0) {
            outJson(0, "数据更新成功！");
        } else if($ret === 0){
            outJson(115, errs_out(115));
        } else {
            outJson(116, $ret);
        }
    }

    /**
     * @function    登出;
     * @author      xuwb@ylfcf.com
     * @return      string msg
     */
    public function logout(){
        $this->postRule = [
            'user_id'   =>  [1, 'string', '用户ID',   'user_id',       1],
            'token'     =>  [1, 'string', 'token',    'token',    'YZT_233323392939393939392848568']
        ];
        $get_data   =   must_post($this->postRule, $this->req, 1);

        ///TOKEN 验证；
        $user_info  =   $get_data;
        $token_arr  =   array(
                            "user_id"   => $user_info["user_id"],
                            "token"     => $user_info["token"]
                        );
        checkSign($token_arr);

        ///得到Redis的值；
        $redis_model        =   new Redis();
        $user_id            =   $user_info['user_id'];
        $redis_key_by_id    =   getRedisKey($user_id);
        $result_by_id       =   $redis_model->get($redis_key_by_id);
        $result_by_id       =   json_decode($result_by_id, true);
        if (empty($result_by_id)) {
            outJson(100, errs_out(100));
        }

        ///根据redis的值来得到phone的redis值；
        $user_phone         =   $result_by_id["mobile"];
        $redis_key_by_phone =   getRedisKey($user_phone);
        $result_by_phone    =   $redis_model->get($redis_key_by_phone);
        $redis_by_phone     =   json_decode($result_by_phone, true);
        if (empty($redis_by_phone)) {
            outJson(100, errs_out(100));
        }

        ///删除redis值；
        session::delete($redis_key_by_id);
        session::delete($redis_key_by_phone);
        outJson(0, "退出成功");
    }

    /**
     * @function    数据校验 (注册)
     * @author      xuwb@ylfcf.com
     * @param       $user_info
     * @return      array|bool
     */
    public function checkRegInfo( $user_info ){
        ///数据验证；
        $rules      =   array(
            'phone'     =>  'require|length:11|regex:/^1[345789]\d{9}$/ims',
            'pwd'       =>  'require|min:6'
        );
        $message    =   array(
            'phone.require'     =>  '手机号码必须填写',
            'phone.length'      =>  '手机号码长度为11个字符',
            'phone.regex'       =>  '手机号码格式不正确',
            'pwd.require'       =>  '密码必须填写',
            'pwd.min'           =>  '密码长度必须超过6位',
        );
        $validate   =   new Validate($rules, $message);
        $data       =   [
                            'phone'  => isset($user_info['phone'])  ?   $user_info['phone'] :   '',
                            'pwd'    => isset($user_info['pwd'])    ?   $user_info['pwd']   :   '',
                        ];
        if (!$validate->check($data)) {
            return  $validate->getError();
        } else {
            return true;
        }
    }

    /**
     * @function    修改登陆密码
     * @author      xuwb@ylfcf.com
     * @return      string  msg
     */
    public function amendLoginPassword(){
        $this->postRule = [
            'user_id'         =>  [1, 'string',    '用户ID',       'user_id',           1],
            'old_password'    =>  [1, 'string', '旧密码',       'old_password',      '123456'],
            'new_password'    =>  [1, 'string', '新密码',       'new_password',      '654321'],
            'new_re_password' =>  [1, 'string', '确认新密码',   'new_re_password',   '654321'],
            'token'           =>  [1, 'string', 'TOKEN',        'token',             'YZT_233323392939393939392848568']
        ];
        $get_data   =   must_post($this->postRule, $this->req, 1);

        ///TOKEN验证；
        $user_info  =   $get_data;
        $token_arr  =   array(
            "user_id"   => $user_info["user_id"],
            "token"     => $user_info["token"]
        );
        checkSign($token_arr);

        ///实例化操作；
        $redis_model        =   new Redis();
        $user_model         =   new UsersModel();

        ///先验证旧密码是否正确；
        $redis_key_by_id    =   getRedisKey($user_info['user_id']);
        $redis_result       =   $redis_model->get($redis_key_by_id);
        $redis_result       =   json_decode($redis_result, true);
        if (empty($redis_result)) {
            outJson(1003, errs_out(1003));
        }
        if (getEncryptedPassword($user_info['old_password']) != $redis_result['password']) {
            outJson(1107, errs_out(1107));
        }

        ////数据验证；
        $rules      =   array(
                            'old_password'              =>  'require|min:6',
                            'new_password'              =>  'require|min:6',
                            'new_re_password'           =>  'require|min:6|confirm:new_password'
                        );
        $message    =   array(
                            'old_password.require'      =>  '原始密码必须填写',
                            'old_password.min'          =>  '密码长度必须超过6位',
                            'new_password.min'          =>  '密码长度必须超过6位',
                            'new_re_password.min'       =>  '密码长度必须超过6位',
                            'new_re_password.confirm'   =>  '两次密码不一致',
                            'new_re_password.require'   =>  '确认新密码必须填写',
                            'new_password.require'      =>  '新密码必须填写',
                        );
        $validate   =   new Validate($rules, $message);
        $data       =   [   'old_password'      =>  $user_info['old_password'],
                            'new_password'      =>  $user_info['new_password'],
                            'new_re_password'   =>  $user_info['new_re_password'],
                        ];
        if (!$validate->check($data)) {
            outJson(1101, $validate->getError());
        }

        ///数据存入Redis和存表；
        $new_update_password        =   getEncryptedPassword($user_info['new_password']);
        $update_arr                 =  ["password" => $new_update_password];
        $redis_result['password']   =   $new_update_password;
        $ret                        =   $user_model->updateInfo($update_arr, array("user_id" => $user_info['user_id']));
        $redis_key_by_phone         =   getRedisKey($redis_result['mobile']);

        ///输出结果；
        if ($ret == -1) {
            outJson(100, errs_out(100));
        } else if ($ret > 0) {
            $redis_model->set($redis_key_by_id,      json_encode($redis_result));
            $redis_model->set($redis_key_by_phone,   json_encode($redis_result));
            outJson(0, "数据更新成功！");
        } else if($ret === 0){
            outJson(115, errs_out(115));
        } else {
            outJson(116, $ret);
        }
    }


    /**
     * @function    更改手机号_验证旧手机号
     * @author      xuwb@ylfcf.com
     * @return      True | False
     */
    public function VerifyOldMobile(){
        $this->postRule = [
            'user_id'         =>  [1, 'string', '用户ID',       'user_id',       1],
            'old_phone'       =>  [1, 'string', '旧手机号码',   'old_phone',     '13615263654'],
            'verify_number'   =>  [1, 'string', '短信验证码',   'verify_number', '654321'],
            'token'           =>  [1, 'string', 'TOKEN',        'token',         'YZT_233323392939393939392848568']
        ];
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $redis      =   new Redis();
        ///TOKEN验证；
        $token_arr  =   array(
            "user_id"   => $get_data["user_id"],
            "token"     => $get_data["token"]
        );
        checkSign($token_arr);

        ///验证输入的手机号和存在Redis里面的手机号是否一致；
        $redis_key_by_id    =   getRedisKey($get_data["user_id"]);
        $redis_id_result    =   json_decode($redis->get($redis_key_by_id), true);
        if ($redis_id_result["mobile"] != $get_data["old_phone"]) {
            outJson(1103, errs_out(1103));
        }

        ///验证短信内容是否正确；
        $redis_key      =   getRedisKey($get_data["old_phone"] . "9");
        $redis_result   =   $redis->get($redis_key);
        if (empty($redis_result) || $get_data["verify_number"] != $redis_result) {
            outJson(-1, errs_out(-1));
        }
        outJson(0, true);
    }

    /**
     * @function    更改手机号_验证新手机号并入库；
     * @author      xuwb@ylfcf.com
     */
    public function VerifyNewMobile(){
        $this->postRule = [
            'user_id'         =>  [1, 'string', '用户ID',       'user_id',       1],
            'new_phone'       =>  [1, 'string', '新手机号码',   'new_phone',     '13615263654'],
            'verify_number'   =>  [1, 'string', '短信验证码',   'verify_number', '654321'],
            'token'           =>  [1, 'string', 'TOKEN',        'token',         'YZT_233323392939393939392848568']
        ];
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $phone      =   $get_data["new_phone"];
        $user_id    =   $get_data["user_id"];
        $redis      =   new Redis();
        $user_model =   new UsersModel();

        ///TOKEN验证；
        $token_arr  =   array(
            "user_id"   => $user_id,
            "token"     => $get_data["token"]
        );
        checkSign($token_arr);

        ///验证手机号码是否正确；
        $rules      =   array( 'phone'  =>  'require|length:11|regex:/^1[345789]\d{9}$/ims' );
        $message    =   array(
                                'phone.require'     =>  '请输入正确的手机号',
                                'phone.length'      =>  '请输入正确的手机号',
                            );
        $validate   =   new Validate($rules, $message);
        $data       =   [ 'phone' => $phone ];
        if (!$validate->check($data)) {
            outJson(1101, $validate->getError());
        }

        ///得到旧手机号和Redis的值；
        $redis_key_by_id            =   getRedisKey($user_id);
        $redis_key_result           =   json_decode($redis->get($redis_key_by_id), true);
        $old_phone                  =   "";
        if (isset($redis_key_result["mobile"])){
            $old_phone  =   $redis_key_result["mobile"];
        } else {
            outJson(1003, errs_out(1003));
        }
        $redis_key_result["mobile"] =   $phone;

        ///进行旧手机号和新手机号进行匹配是否一致；
        if ($old_phone == $phone) {
            outJson(1111, errs_out(1111));
        }

        ///验证短信内容是否正确；
        $redis_key      =   getRedisKey($phone . "10");
        $redis_result   =   json_decode($redis->get($redis_key), true);
        if (empty($redis_result) || $get_data["verify_number"] != $redis_result) {
            outJson(1106, errs_out(1106));
        }

        ///对修改的数据更新进表；
        $update_arr     =   ["mobile"  => $phone];
        $user_result    =   $user_model->updateInfo($update_arr, array("user_id" => $user_id));

        ///输出结果；
        if ($user_result == -1) {
            outJson(100, errs_out(100));
        } else if ($user_result > 0) {
            ///****************对Redis值进行更新；
            ///分别得到旧手机号和新手机号redis的KEY值；
            $redis_key_by_old_phone     =   getRedisKey($old_phone);
            $redis_key_by_new_phone     =   getRedisKey($phone);
            ///删除旧redis里面的值；
            session::delete($redis_key_by_id);
            session::delete($redis_key_by_old_phone);
            ///创建新手机号的redis的值；
            $redis_value    =   json_encode($redis_key_result);
            $redis->set($redis_key_by_id,           $redis_value);
            $redis->set($redis_key_by_new_phone,    $redis_value);
            outJson(0, "数据更新成功！");
        } else if($user_result === 0){
            outJson(115, errs_out(115));
        } else {
            outJson(116, $user_result);
        }
    }

    /*
     * @function:更新登录信息
     * @author:yangyh
     * @date:20180706
     * */
    public function updateLoginLog($user_id){
        $update_field_arr=[
            'last_login' =>  time(),
            'last_ip'    =>  getIp(),
            'is_lock'    =>  0
        ];
        $user_model      =   new UsersModel();
        $update_state    =   $user_model->updateInfo($update_field_arr,array("user_id" => $user_id));
        return $update_state;
    }
}