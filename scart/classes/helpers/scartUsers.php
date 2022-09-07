<?php

/**
 * General SCART users functions
 *
 * 2019/02/01
 *   Based on OctoberCMS backend administrator functionality
 *
 */

namespace abuseio\scart\classes\helpers;

use Db;
use Config;
use BackendAuth;
use Translator;
use Backend\Models\User;
use Backend\Models\UserGroup;
use abuseio\scart\models\User_options;
use abuseio\scart\models\Systemconfig;

class scartUsers {

    static $_unknown = '(unknown)';
    static $_SCARTuser = 'SCARTuser';
    static $_SCARTmanager = 'SCARTmanager';
    static $_SCARTcorona = 'SCARTcorona';
    static $_SCARTadmin = 'SCARTadmin';
    static $_SCARTworkuser = SCART_GROUP_WORKUSER;
    static $_SCARTroleIds = [];

    static function roleId($code) {
        if (!isset(self::$_SCARTroleIds[$code])) {
            $rec = Db::table('backend_user_roles')->where('code',$code)->first();
            self::$_SCARTroleIds[$code] = ($rec) ? $rec->id : 0;
        }
        return self::$_SCARTroleIds[$code];
    }

    public static function getWorkusers() {

        $workusers = Db::table('backend_users')->join('backend_users_groups', 'backend_users.id', '=', 'backend_users_groups.user_id')
            ->join('backend_user_groups', 'backend_users_groups.user_group_id', '=', 'backend_user_groups.id')
            ->where('backend_user_groups.code',self::$_SCARTworkuser)
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
        $chk = Db::table('backend_users')->where('id',$userid)->where('role_id',self::roleId(self::$_SCARTuser))->first();
        return ($chk!='');
    }

    public static function isManager($workuser_id=0) {

        $userid = ($workuser_id==0) ?  self::getId() : $workuser_id;
        $chk = Db::table('backend_users')->where('id',$userid)->where('role_id',self::roleId(self::$_SCARTmanager))->first();
        return ($chk!='');
    }

    public static function isScartAdmin($workuser_id=0) {

        $userid = ($workuser_id==0) ?  self::getId() : $workuser_id;
        $chk = Db::table('backend_users')->where('id',$userid)->where('role_id',self::roleId(self::$_SCARTadmin))->first();
        return ($chk!='');
    }

    public static function isCorona($workuser_id=0) {

        $userid = ($workuser_id==0) ?  self::getId() : $workuser_id;
        $chk = Db::table('backend_users')->where('id',$userid)->where('role_id',self::roleId(self::$_SCARTcorona))->first();
        return ($chk!='');
    }

    public static function isAdmin() {
        $user = BackendAuth::getUser();
        return ($user->is_superuser==1);
    }

    public static function isDisabled() {
        $id = self::getId();
        $user = \abuseio\scart\models\User::where('be_user_id',$id)->first();
        if ($user) {
            if (!$user->disabled) {
                $day = date('N') - 1;
                if (isset($user->workschedule[$day])) {
                    if (count($user->workschedule[$day]) >= 2)  {
                        $mins = date('G') * 60 + intval(date('i'));
                        //scartLog::logLine("D-isDisabled; check if within working hours today (day=$day): mins=$mins; from=" . $user->workschedule[$day][0] . ', till=' . $user->workschedule[$day][1]);
                        if ($mins < $user->workschedule[$day][0] || $mins > $user->workschedule[$day][1]) {
                            $user->disabled = true;
                        }
                    } else {
                        scartLog::logLine("D-isDisabled; on this day (=$day) no working hours");
                        $user->disabled = true;
                    }
                }
            }
        } else {
            // eg scheduler account
            $user = new \stdClass();
            $user->disabled = false;
        }
        return ($user->disabled);
    }

    public static function getOption($name, $user_id=0) {

        if ($user_id==0) $user_id = self::getId();
        $find = User_options::where('user_id', $user_id)->where('name',$name)->first();
        return (($find) ? unserialize($find->value) : '');
    }

    public static function getGeneralOption($name, $default='') {

        $user_id = 0;
        $find = User_options::where('user_id', $user_id)->where('name',$name)->first();
        return (($find) ? unserialize($find->value) : $default);
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

    public static function setGeneralOption($name, $value) {

        $user_id = 0;
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
            $scheduler_login = Systemconfig::get('abuseio.scart::scheduler.login');
            if (self::getLogin() == $scheduler_login) {
                $ret = '(scheduler)';
            } else {
                $ret = self::$_unknown;
            }
        }
        return $ret;
    }


    public static function getBackendUser($user_id='') {
        if (!$user_id) $user_id = self::getId();
        $be_user = Db::table('backend_users')->where('id',$user_id)->first();
        return $be_user;
    }

    public static function updBackendUser($user_id,$updatedata) {
        Db::table('backend_users')->where('id',$user_id)->update($updatedata);
    }

    public static function delBackendUser($user_id) {
        Db::table('backend_users_groups')->where('user_id',$user_id)->delete();
        Db::table('backend_users')->where('id',$user_id)->delete();
    }

    public static function getBackendRoles() {
        return Db::table('backend_user_roles')->where('is_system','0')->where('code','<>',SCART_ROLE_SCHEDULER)->orderBy('id')->select('id','code','description')->get();
    }

    public static function setBackendRole($user_id,$role_id) {
        Db::table('backend_users')->where('id',$user_id)->update([
            'role_id' => $role_id,
        ]);
    }

    public static function setBackendWorkuser($user_id) {
        $group = Db::table('backend_user_groups')->where('code',SCART_GROUP_WORKUSER)->first();
        if ($group) {
            $user_groups = Db::table('backend_users_groups')->where('user_id',$user_id)->where('user_group_id',$group->id)->first();
            if (!$user_groups) {
                Db::table('backend_users_groups')->insert([
                    'user_id' => $user_id,
                    'user_group_id' => $group->id,
                ]);
            }
        }
    }

    // ** locale ** //

    public static function getLocale() {

        $trans = Trans::instance();
        return $trans->getLocale();
    }


}
