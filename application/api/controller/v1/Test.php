<?php
namespace app\api\controller\v1;

use app\api\model\Users as UsersModel;
use think\cache\driver\Redis;
use think\Db;
use think\Cache;
use think\Ase;
use think\Controller;
use think\Session;
use think\Validate;

/**
 * Created by PhpStorm.
 * User: YHx
 * Date: 2018/6/8
 * Time: 16:58
 */

class Test extends Controller{

    protected $postRule = []; //需要传递的参数
    public function __construct() {
        $this->req = $_POST;
    }

    public function index(){
        //初始化redis服务
        $redis = new Redis();
        $redis_name = "miaosha";
        //获取一下redis里面已有的数量
        $num = 10;
        for ($i = 0; $i < 100; $i++) {
            $uid = rand(1000000, 9999999);
            $microtime = time();
            //如果当天人数小于10的时候，则加入这个队列
            $redis_name_count = $redis->lLen($redis_name);
            if ($redis_name_count < $num) {
                $haha = $uid . "%" . $microtime;
                $redis->lPush($redis_name, $haha);
                echo $uid . '秒杀成功';
                return;
            } else {
                //当天人数已经达到10人，返回秒杀结束
                echo "系统繁忙，请稍后再试";
            }
        }


        $result_length=$redis->lLen($redis_name);
        for ($j=0;$j<=$result_length;$j++){
            $user=$redis->rPop($redis_name);
            //判断这个值是否存在
            if(!$user || $user=='nil'){
                sleep(2);
                continue;
            }
            //切割出时间，用户id
            $user_arr=explode('%',$user);
            $insert_data=[
                'user_name'=>$user_arr[0],
                'timestamp'=>$user_arr[1],
            ];
//            var_dump($insert_data);
            $res_status=Db::name('user_test')->insert($insert_data);
            if(!$res_status){
                $redis->lPush($redis_name,$user);
            }
            sleep(2);
        }


    }
    /**
     * @function    用户注册；
     * @return \think\response\Json
     */
    /**
     * @function    用户注册；
     * @return \think\response\Json
     */
    public function register () {
        $this->postRule = [
            'phone'         =>  [1, 'string', '用户电话', 'phone', 1],
            'mail'          =>  [0, 'string', '邮箱', 'mail', 1],
            'pwd'           =>  [1, 'string', '密码', 'pwd', 1],
            'verify'        =>  [1, 'string', '验证码', 'verify', 1],
        ];
        /////过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $user_info  =   $get_data;
        ////数据验证；
        $rules      =   array(
            'phone'     =>  'require|max:11',
            'mail'      =>  'email'
        );
        $message    =   array(
            'phone.require'     =>  '名称必须填写',
            'phone.max'         =>  '名称最多不能超过2个字符',
            'mail.email'        =>  '邮箱格式错误'
        );
        $validate   =   new Validate($rules, $message);
        $data       =   [
            'phone'  => isset($user_info['phone']) ? $user_info['phone'] : '',
            'mail'   => isset($user_info['mail'])  ? $user_info['mail']  : '',
        ];
        if (!$validate->check($data)) {
            outJson(1101, $validate->getError());
        };
//        if(!captcha_check($user_info['verify'])){
//            outJson(1105, errs_out(1105));
//        };
        ////动态数据先查询是否注册过，之后再存入数据表；
        $user_model             =   new UsersModel();
        $redis_key_by_phone     =   getRedisKey($user_info['phone']);
        Db::startTrans();
        ///先查询是否已经注册过；
        $is_register_by_redis   =   session::get($redis_key_by_phone);
        if (!empty($is_register_by_redis)) {
            Db::rollback();
            outJson(1102, errs_out(1102));
        }
        $find_where             =   array( 'mobile' => $user_info['phone'] );
        $is_register_by_db      =   $user_model->selectOne($find_where);
        if (is_array($is_register_by_db) && !empty($is_register_by_db)) {
            Db::rollback();
            outJson(1102, errs_out(1102));
        }
        ///没有注册过的进行数据保存；
        $insert_data    =    array(
            'mobile'            =>  $user_info['phone'],
            'email'             =>  $user_info['mail'],
            'password'          =>  getEncryptedPassword($user_info['pwd']),
            'phone_province'    =>  getProvinceByPhone($user_info['phone'])
        );
        $ret = $user_model->save_user_info($insert_data);
        if ($ret < 0) {
            Db::rollback();
            outJson(112, errs_out(112));
        }
        ///////用户注册的基本信息已经存表，注册时如有活动优惠等信息发放 START ;
//        YZT_5b36240d26ce4c50ccee433001dabeca YZT_90cec12d72db006fcc5221836a39b320
        ///////用户注册的基本信息已经存表，注册时如有活动优惠等信息发放 END ;
        ////数据存入Redis;
        $insert_data['user_id'] =   $ret;
        $redis_key_by_user_id   =   getRedisKey($ret);
        $redis=new Redis();
        $redis->set($redis_key_by_user_id,$insert_data);
        $redis->expire($redis_key_by_user_id,100);
        $redis->set($redis_key_by_phone,$insert_data);
        $redis->expire($redis_key_by_phone,100);
//        session::set($redis_key_by_user_id,  $insert_data);
//        session::set($redis_key_by_phone,    $insert_data);
        Db::commit();
        ///返回值的数据输出；
        $return_array   =   array(
            "redis_key_by_user_id" => $redis_key_by_user_id,
            "redis_key_by_phone"   => $redis_key_by_phone
        );
        outJson(0, $return_array);

    }
    /**
     * @function    根据用户的某一信息搜索用户；
     */
    public function selectOne() {
//        UQlTuWOh56y9kjIaCVmOEA==
//        YZT_d1777dd078eec9589712951db9f1cf69
//        YZT_18fd6c9f8d63a5fd4614f7255a526187
        $this->postRule = [
            'user_id'   =>  [1, 'string', '用户ID',   'user_id',  1],
            'phone'     =>  [0, 'string', '用户电话', 'phone',    1],
            'mail'      =>  [0, 'string', '邮箱',     'mail',     1],
            'gender'    =>  [0, 'string', '性别',     'gender',   1],
            'sign'      =>  [1, 'string', '签名',     'sign',    ''],
            'timestamp' =>  [1, 'string', '时间戳',   'timestamp','']
        ];

        //过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $user_info  =   $get_data;
        //验证token
        checkSign($user_info);
        $where      =   [];
        if(isset($user_info['user_id']))   $where['user_id']  =    trim(Ase::decrypt($user_info['user_id']));
        if(isset($user_info['phone']))     $where['mobile']   =    $user_info['phone'];
        if(isset($user_info['mail']))      $where['email']    =    $user_info['mail'];
        if(isset($user_info['gender']))    $where['sex']      =    $user_info['gender'];
        $user_model = new UsersModel();
        $ret = $user_model->selectOne($where);
        $ret == -1 ? outJson(-1, "没有数据"): outJson(0, $ret);
    }


    public function getMic(){
        $this->postRule = [
            'user_id'       =>  [0, 'string', '用户ID', 'user_id', 'agargagwrhrhy3wh'],
            'phone'         =>  [0, 'string', '手机号', 'phone', 'fawgawggggggghahrah']
        ];
        //过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $user_id    =   isset($get_data['user_id']) ? $get_data['user_id'] : 0;
        $phone      =   isset($get_data['phone'])   ? $get_data['phone']   : 0;
        $redis=new Redis();
        if(!empty($user_id)){
            $use_info=$redis->get($user_id);
            outJson(0,$use_info);
        }

        if(!empty($phone)){
            $use_info=$redis->get($phone);
            outJson(0,$use_info);
        }
    }
    public function outRedis(){
        $this->postRule = [
            'user_id'         =>  [0, 'string', '用户ID', 'user_id', 1],
            'phone'         =>  [0, 'string', '手机号', 'phone', 1]
        ];
        //过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $user_id    =   isset($get_data['user_id']) ? $get_data['user_id'] : 0;
        $phone      =   isset($get_data['phone'])   ? $get_data['phone']   : 0;
        if(!empty($user_id)){
            outJson(0,getRedisKey($user_id));
        }

        if(!empty($phone)){
            outJson(0,getRedisKey($phone));
        }
    }


    public function getMicInfo(){
        outJson(-1,time());
        $this->postRule = [
            'key'         =>  [1, 'string', '用户电话', 'key', 1]
        ];
        //过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        $user_arr   =   Session::get($get_data['key']);
        outJson(0,$user_arr);
    }


    public function getProvinceArr(){
        $pro_arr=Db::name("region2")->where(['level'=>1])->select();
        foreach ($pro_arr as $key=>$value){
            $city_arr=Db::name("region2")->where(['level'=>2,'parent_id'=>$value['id']])->select();
            $pro_arr[$key]['city']=$city_arr;
            foreach ($city_arr as $k=>$v){
                $area_arr=Db::name("region2")->where(['level'=>3,'parent_id'=>$v['id']])->select();
                $pro_arr[$key]['city'][$k]['area']=$area_arr;
            }
        }
        $json_strings = json_encode($pro_arr);
        file_put_contents('getProCityArea.json',$json_strings);//写入
        outJson(0,$pro_arr);
    }

    /*
     * @param access_time首次访问json文件的时间
     * @return 省市区
     * */
    public function outProCityArea(){
        $this->postRule = [
            'access_time'   =>  [0, 'string', '时间',   'access_time',  '2018-02-10 12:12:12'],
        ];
        //过滤参数，查看是否传递必须的数据
        $get_data   =   must_post($this->postRule, $this->req, 1);
        //客户端获取文件的时间
        $access_time=   isset($get_data['access_time'])?$get_data['access_time']:'';
        $read=file_get_contents("getProCityArea.json");
        $proCityArea_arr=json_decode($read,true);
        $da=filemtime("getProCityArea.json");
        //修改时间
        $date_time=date("Y-m-d H:i:s",$da);

        if(empty($access_time) || $date_time>$access_time){
            outJson(0,$proCityArea_arr);
        }else{
            outJson(-101,'数据暂无更新');
        }
    }

    public function getCityArr($parent_id=19){
        $pro_arr=Db::name("region2")->where(['level'=>2,'parent_id'=>$parent_id])->select();
        outJson(0,$pro_arr);
    }

    public function getAreaArr($parent_id=236){
        $pro_arr=Db::name("region2")->where(['level'=>3,'parent_id'=>$parent_id])->select();
        outJson(0,$pro_arr);
    }

}