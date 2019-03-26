<?php

namespace app\api\controller\v1;

use app\api\model\Goods as GoodsModel;
use app\api\controller\Base;

/**
 * @function 商品处理类
 * @author zhult 
 */
class Goods extends Base {

    /**
     * @function 单条商品详情
     * @author zhult 
     */
    public function selectOne() {
        $this->postRule = [
            'goods_id' => [1, 'num', '商品id', 'goods_id', 1],
        ];
        //过滤参数，查看是否传递必须的数据
        $get_data = must_post($this->postRule, $this->req, 1);
        $goods_id = $get_data['goods_id'];
        $gmodel = new GoodsModel();
        $goodsInfo = $gmodel->selectOne($goods_id);
        if (empty($goodsInfo)) {
            outJson(113, errs_out(113));
        }
        //图片HTML字符过滤
        $pic_temp                   = htmlspecialchars_decode($goodsInfo['goods_content']);
        $goodsInfo['goods_content'] = getImgSrc($pic_temp);
        //商品属性和规格
        $goodsAttrInfo = $gmodel->selectGoodsAttrInfo($goods_id);
        $goodsImgInfo = $gmodel->selectGoodsImgInfo($goods_id);
        $goodsSpecialInfo = $gmodel->selectGoodsSpecial($goods_id);
        $json_data = array(
            'goodsInfo' => $goodsInfo,
            'goodsAttrInfo' => $goodsAttrInfo,
            'goodsImgInfo' => $goodsImgInfo,
            'goodsSpecialInfo' => $goodsSpecialInfo
        );
        outJson(0, $json_data);
    }

    /**
     * @function 商品列表
     * @author zhult 
     */
    public function selectList() {
        $this->postRule = [
            'cat_id' => [0, 'num', '目录id', 'cat_id', 0],
            'brand_id' => [0, 'num', '品牌id', 'brand_id', 0],
            'is_virtual' => [0, 'num', '是否虚拟商品', 'is_virtual', 0],
            'page'     => [0, 'num', '分页第?页', 'page', 0],
            'page_size'   => [0, 'num', '每页记录数', 'page_size', 20]
        ];
        //过滤参数，查看是否传递必须的数据
        $params = must_post($this->postRule, $this->req, 1);
        $gmodel = new GoodsModel();
        if (isset($params['cat_id'])) {
            $gmodel->cat_id = $params['cat_id'];
        }
        if (isset($params['brand_id'])) {
            $gmodel->brand_id = $params['brand_id'];
        }
        if (isset($params['is_virtual'])) {
            $gmodel->is_virtual = $params['is_virtual'];
        }
        if (isset($params['page'])) {
            $gmodel->page = $params['page'];
        }
        if (isset($params['page_size'])) {
            $gmodel->page_size = $params['page_size'];
        }
        $list = $gmodel->selectList();
        if (empty($list)) {
            outJson(113, errs_out(113));
        }
        $count = $gmodel->selectCount();
        $return_data = array(
            'list' => $list,
            'count' => $count
        );
        outJson(0, $return_data);
    }

}
