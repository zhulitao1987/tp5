<?php

namespace app\api\controller\v1;

use app\api\controller\Base;
use app\api\model\Sms as SmsModel;
/**
 * @function 发送短信
 */
class Sms extends Base
{

    public function sendSms(){
//        echo __DIR__ .'/../../../extend/sms/emay/emay_sms.php';exit;
        $phone  = "18606291337";
        $content = "你好";
        $sms_model   =    new SmsModel();
        $result = $sms_model->smsAdd($phone,'测试' ,$content);

    }


}
