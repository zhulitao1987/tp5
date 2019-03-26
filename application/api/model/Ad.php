<?php

namespace app\api\model;

use think\Model;
use think\Db;

/**
 * 广告位操作方法
 *
 * @author zhult
 */
class Ad extends Model {

    public $ad_id;
    public $pid;
    public $media_type = 0;
    public $start_time;
    public $end_time;
    public $enabled = 1;
    public $page = 0;
    public $page_size = 20;

    /**
     * @param int $ad_id 广告id
     * @return array 广告信息
     */
    public function selectOne($ad_id) {
        return model("ad")->where("ad_id", $ad_id)->find();
    }

    /**
     * @function 筛选结果记录数
     */
    public function selectCount() {
        $params['enabled'] = array('eq', $this->enabled);
        $params['media_type'] = array('eq', $this->media_type);
        if ($this->pid) {
            $params['pid'] = array('eq', $this->pid);
        }
        //开始时间和结束时间判断
        if (!empty($this->start_time) && !empty($this->end_time)) {
            $params['start_time'] = array('EGT', $this->start_time);
            $params['end_time'] = array('ELT', $this->end_time);
        }
        $list = model("ad")->where($params)->select();
        return count($list);
    }

    /**
     * @function 筛选结果列表
     */
    public function selectList() {
        if ($this->pid) {
            $params['pid'] = array('eq', $this->pid);
            //判断是否广告位置是否显示状态。不显示返回空 param：is_open = 1
            $data = Db::name("ad_position")->where("position_id", $this->pid)->find();
            if(empty($data) || $data['is_open'] != 1)
            {
                return '';
            }
        }
        //广告详情显示
        $params['enabled'] = array('eq', $this->enabled);
        if ($this->media_type) {
            $params['media_type'] = array('eq', $this->media_type);
        }

        //开始时间和结束时间判断
        if (!empty($this->start_time) && !empty($this->end_time)) {
            $params['start_time'] = array('EGT', $this->start_time);
            $params['end_time'] = array('ELT', $this->end_time);
        }
        return model("ad")->where($params)->limit($this->page * $this->page_size, $this->page_size)->select();
    }

}
