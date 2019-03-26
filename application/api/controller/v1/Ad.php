<?php

namespace app\api\controller\v1;

use app\api\model\Ad as AdModel;
use app\api\controller\Base;

/**
 * @function 广告位API
 *
 * @author zhult
 */
class Ad extends Base {

    /**
     * @function 根据广告ID,查询单条记录 
     */
    public function selectOne() {
        header("Access-Control-Allow-Origin: *");
        $this->postRule = [
            'ad_id' => [1, 'num', '广告id', 'ad_id', 1],
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $ad_id = $params['ad_id'];
        $AdModel = new AdModel();
        $return = $AdModel->selectOne($ad_id);
        if (empty($return)) {
            outJson(113, errs_out(113));
        }
        outJson(0, $return);
    }
    
    /**
     * @function 根据广告ID,查询单条记录
     */
    public function selectList(){
        header("Access-Control-Allow-Origin: *");
        $this->postRule = [
            'pid' => [1, 'num', '位置id', 'pid', 0],
            'start_time' => [0, 'string', '活动开始时间（时间戳）', 'start_time', ""],
            'end_time' => [0, 'string', '活动结束时间（时间戳）', 'end_time', ""],
            'media_type' => [0, 'num', '广告类型', 'media_type', 0],
            'page'     => [0, 'num', '分页第?页', 'page', 0]
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $AdModel = new AdModel();
        if (isset($params['pid'])) {
            $AdModel->pid = $params['pid'];
        }
        if (isset($params['start_time']) && isset($params['end_time'])) {
            $AdModel->start_time = $params['start_time'];
            $AdModel->end_time = $params['end_time'];
        }
        if (isset($params['media_type'])) {
            $AdModel->media_type = $params['media_type'];
        }
        if (isset($params['page'])) {
            $AdModel->page = $params['page'];
        }
        //广告数据列表
        $list = $AdModel->selectList();
        if (empty($list)) {
            outJson(113, errs_out(113));
        }
        $count = $AdModel->selectCount();
        $return_data = array(
            'list' => $list,
            'count' => $count
        );
        outJson(0, $return_data);
    }

}
