<?php

namespace app\api\controller\v1;

use app\api\model\Address as AddressModel;
use app\api\controller\Base;
use think\Db;
use think\Validate;

/**
 * 地址API控制层
 *
 * @author zhult
 */
class Address extends Base {
    

    /*
     * @param access_time首次访问json文件的时间
     * @return 省市区数据
     * @author:yangyh
     * @date:20180627
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
    /**
     * @function 更新收货地址
     * @author yyh
     * @date：20180626
     */
    public function saveAddress(){
        $this->postRule = [
            'token'     => [1, 'string', '签名', 'token', 'YZT_6861596d77be9f69e5fec5245f4c5dcb'],
            'user_id'   => [1, 'num', '用户ID', 'user_id', 6],
            'address_id'=> [0, 'num', '收货地址ID', 'address_id', 6],
            'consignee' => [1,'string','收货人','consignee','张三'],
            'province'  => [1,'string','省份','province','湖北省'],
            'city'      => [1,'string','城市','city','宜昌市'],
            'district'  => [1,'string','地区','district','伍家岗区'],
            'town'      => [0,'string','乡镇','town','尚武街'],
            'address'   => [1,'string','地址','address','666号12楼1201室'],
            'zipcode'   => [0,'string','邮政编码','zipcode','0200120'],
            'mobile'    => [1,'string','收货人手机号','mobile','18815465366'],
            'is_default'=> [0,'num','是否设置为默认收货地址','is_default','0'],
            'is_pickup' => [0,'num','是否自提点','is_pickup','0'],
        ];
        $params = must_post($this->postRule, $this->req, 1);
        $params['town']      =isset($params['town'])        ?   $params['town']        :    0;
        $params['zipcode']   =isset($params['zipcode'])     ?   $params['zipcode']     :    0;
        $params['is_default']=isset($params['is_default'])  ?   $params['is_default']  :    0;
        $params['is_pickup'] =isset($params['is_pickup'])   ?   $params['is_pickup']   :    0;
        $user_id             =$params['user_id'];
        $address_id          =isset($params['address_id'])  ?   $params['address_id']  :0;
        $check_arr=[
            'user_id'=>$user_id,
            'token'  =>$params['token']
        ];
        //验证是否是正常访问
        checkSign($check_arr);
        //验证mobile格式是否正确
        $check_phone_status=self::checkPhone($params['mobile']);
        if(!$check_phone_status){
            outJson(-1,$check_phone_status);
        }
        //实例化地址model类
        $addressModel=new AddressModel();
        $addressModel->user_id      =$user_id;
        $addressModel->is_default   =1;
        //查询是否有默认的收货地址
        $address_default_info=$addressModel->selectList();
        $address_default_info=$address_default_info==-1 ? '' : $address_default_info[0];
        Db::startTrans();
        if($address_id==0){
            $condition=$params["is_default"]==1 && !empty($address_default_info);
        }else{
            //如果存在默认收货地址  查询的address的ID不等于正在更改的ID
            $condition=$params["is_default"]==1 && !empty($address_default_info) && $address_default_info["address_id"]!=$address_id;
        }
        //如果存在默认收货地址
        if($condition){
            //更新此默认收货的值为0
            $address_default_info['is_default']=0;
            $update_state=$addressModel->updateAddress($address_default_info,$user_id);
            if(!$update_state){
                Db::rollback();
                outJson(1109,errs_out(1109));
            }
        }
        if($address_id==0){
            //执行新增操作
            unset($params['token']);
            $params['add_time']   =getRequestTime(1);
            $params['update_time']=getRequestTime(1);
            $add_state=$addressModel->addAddress($params);
        }else{
            unset($params['token']);
            $params['update_time']=getRequestTime(1);
            $add_state=$addressModel->updateAddress($params,$user_id);
        }
        if(!$add_state){
            Db::rollback();
            outJson(1109,errs_out(1109));
        }
        Db::commit();
        outJson(0,'保存成功！');

    }

    
    /**
     * @function 单条地址详情
     * @author zhult 
     */
    public function selectOne() {
        $this->postRule = [
            'address_id' => [1, 'num', '地址id', 'address_id', 1],
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $address_id = $params['address_id'];
        $AddressModel = new AddressModel();
        $addressInfo = $AddressModel->selectOne($address_id);
        if (empty($addressInfo)) {
            outJson(113, errs_out(113));
        }
        outJson(0, $addressInfo);
    }

    /**
     * @function 查询收货地址列表
     */
    public function selectList() {
        $this->postRule = [
            'user_id'       =>  [1, 'num',      '用户id',            'user_id',   1],
            'is_default'    =>  [0, 'string',   '是否默认收货地址0:除了默认的，1：默认的，2：全部',  'is_default', "0"],
            'token'         =>  [1, 'string',   'TOKEN',             'token',      'YZT_565465468']
        ];
        //过滤参数，查看是否传递必须的数据
        $params         =   must_post($this->postRule, $this->req, 1);
        ///赋初始值与实例化；
        $AddressModel   =   new AddressModel();
        $AddressModel->is_default   =   isset($params["is_default"]) ? $params["is_default"] : 2;
        $AddressModel->user_id      =   $params["user_id"];
        ///TOKEN验证
        $token_arr  =   [
                            "user_id"   => $params["user_id"],
                            "token"     => $params["token"]
                        ];
        checkSign($token_arr);
        $addressList = $AddressModel->selectList();
        if ($addressList === -1) { outJson(113, errs_out(113)); }
        if (is_array($addressList) && $addressList) {
            outJson(0, $addressList);
        } else {
            outJson(116, $addressList);
        }
    }


    /**
     * @function    删除收货地址
     */
    public function deleteShipAddress()
    {
        $this->postRule = [
            'user_id' => [1, 'num', '用户id', 'user_id', 1],
            'address_id' => [1, 'num', '收货地址ID', 'address_id', 1526],
            'token' => [1, 'string', 'TOKEN', 'token', 'YZT_565465468']
        ];
        ///过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        ///赋初始值与实例化；
        $AddressModel = new AddressModel();
        $AddressModel->address_id = $params["address_id"];
        $AddressModel->user_id = $params["user_id"];
        ///TOKEN验证
        $token_arr = [
            "user_id" => $params["user_id"],
            "token" => $params["token"]
        ];
        checkSign($token_arr);
        $addressList = $AddressModel->deleteAddress();
        if ($addressList > 0) {
            outJson(0, "删除成功！");
        } elseif ($addressList === -1) {
            outJson(100, errs_out(100));
        } else {
            outJson(116, $addressList);
        }
    }

    /*
     * @function:验证手机号的格式是否正确
     * @author:yyh
     * @date:20180625
     * */
    public function checkPhone($phone){
        ////数据验证；
        $rules      =   array(
            'phone'     =>  'length:11|regex:/^1[345789]\d{9}$/ims',
        );
        $message    =   array(
            'phone.length'      =>  '手机号码长度为11个字符',
            'phone.regex'       =>  '手机号码格式不正确',
        );
        $validate   =   new Validate($rules, $message);
        $data       =   [
            'phone'  => $phone,
        ];
        if (!$validate->check($data)) {
            return $validate->getError();
        } else {
            return true;
        }
    }

}
