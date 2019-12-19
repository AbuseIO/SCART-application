<?php

/**
 * General ERT users functions
 *
 * 2019/02/01
 *   Based on OctoberCMS backend administrator functionality
 *
 */

namespace reportertool\eokm\classes;

use Db;
use Config;
use BackendAuth;
use Backend\Models\User;
use Backend\Models\UserGroup;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\User_options;

class ertUsers {

    static $_unknown = '(unknown)';
    static $_ERTuser = 'ERTuser';
    static $_ERTmanager = 'ERTmanager';
    static $_ERTroleIds = [];

    static function roleId($code) {
        if (!isset(self::$_ERTroleIds[$code])) {
            $rec = Db::table('backend_user_roles')->where('code',$code)->first();
            self::$_ERTroleIds[$code] = $rec->id;
        }
        return self::$_ERTroleIds[$code];
    }

    public static function getWorkusers() {

        $workusers = Db::table('backend_users')->join('backend_users_groups', 'backend_users.id', '=', 'backend_users_groups.user_id')
            ->join('backend_user_groups', 'backend_users_groups.user_group_id', '=', 'backend_user_groups.id')
            ->where('backend_user_groups.code','ERTworkuser')
            ->select('backend_users.*')
            ->get();
        return $workusers;
    }

    public static function getWorkuserId($login) {

        $user = Db::table('backend_users')->where('login', $login)->first();
        return ($user) ? $user->id : 0;
    }

    public static function getWorkuserLogin($id) {

        $user = Db::table('backend_users')->where('id', $id)->first();
        return ($user) ? $user->login : self::$_unknown;
    }

    public static function getId() {
       return BackendAuth::getUser()->id;
    }

    public static function getUser() {
        return BackendAuth::getUser();
    }

    public static function isUser($workuser_id=0) {

        $userid = ($workuser_id==0) ? self::getId() : $workuser_id;
        $chk = Db::table('backend_users')->where('id',$userid)->where('role_id',self::roleId(self::$_ERTuser))->first();
        return ($chk!='');
    }

    public static function isManager($workuser_id=0) {

        $userid = ($workuser_id==0) ?  self::getId() : $workuser_id;
        $chk = Db::table('backend_users')->where('id',$userid)->where('role_id',self::roleId(self::$_ERTmanager))->first();
        return ($chk!='');
    }

    public static function isAdmin() {
        $user = BackendAuth::getUser();
        return ($user->is_superuser===1);
    }

    public static function getOption($name, $user_id=0) {

        if ($user_id==0) $user_id = self::getId();
        $find = User_options::where('user_id', $user_id)->where('name',$name)->first();
        return (($find) ? unserialize($find->value) : '');
    }

    public static function setOption($name, $value, $user_id=0) {

        if ($user_id==0) $user_id = self::getId();
        $find = User_options::where('user_id', $user_id)->where('name',$name)->first();
        if ($find=='') {
            $find = new User_options();
            $find->user_id = $user_id;
            $find->name = $name;
        }
        $find->value = serialize($value);
        $find->save();
    }

    public static function getFullName($id=0) {

        if ($id==0) $id = self::getId();
        $user = Db::table('backend_users')->where('id', $id)->first();
        return ( ($user) ? $user->first_name . ' ' . $user->last_name : self::$_unknown);
    }

    public static function getLogin() {

        $user = self::getUser();
        $login = ($user) ? $user->login : self::$_unknown;
        return $login;
    }

    public static function getRemoteAddress() {

        // based on REMOTE_ADDR
        $ret = (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '';
        if ($ret=='') {
            $scheduler_login = Config::get('reportertool.eokm::scheduler.login');
            if (self::getLogin() == $scheduler_login) {
                $ret = '(scheduler)';
            } else {
                $ret = self::$_unknown;
            }
        }
        return $ret;
    }
}
