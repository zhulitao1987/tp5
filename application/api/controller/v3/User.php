<?php

namespace app\api\controller\v3;

use app\api\model\User as UserModel;
use app\api\controller\Base;
use think\cache\driver\Redis;

class User extends Base {

    public function index() {
        $user = UserModel::get(100);
        $ret = $this->check(100);
        if (empty($ret)) {
            exit("请求非法");
        }
        if ($user) {
            return json(array('user' => $user, 'data' => getTest()));
        } else {
            return json(['error' => '用户不存在'], 404);
        }
    }

    public function outjson() {
        $model = new Redis();
        if (!empty($model)) {
            return json(['error' => ''], 200);
        }
        return json(['error' => '用户不存在'], 404);
    }

}
