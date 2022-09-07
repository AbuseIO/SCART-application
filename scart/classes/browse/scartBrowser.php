<?php

/**
 * scartBrowser
 *
 * Wrapper for Browser implemenation
 *
 * Provider Functions
 * - getData(url,referer)
 * - getImages(url,referer,screenshot)
 * - getImageData(url,referer)
 *
 * Helpers
 * - getImageBase64(url,hash,referer)
 * - parse_base($link)
 * - get_host($base)
 * - validateURL($url)
 *
 */

namespace abuseio\scart\classes\browse;

use Config;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;

class scartBrowser {

    static $_lasterror = '';
    public static function getLasterror() {
        return SELF::$_lasterror;
    }

    private static $_browserInstance='';
    // extra logging
    public static $_debug = false;

    public static function getBrowser() {
        if (SELF::$_browserInstance=='') {
            $browserprovider = Systemconfig::get('abuseio.scart::browser.provider', 'BrowserBasic');
            if ($browserprovider) {
                $browserclass = 'abuseio\scart\classes\browse\scart'.$browserprovider;
                scartLog::logLine("D-scartBrowser; use provider '$browserprovider' ");
                SELF::$_browserInstance = new $browserclass();
            }
        }
        return SELF::$_browserInstance;
    }

    public static function setBrowser($browserprovider) {
        if ($browserprovider) {
           $browserclass = 'abuseio\scart\classes\browse\scart'.$browserprovider;
           scartLog::logLine("D-scartBrowser; set provider '$browserprovider' ");
           SELF::$_browserInstance = new $browserclass();
        }
    }

    public static $_usecached = '';
    public static function useCached() {
        if (self::$_usecached === '') {
            self::$_usecached = Systemconfig::get('abuseio.scart::browser.provider_cache', false);
        }
        return self::$_usecached;
    }
    public static function setCached($set) {
        self::$_usecached = $set;
    }

    /**
     * provider static functions:
     * - getData(url,referer)
     * - getImages(url,referer,screenshot)
     * - getImageData(url,referer)
     *
     * @param $methode_name
     * @param $args
     * @return mixed
     */

    public static function __callStatic($methode_name,$args) {
        $provider = SELF::getBrowser();
        return call_user_func_array(array($provider,$methode_name),$args);
    }

    /**
     * getImageBase64
     *
     * Get image base64 source data from cache
     *
     */

    public static function getImageBase64($url,$hash,$ifemptyshowurl=true,$imagenotfound=SCART_IMAGE_NOT_FOUND)
    {

        $imagebase64 = '';
        $provider_cache = Systemconfig::get('abuseio.scart::browser.provider_cache', false);

        if ($provider_cache) {
            $cache = Scrape_cache::getCache($hash);
            if ($cache) {
                $imagebase64 = $cache->cached;
            } else {
                if ($ifemptyshowurl) {
                    $imagebase64 = $url;
                } else {
                    $cache = Scrape_cache::getCache($imagenotfound);
                    if ($cache) {
                        $imagebase64 = $cache->cached;
                    } else {
                        $inf = plugins_path($imagenotfound);
                        $data = file_get_contents($inf);
                        if ($data) {
                            scartLog::logLine("D-getImageBase64; fill cache with '$imagenotfound' ($inf) ");
                            $imagebase64 = "data:image/png;base64," . base64_encode($data);
                            Scrape_cache::addCache($imagenotfound, $imagebase64);
                        } else {
                            scartLog::logLine("W-getImageBase64; '$imagenotfound' ($inf) not found!?!");
                        }
                    }
                }
            }

        } else {
            $imagebase64 = $url;
        }

        return $imagebase64;
    }

    public static function delImageCache($hash) {
        $provider_cache = Systemconfig::get('abuseio.scart::browser.provider_cache', false);
        if ($provider_cache) {
            scartLog::logLine("D-delImageCache($hash)");
            Scrape_cache::delCache($hash);
        }
    }

    // ** HELPER FUNCTIONS **

    public static function getImageHash($imgsrc) {

        $hash = '';
        try {
            // use a combination of size and hash
            $hash = sprintf('%s%016d', md5($imgsrc),strlen($imgsrc));   // 48 tekens
            if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.getImageHash; hash=$hash");
        } catch (Exception $err) {
            scartLog::logLine("E-scartBrowserBasic.getImageHash error: ".$err->getMessage());
        }
        return $hash;
    }

    public static function parse_base($link) {

        $base = '';
        try {
            $url = parse_url($link);
            if ($url!==false) {
                // init base (can be overruled, see below)
                $base  = (isset($url['scheme']) ? $url['scheme'] : 'http') . '://';
                $base .= (isset($url['host']) ? $url['host'] : '') ;
                $path = (isset($url['path']) ? $url['path'] : '') ;
                if ($path) {
                    $pos = strrpos($path,'/');
                    if ($pos!==false) $path = substr($path,0,$pos);
                    $base .= $path;
                }
                if (substr($base,-1,1) != '/') $base .= '/';
            }

            if (SELF::$_debug) scartLog::logLine("D-scartBrowser.parse_base; base=$base");

        } catch (Exception $err) {

            scartLog::logLine("E-scartBrowser.parse_base error: ".$err->getMessage());

        }

        return $base;
    }

    public static function get_host($base) {

        $host = '';
        try {

            $url = parse_url($base);
            if ($url!==false) {
                $host = (isset($url['host']) ? $url['host'] : '') ;
            }

            if (SELF::$_debug) scartLog::logLine("D-scartBrowser.get_host; host=$host");

        } catch (Exception $err) {

            scartLog::logLine("E-scartBrowser.get_host error: ".$err->getMessage());

        }

        return $host;
    }

    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

}
