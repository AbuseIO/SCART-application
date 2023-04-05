<?php
namespace abuseio\scart\classes\whois;

use Config;
use abuseio\scart\models\Whois_cache;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;

class scartWhoisCache {

    private static $_disabled = false;

    public static function setDisabled($disabled=true) {
        SELF::$_disabled = $disabled;
    }

    // ** GENERAL **

    public static function validWhoisCache($target,$target_type) {

        if (SELF::$_disabled) {
            $cnt = 0;
        } else {
            $nowstamp = date('Y-m-d H:i:s');
            // $cnt=0 if not found or max_age reached
            $cnt = Whois_cache::where('target',$target)
                ->where('target_type',$target_type)
                ->where('max_age','>',$nowstamp)
                ->count();
        }
        //scartLog::logLine("D-validWhoisCache; target=$target ($target_type); cnt=$cnt ");
        return ($cnt > 0);
    }

    public static function delWhoisCache($target,$target_type) {
        // delete (all)
        Whois_cache::where('target',$target)
            ->where('target_type',$target_type)
            ->delete();
    }

    // ** abuse_contact_id **/

    public static function getWhoisCache($target,$target_type) {

        $abusecontact_id = false;
        if (!SELF::$_disabled) {
            if (self::validWhoisCache($target,$target_type)) {
                $cache = Whois_cache::where('target',$target)
                    ->where('target_type',$target_type)
                    ->first();
                if ($cache) {
                    $abusecontact_id = $cache->abusecontact_id;
                    scartLog::logLine("D-getWhoisCache; load from WHOIS CACHE; target=$target ($target_type); max_age=$cache->max_age; abusecontact_id=$abusecontact_id ");
                }
            } else {
                scartLog::logLine("D-getWhoisCache; target=$target ($target_type); CACHE is to old or empty");
            }
        } else {
            scartLog::logLine("D-getWhoisCache; target=$target ($target_type); CACHE is disabled ");
        }
        return $abusecontact_id;
    }

    public static function setWhoisCache($target,$target_type,$abusecontact_id) {

        // clear before
        self::delWhoisCache($target,$target_type);

        // create
        $max_age = Systemconfig::get('abuseio.scart::whois.whois_cache_max_age',12);
        $agestamp = date('Y-m-d H:i:s', strtotime("+$max_age hours"));
        $cache = new Whois_cache();
        $cache->target = $target;
        $cache->target_type = $target_type;
        $cache->max_age = $agestamp;
        $cache->abusecontact_id = $abusecontact_id;
        $cache->save();

        scartLog::logLine("D-setWhoisCache; target=$target ($target_type); max-age=$cache->max_age");
    }

    // ** REAL_IP **//

    public static function getWhoisCacheRealIP($target,$target_type) {

        $real_ip = false;
        if (self::validWhoisCache($target,$target_type)) {
            $cache = Whois_cache::where('target',$target)
                ->where('target_type',$target_type)
                ->first();
            $real_ip = $cache->real_ip;
            scartLog::logLine("D-getWhoisCacheRealIP; load from WHOIS CACHE; target=$target ($target_type); max_age=$cache->max_age; real_ip=$real_ip ");
        } else {
            scartLog::logLine("D-getWhoisCacheRealIP; target=$target ($target_type); CACHE is to old or empty");
        }
        return $real_ip;
    }

    public static function setWhoisCacheRealIP($target,$target_type,$real_ip) {

        // clear before
        self::delWhoisCache($target,$target_type);

        // create
        $max_age = Systemconfig::get('abuseio.scart::whois.whois_cache_max_age',12);
        $agestamp = date('Y-m-d H:i:s', strtotime("+$max_age hours"));
        $cache = new Whois_cache();
        $cache->target = $target;
        $cache->target_type = $target_type;
        $cache->max_age = $agestamp;
        $cache->real_ip = $real_ip;
        $cache->save();

        scartLog::logLine("D-setWhoisCacheRealIP; target=$target ($target_type); max-age=$cache->max_age");
    }





}
