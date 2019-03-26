<?php

use think\Config;
use think\Session;
use think\Ase;
use think\Request;
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
// 应用公共文件
function encrypt($str) {
    return md5(Config::get("AUTH_CODE") . $str);
}

/**
 * 必须传递的参数
 * @param  array $data 必须要的数据
 * @param  array $req post传递的数据
 * @param  int $type 是否判断参数必须
 * @param  int $is_set 是否填充默认值
 * @return array
 */
function must_post($data, $req, $type = 0, $is_set = 0) {
    $_data = [];
    foreach ($data as $key => $value) {
        $v_type = $value[1];
        $v_type_list = explode("|", $v_type);
        $_min = 0;
        $_max = 0;
        $v_type_list_str = isset($v_type_list[0]) && !empty($v_type_list[0]) ? $v_type_list[0] : "string";
        if ($v_type_list_str == 'string' && isset($req[$key])) {
            $req[$key] = (string) $req[$key];
            if ($_min || $_max) {
                $_length = mb_strlen($req[$key], 'utf-8');
                if ($_length < $_min || $_length > $_max) {
                    if ($_max > $_min) {
                        outJson(101, $key . "只能传递长度在{$_min}与{$_max}之间的字符串");
                    } else {
                        outJson(101, $key . "只能传递长度为{$_min}的字符串");
                    }
                }
            }
        } elseif ($v_type_list_str == 'num' && isset($req[$key])) {
            if (!is_numeric($req[$key])) {
                outJson(100, $key . '数据格式错误');
            }
            if ($_min || $_max) {
                if ($req[$key] < $_min || $req[$key] > $_max) {
                    outJson(102, $key . "只能传递大小在{$_min}与{$_max}之间的数字");
                }
            }
        } elseif (isset($req[$key])) {
            outJson(98);
        }
        unset($v_type_list[0]);
        if ((!isset($req[$key])  || (empty($req[$key]))) && $value[0] == 1 && $type == 1) {
            outJson(99, $key . ' 为必传参数');
        }
        $d_key = isset($value[3]) && is_string($value[3]) ? $value[3] : $key;
        if (isset($req[$key])) {
            $_data[$d_key] = $req[$key];
        } elseif ($is_set) {
            if ($v_type == 'num') {
                $_data[$d_key] = 0;
            } else {
                $_data[$d_key] = '';
            }
        }
    }
    return $_data;
}

/**
 * 打印
 * @param  int $err 错误ID
 * @param  array|string $data 传递数据
 * @return string
 */
function outJson($err, $data = null) {
//    header("Content-Type:application/json;charset=utf-8");
    header("Access-Control-Allow-Origin: *");
    $_err = "";
    if ($err) {
        $_err = errs_out($err);
        if ($data && is_string($data)) {
            $_err = $data;
        }
    }
    $json_data = array('time' => date('Y-m-d H:i:s'), 'error_id' => $err, 'error' => $_err, 'msg' => $data);
    echo json_encode($json_data);exit;
}

function errs_out($id) {
    $ERRS = [
        -1 => 'false',
        10 => '错误的地址或版本',
        11 => '方法不存在',
        12 => 'model不存在',
        15 => '文件丢失，请联系管理员',
        16 => '不存在的类，请联系管理员',
        20 => '已有相同用户名',
        21 => '已有相同集团',
        97 => '传递数据参数不正确',
        98 => '未知数据类型',
        99 => '为必传参数',
        100 => '传递数据格式错误',
        101 => '数据字符长度错误',
        102 => '数据大小错误',
        105 => 'TOKEN为空',
        106 => 'time为空',
        107 => '超时',
        108 => 'TOKEN验证失败',
        109 => '非法访问',
        112 => '保存失败',
        113 => '没有数据',
        114 => '数据更新失败',
        115 => '数据一致无需更新或数据主键有误',
        116 => '数据库操作错误',
        500 => '服务器内部错误',
        1001 => '未实名',
        1002 => '未绑卡',
        1003 => '未登陆',
        1101 => '数据验证失败',
        1102 => '已注册',
        1103 => '该手机号未注册',
        1104 => '您输入的手机号或密码不正确！',
        1105 => '验证码不正确',
        1106 => '短信验证码错误',
        1107 => '原密码错误',
        1108 => '两次密码不一致',
        1109 => '收货地址保存失败',
        1110 => '到货提醒每2分钟才可以添加一次！',
        1111 => '优惠券不可用',
        1112 => '新手机号和旧手机号一致，无需修改',
        1113 => '系统问题，请联系管理员！',
        1114 => '手机号不正确',
        1115 => '请确认商品购买数量',
        1116 => '短信验证码已失效',


    ];
    return isset($ERRS[$id]) ? $ERRS[$id] : '未指定错误信息!';
}


/**
 * @function    运单签收状态以及服务说明；
 */
function express_status($id) {
    $STATUS = [
        0 => '快件处于运输过程中',
        1 => '快件已由快递公司揽收',
        2 => '疑难快件',
        3 => '正常签收',
        4 => '货物退回发货人并签收',
        5 => '货物正在进行派件',
        6 => '货物正处于返回发货人的途中',

    ];
    return isset($STATUS[$id]) ? $STATUS[$id] : '未指定错误信息!';
}


/**
 * @function    快递公司编码；
 */
function express($express_name) {
    $NAME = [
        '邮政包裹'         => 'youzhengguonei',
        '平邮'             => 'youzhengguonei',
        '国际包裹'         => 'youzhengguoji',
        'EMS'              => 'ems',
        '北京EMS'          => 'bjemstckj',
        '顺丰'             => 'shunfeng',
        '申通'             => 'shentong',
        '圆通'             => 'yuantong',
        '中通'             => 'zhongtong',
        '汇通'             => 'huitongkuaidi',
        '韵达'             => 'yunda',
        '宅急送'           => 'zhaijisong',
        '天天'             => 'tiantian',
        '德邦'             => 'debangwuliu',
        '国通'             => 'guotongkuaidi',
        '增益'             => 'zengyisudi',
        '速尔'             => 'suer',
        '中铁物流'         => 'ztky',
        '中铁快运'         => 'zhongtiewuliu',
        '能达'             => 'ganzhongnengda',
        '优速'             => 'youshuwuliu',
        '全峰'             => 'quanfengkuaidi',
        '京东'             => 'jd',

    ];
    return isset($NAME[$express_name]) ? $NAME[$express_name] : '未指定错误信息!';
}



/**
 * @function    根据用户ID来得到Redis的KEY值；
 * @param       user_id:用户ID；
 * @return      True: string: Redis KEY值; False: 空字符串；
 */
function getRedisKey($user_id){
    if (empty($user_id)) {
        return '';
    }
    $redis_key  =   session::prefix() ."_" .md5( config::get('AUTH_CODE') . "_" . $user_id  );
    return $redis_key;
}

/**
 * @function    用户注册密码加密；
 * @param       pwd:用户密码；
 * @return      True: string: 加密后值; False: 空字符串；
 */
function getEncryptedPassword ($password){
    if (empty($password)) {
        return '';
    }
    $password           =   "YHX_!" . $password . "YHX_YZT";
    $encrypted_password =   md5($password);
    return $encrypted_password;
}

/**
 * @function    根据用户的手机号得到省份；
 * @param       phone:手机号
 * @return      True: string: 返回省份; False: 空字符串；
 */
function getProvinceByPhone ($phone, $code = "UTF-8") {
    try {
        $url    =   'https://tcc.taobao.com/cc/json/mobile_tel_segment.htm?tel=' . $phone;
        $ch     =   curl_init();
        // 2. 设置选项，包括URL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // 3. 执行并获取HTML文档内容
        $cur_str =   curl_exec($ch);
        if($cur_str === FALSE ){
            return "CURL Error:".curl_error($ch);
        }
        $encode =   mb_detect_encoding($cur_str, array("ASCII","UTF-8","GB2312","GBK","BIG5"));
        if($encode != $code) {
            $cur_str = mb_convert_encoding($cur_str, $code, $encode);
        }
        ///得到的数据进行处理；
        $cut_str_list1  =   explode('{', $cur_str);
        $cut_str_list2  =   explode('province:', $cut_str_list1[1]);
        $cut_str_list   =   explode(',', $cut_str_list2[1]);
        $cur_str        =   str_replace("'", "", $cut_str_list[0]);
        // 4. 释放curl句柄
        curl_close($ch);
    } catch (Exception $e) {
        $cur_str = "";
    }
    return $cur_str;
}
/**
 * 获取用户IP地址
 * @param bool $is_post 是否获取传递的
 * @return string
 */
function getIp($is_post = true)
{
    if (isset($_POST['_ip_']) && $is_post) {
        return $_POST['_ip_'];
    }
    $ip = '-';
    if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
        $ip = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
        $ip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
        $ip = getenv('REMOTE_ADDR');
    } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}
/**
 * 获取当前时间戳
 * @param int $type 0 = YmdHis，1 = 时间戳
 * @return int
 */
function getRequestTime($type = 0)
{
    $t = $_SERVER['REQUEST_TIME'];
    return $type == 0 ? date('Y-m-d H:i:s', $t) : $t;
}
/**
 * @function    验证请求签名是否合法,所有涉及到用户的查询都要验证签名
 * @param       user_id:用户ID；
 * @param       sign:签名；
 * @param       timestamp:时间戳；
 * @return      True: string: Redis KEY值; False: 空字符串；
 */
function checkSign($sign_param){
    if(empty($sign_param['token']) || empty($sign_param['user_id'])){
        outJson(99, 'token 或 user_id参数'.errs_out(99));
    }
    $user_id            =   $sign_param['user_id'];
    //根据user_id获取redis key
    $redis_user_key     =   getRedisKey($user_id);
    if($sign_param['token']!=$redis_user_key){
        outJson(108, errs_out(108));
    }


}

/**
 * 对象 转 数组
 *
 * @param object $obj 对象
 * @return array
 */
function object_to_array($obj) {
    $obj = (array)$obj;
    foreach ($obj as $k => $v) {
        if (gettype($v) == 'resource') {
            return;
        }
        if (gettype($v) == 'object' || gettype($v) == 'array') {
            $obj[$k] = (array)object_to_array($v);
        }
    }

    return $obj;
}

/**
 * @function 发送短信随机验证码，不是线上的都发送成6666；
 * @return int
 */
function get_rand_sms(){
    $request    =  new Request();
    $host_name  =   $request -> host();
    if ($host_name != config::get("API_URL_ONLINE")) {
        return 6666;
    }
    return rand(1000, 9999);
}
/**
 * 如果系统不存在file_get_contents函数则声明该函数
 *
 * @author  wj
 * @param   string  $file
 * @param   mix     $data
 * @return  int
 */
if (!function_exists('file_get_contents')) {
    function file_get_contents($filename, $incpath = false, $resource_context = null) {
        if (false === $fh = fopen($filename, 'rb', $incpath)) {
            user_error('file_get_contents() failed to open stream: No such file or directory',
                E_USER_WARNING);
            return false;
        }
        clearstatcache();
        if ($fsize = @filesize($filename)) {
            $data = fread($fh, $fsize);
        }
        else {
            $data = '';
            while (!feof($fh)) {
                $data .= fread($fh, 8192);
            }
        }
        fclose($fh);
        return $data;
    }
}
/**
 * 如果系统不存在file_put_contents函数则声明该函数
 *
 * @author  wj
 * @param   string  $file
 * @param   mix     $data
 * @return  int
 */
if (!function_exists('file_put_contents'))
{
    define('FILE_APPEND', 'FILE_APPEND');
    if (!defined('LOCK_EX'))
    {
        define('LOCK_EX', 'LOCK_EX');
    }

    function file_put_contents($file, $data, $flags = '')
    {
        $contents = (is_array($data)) ? implode('', $data) : $data;

        $mode = ($flags == 'FILE_APPEND') ? 'ab+' : 'wb';

        if (($fp = @fopen($file, $mode)) === false)
        {
            return false;
        }
        else
        {
            $bytes = fwrite($fp, $contents);
            fclose($fp);

            return $bytes;
        }
    }
}

/**
 * @return string 获取订单编号
 * @author zhult
 */
function getOrderNum(){
    return date('YmdHis').rand(1000000,9999999);
}

/**
 * @function html中,获取其中的img标签中的src地址
 * @param string $imgHtml
 * @return string src地址
 */
function getImgSrc($imgHtml) {
    preg_match("/src=([\"']*([^>\"']+)[\"'])/", $imgHtml, $match);
    return isset($match[2]) && !empty($match[2]) ? $match[2] : "/style/new/images/lipins.png";
}
