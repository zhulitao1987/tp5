<?php

namespace app\api\model;

use think\Model;
use think\Db;

/**
 * @function 文章类
 *
 * @author zhult
 */
class Article extends Model {

    //文章ID
    public $article_id;
    //文章类目ID
    public $cat_id;
    //关键词
    public $keywords;
    //文章通过审核？
    public $is_open = 1;
    //分页数据
    public $page = 0;
    public $page_size = 20;

    /**
     * @function 查询单条文章记录
     * @param int $article_id 文章ID
     * @return array 文章记录
     */
    public function selectOne($article_id) {
        return Db::name("article")->where("article_id", $article_id)->find();
    }

    /**
     * @function 查询多条文章记录
     * @return array 文章记录
     */
    public function selectList() {
        $params['is_open'] = array("eq", $this->is_open);
        if ($this->cat_id) {
            $params['cat_id'] = array("eq", $this->cat_id);
        }
        if ($this->keywords) {
            $params['keywords'] = array("LIKE", $this->keywords . "%");
        }
        return Db::name("article")->where($params)->limit($this->page * $this->page_size, $this->page_size)->select();
    }

    /**
     * @param $cat_info 查询文章分类条件 cat_type = 1
     * @param $column   排序的字段；
     * @param $order    排序方式；
     * @return array    对应的文章分类信息
     */
    public function selectCatList( $cat_info = array(), $page = 0, $page_size = 20, $column = "sort_order", $order = "ASC"  ) {
        try {
            $result = Db::name('article_cat')->where($cat_info)->order($column, $order)
                ->limit($page * $page_size, $page_size)->select();
        } catch (\Exception $e) {
            Log::error('[DB_ERROR]User:Database Error:'.$e->getMessage());
            return $e->getMessage();
        }
        return empty($result) ? 116 : $result;
    }

}
