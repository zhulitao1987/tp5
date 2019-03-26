<?php

namespace app\index\controller;

use think\View;
use app\index\model\User;

class Index {

    public function index() {

        $user_model = new User();
        $user_info = $user_model->selectOne();
        $view = new View();
        return $view->fetch('index', array('user_info' => $user_info));
    }

    public function test() {
        $view = new View();
        return $view->fetch('test');
    }

    /**
     * @function    验证图片验证码是否正确；
     * @return      bool
     */
    public function check() {
        $verify_code =  I('post.verify_code');
        $verify      =  $verify_code ? $verify_code : "";
        if (empty($verify)) {
            echo 1105;
        }
        if(!captcha_check($verify)){
            echo 1105;
        }else{
            echo 0;
        }
    }
}
