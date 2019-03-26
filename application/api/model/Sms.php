<?php
namespace app\api\model;
//require_once  __DIR__ .'/../../extend/sms/emay/emay_sms.php';
use think\Model;
use think\Db;
use think\Log;
use think\Loader;
use emay_sms;

class Sms extends Model
{

    private $emay_sms = null;

    public function __construct()
    {
        Loader::import('emay\sms\emay\emay_sms', EXTEND_PATH);
        $this->emay_sms = new emay_sms();
        var_dump($this->emay_sms);
    }

    /**
     * 根据短信模板和参数获取短信内容
     * @param string $templateName 短信模板
     * @param string $params 参数，串行化键值对的数组
     * @return array|bool|mixed|string 保存结果
     */
    public function getContent($templateName, $params = '', $verify = '')
    {
        //获取短信模板内容
        $template_info = M('sms_template')->get_one(['title' => $templateName, 'is_enable' => "是"]);
        if (!$template_info) {
            return false;
        }
        $template_id = $template_info['id'];
        $template = $template_info['content'];
        if ($params) {
            $paramArr = is_string($params) ? unserialize(html_entity_decode($params)) : '';
            if ($verify) $paramArr['verify'] = $verify;
            if (is_array($paramArr)) {
                foreach ($paramArr as $key => $value)
                    is_string($value) || $value >= 0 ? $template = str_replace('{' . strtoupper($key) . '}', $value, $template) : outJson(-1, '参数非法！');
            } else {
                return false;
            }
        }
        if (stristr($template, '{'))
            return false;
        return array(
            'title'       => $templateName,
            'template'    => $template,
            'template_id' => $template_id,
            'is_voice'    => $template_info['is_voice'],
            'is_limit'    => $template_info['is_limit']
        );
    }

    /**
     * 添加短信代码
     * @param string $phone 手机号
     * @param string $sms_title 模板名称
     * @param string $params 参数，串行化键值对的数组
     * @param string $ver 验证码
     * @return bool
     */
    public function smsAdd($phone, $sms_title, $params, $ver = "")
    {

        //获取短信内容
        $content = "你好";
        if ($content) {
            $this->doSendSmsCommon(25, '', $params);
        }
        return true;
    }

    /**
     * 发送短信方法
     * @param string $log_id 短信ID
     * @param string $is_voice 是否语音
     * @param array $params 参数
     * @return bool
     */
    public function doSendSmsCommon($log_id, $is_voice = '', $params = array())
    {
        $phone = '18606291337';
        $content = '好呀好';
        $result = $this->emay_sms->sendSMS($phone, $content);
        dump($result);exit;
    }
}