<?php

namespace app\api\controller\v1;

use app\api\controller\Base;
use think\Config;

/**
 * @function 快递100接口封装
 *
 */
class Express extends Base
{

    /**
     * 实时查询快递动态信息
     * @return json data
     */

    public function selectExpressEveryTime()
    {
        $this->postRule = [
            'com'       => [1, 'string', '查询的快递公司的编码， 一律用小写字母', 'com', 1],
            'num'       => [1, 'string', '查询的快递单号， 单号的最大长度是32个字符', 'num', 1],
            'from'      => [0, 'string', '出发地城市', 'from', 1],
            'to'        => [0, 'string', '目的地城市，到达目的地后会加大监控频率', 'to', 1],
            'resultv2'  => [0, 'int', '开通行政区域解析功能', 'resultv2', 0],
        ];
        //过滤参数，查看是否传递必须的数据
        $get_data                         = must_post($this->postRule, $this->req, 1);
        $key                              = Config::get("EXPRESS_100_KEY"); //授权key
        $customer                         = Config::get("EXPRESS_100_CUSTOMER_ID"); //分配公司编号
        $get_data['key']                  = $key;
        $get_data['customer']             = $customer;
        $paramData['com']                 = $get_data['com'];
        $paramData['num']                 = $get_data['num'];
        //判断查询快递param参数是否填写出发地城市
        $paramData['from']                = !empty($get_data['from']) ? $get_data['from'] : "";
        //判断查询快递param参数是否填写目的地城市
        $paramData['to']                  = !empty($get_data['to']) ? $get_data['to'] : "";
        //判断查询快递param参数是否填写开通行政区域解析功能
        $paramData['resultv2']            = !empty($get_data['resultv2']) ? $get_data['resultv2'] : 0;
        $param                            = json_encode($paramData);
        $get_data['param']                = $param;
        $data                             = $this -> queryHttp($get_data);
        $data                             = json_decode($data,true);
        //实时物流接口成功返回数据状态200
        if( isset($data['status']) && $data['status'] == 200 )
        {
            $data['state_description']    = express_status($data['state']);//运单签收状态以及服务说明
            outJson(0, $data);
        //实时物流接口返回错误result = false
        } else if(isset($data['result']) && $data['result'] == false)
        {
            outJson(-1, $data['message']);
        } else {
            outJson(1113, errs_out(1113));
        }

//        echo MD5('{"com":"yuantong","num":"12345678","from":"","to":"","resultv2":0}tEstZNzAGDLi4742A2AC96C6A2CD4486A0278DBF4FFB7E51');exit;

    }

    /**
     * curl 参数请求
     * @return json data
     */

    public function queryHttp($get_data)
    {
        //参数设置
        $post_data = array();
        $post_data["customer"]       = $get_data['customer']; //customer 参数值，分配的公司编码
        $key                         = $get_data['key'] ; //设置参数key
        $url                         = Config::get("EXPRESS_URL"); //分配公司编号 //快递100请求接口Url
        $post_data["sign"]           = md5($get_data["param"].$key.$post_data["customer"]); //MD5 sign值
        $post_data["sign"]           = strtoupper($post_data["sign"]); //字母转大写
        $post_data["param"]          = $get_data['param']; //param 主要包括查询快递信息参数
        $o                           = "";
        foreach ($post_data as $k=>$v)
        {
            $o.=  "$k=".urlencode($v)."&";		//默认UTF-8编码格式
        }
        $post_data                   = substr($o,0,-1);
        $ch                          = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $result                      = curl_exec($ch);
        $data                        = str_replace("\"",'"',$result );
//        $data = json_decode($data,true);
        return $data;
    }

}
