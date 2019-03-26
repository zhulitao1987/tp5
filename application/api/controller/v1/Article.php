<?php

namespace app\api\controller\v1;

use app\api\controller\Base;
use app\api\model\Article as ArticleModel;

/**
 * @function 文章类处理
 *
 */
class Article extends Base
{

    /**
     * 获取当前cat_type为1下的所有开启状态下的文章
     * @return json data
     */

    public function selectList()
    {
        header("Access-Control-Allow-Origin: *");
        $cat_info['cat_type'] = 1;
        $orderModel = new ArticleModel();
        //获取有效文章分类信息
        $return = $orderModel->selectCatList($cat_info);
        if(!is_array($return) && $return == 116)
        {
            outJson(116, errs_out(116));
        }
        $data = array();
        foreach ($return as $key => $val)
        {
            $orderModel ->cat_id               = $val['cat_id']; //文章分类ID
            $orderModel ->is_open              = 1;              //文章详情页开启状态
            $returnData                        = $orderModel->selectList(); //获取文章分类下开启的文章详情
            //文章分类以及分类下的详情组成数组返回
            $data[$key]                        = $val;
            $data[$key]['article_description'] = empty($returnData) ? '' : $returnData;

        }
        outJson(0, $data);
    }

}
