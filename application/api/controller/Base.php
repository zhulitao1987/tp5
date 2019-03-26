<?php

namespace app\api\controller;

class Base {

    protected $req = ''; //post传递参数
    protected $postRule = []; //需要传递的参数

    public function __construct() {
        $this->req = $_POST;
    }

    public function check($id = 0) {
        if ($id > 0) {
            return true;
        }
        return false;
    }

}
