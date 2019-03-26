<?php

namespace app\api\controller\v2;

use app\api\model\User as UserModel;

class User {

    public function index() {
        $user = UserModel::get(100);
        if ($user) {
            return json($user);
        } else {
            return json(['error' => '用户不存在'], 404);
        }
    }

}
