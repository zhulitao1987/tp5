<?php

namespace app\api\model;

use think\Model;
use think\Db;

/**
 * 商品底层操作类
 *
 * @author zhult
 */
class Goods extends Model {

    public $goods_id;
    public $cat_id;
    public $extend_cat_id;
    public $goods_sn;
    public $brand_id;
    public $is_virtual = '0';
    public $page = 0;
    public $page_size = 20;

    /**
     * @function 修改商品信息
     * @param int $goods_id 商品id
     * @return boolean true|false
     * @author zhult
     */
    public function updateGoodsInfo($params = array(), $goods_id = 0) {
        return Db::name("goods")->where("goods_id", $goods_id)->update($params);
    }

    /**
     * @param int $goods_id 商品id
     * @return array 商品id对应的商品信息
     */
    public function selectOne($goods_id) {
        return Db::name("goods")->where("goods_id", $goods_id)->find();
    }

    /**
     * @param int $goods_id 商品id
     * @return array 商品id对应的商品图片信息
     */
    public function selectGoodsImgInfo($goods_id) {
        return Db::name("goods_images")->where("goods_id", $goods_id)->select();
    }

    /**
     * @param int $goods_id 商品id
     * @return array 商品id对应的商品属性信息
     */
    public function selectGoodsAttrInfo($goods_id) {
        $model = model("goods");
        $sql = "SELECT "
                . "A.*, B.attr_name "
                . "FROM yzt_goods_attr AS A "
                . "LEFT JOIN yzt_goods_attribute AS B "
                . "ON A.attr_id = B.attr_id "
                . "WHERE A.goods_id = $goods_id";
        $list = $model->query($sql);
        return $list;
    }

    /**
     * @return int 商品搜索结果计数
     */
    public function selectCount() {
        $params['is_virtual'] = array('eq', $this->is_virtual);
        if ($this->cat_id) {
            $params['cat_id'] = array('eq', $this->cat_id);
        }
        if ($this->extend_cat_id) {
            $params['extend_cat_id'] = array('eq', $this->extend_cat_id);
        }
        if ($this->brand_id) {
            $params['brand_id'] = array('eq', $this->brand_id);
        }
        $list = Db::name("goods")->where($params)->select();
        return count($list);
    }

    /**
     * @return array 商品列表结果
     */
    public function selectList() {
        $params['is_virtual'] = array('eq', $this->is_virtual);
        if ($this->cat_id) {
            $params['cat_id'] = array('eq', $this->cat_id);
        }
        if ($this->extend_cat_id) {
            $params['extend_cat_id'] = array('eq', $this->extend_cat_id);
        }
        if ($this->brand_id) {
            $params['brand_id'] = array('eq', $this->brand_id);
        }
        return Db::name("goods")->where($params)->limit($this->page * $this->page_size, $this->page_size)->select();
    }

    /**
     * 获取商品规格
     * @param $goods_id|商品id
     * @return array
     */
    public function selectGoodsSpecial($goods_id) {
        //商品规格 价钱 库存表 找出 所有 规格项id
        $orgin_keys = Db::name("spec_goods_price")->where("goods_id", $goods_id)->field("GROUP_CONCAT(`key` ORDER BY store_count desc SEPARATOR '_') as ORIGIN_KEYS")->find();
        $keys = !empty($orgin_keys['ORIGIN_KEYS']) ? $orgin_keys['ORIGIN_KEYS'] : "";
        $filter_spec = array();
        $specImage = array();
        if (!empty($keys)) {
            $specImageTemp = Db::name('spec_image')->where(['goods_id' => $goods_id, 'src' => ['<>', '']])->field("spec_image_id,src")->select(); // 规格对应的 图片表， 例如颜色
            foreach ((array) $specImageTemp as $image) {
                $specImage[$image['spec_image_id']] = $image['src'];
            }
            $keys_array = str_replace('_', ',', $keys);
            $sql = "SELECT "
                    . "a.name,"
                    . "a.order,"
                    . "b.* "
                    . "FROM yzt_spec AS a "
                    . "INNER JOIN yzt_spec_item AS b "
                    . "ON a.id = b.spec_id "
                    . "WHERE b.id IN($keys_array) "
                    . "ORDER BY b.id";
            $filter_spec2 = Db::query($sql);
            foreach ((array) $filter_spec2 as $val) {
                $filter_spec[$val['name']][] = array(
                    'item_id' => $val['id'],
                    'item' => $val['item'],
                    'src' => isset($specImage[$val['id']]) ? $specImage[$val['id']] : "",
                );
            }
        }
        return $filter_spec;
    }

}
