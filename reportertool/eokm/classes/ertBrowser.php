<?php

/**
 * ertBrowser
 *
 * Wrapper for Browser implemenation
 *
 * Provider Functions
 * - getData(url,referer)
 * - getImages(url,referer)
 * - getImageData(url,referer)
 *
 * Helpers
 * - getImageBase64(url,hash,referer)
 * - parse_base($link)
 * - get_host($base)
 * - validateURL($url)
 *
 */

namespace reportertool\eokm\classes;

use Config;
use ReporterTool\EOKM\Models\Scrape_cache;

class ertBrowser {

    private static $_browserInstance='';
    // extra logging
    public static $_debug = false;

    public static function getBrowser() {
        if (SELF::$_browserInstance=='') {
            $browserprovider = Config::get('reportertool.eokm::browser.provider', 'BrowserBasic');
            if ($browserprovider) {
                $browserclass = 'reportertool\eokm\classes\ert'.$browserprovider;
                ertLog::logLine("D-ertBrowser; use provider '$browserprovider' ");
                SELF::$_browserInstance = new $browserclass();
            }
        }
        return SELF::$_browserInstance;
    }

    public static function setBrowser($browserprovider) {
        if ($browserprovider) {
           $browserclass = 'reportertool\eokm\classes\ert'.$browserprovider;
           ertLog::logLine("D-ertBrowser; set provider '$browserprovider' ");
           SELF::$_browserInstance = new $browserclass();
        }
    }

    public static $_usecached = '';
    public static function useCached() {
        if (self::$_usecached === '') {
            self::$_usecached = Config::get('reportertool.eokm::browser.provider_cache', false);
        }
        return self::$_usecached;
    }
    public static function setCached($set) {
        self::$_usecached = $set;
    }

    /**
     * provider static functions:
     * - getData(url,referer)
     * - getImages(url,referer)
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

    public static function getImageBase64($url,$hash,$ifemptyshowurl=true,$imagenotfound=ERT_IMAGE_NOT_FOUND)
    {

        $imagebase65 = '';
        $provider_cache = Config::get('reportertool.eokm::browser.provider_cache', false);

        if ($provider_cache) {
            $cache = Scrape_cache::getCache($hash);
            if ($cache) {
                $imagebase65 = $cache->cached;
            } else {
                if ($ifemptyshowurl) {
                    $imagebase65 = $url;
                } else {
                    $cache = Scrape_cache::getCache($imagenotfound);
                    if ($cache) {
                        $imagebase65 = $cache->cached;
                    } else {
                        $inf = plugins_path($imagenotfound);
                        $data = file_get_contents($inf);
                        if ($data) {
                            ertLog::logLine("D-getImageBase64; fill cache with '$imagenotfound' ($inf) ");
                            $imagebase65 = "data:image/png;base64," . base64_encode($data);
                            Scrape_cache::addCache($imagenotfound, $imagebase65);
                        } else {
                            ertLog::logLine("W-getImageBase64; '$imagenotfound' ($inf) not found!?!");
                        }
                    }
                }
            }

        } else {
            $imagebase65 = $url;
        }

        return $imagebase65;
    }

    public static function delImageCache($hash) {
        $provider_cache = Config::get('reportertool.eokm::browser.provider_cache', false);
        if ($provider_cache) {
            ertLog::logLine("D-delImageCache($hash)");
            Scrape_cache::delCache($hash);
        }
    }

    // ** HELPER FUNCTIONS **

    public static function getImageHash($imgsrc) {

        $hash = '';
        try {
            // use a combination of size and hash
            $hash = sprintf('%s%016d', md5($imgsrc),strlen($imgsrc));   // 48 tekens
            if (SELF::$_debug) ertLog::logLine("D-ertBrowserBasic.getImageHash; hash=$hash");
        } catch (Exception $err) {
            ertLog::logLine("E-ertBrowserBasic.getImageHash error: ".$err->getMessage());
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

            if (SELF::$_debug) ertLog::logLine("D-ertBrowser.parse_base; base=$base");

        } catch (Exception $err) {

            ertLog::logLine("E-ertBrowser.parse_base error: ".$err->getMessage());

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

            if (SELF::$_debug) ertLog::logLine("D-ertBrowser.get_host; host=$host");

        } catch (Exception $err) {

            ertLog::logLine("E-ertBrowser.get_host error: ".$err->getMessage());

        }

        return $host;
    }

    public static function validateURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

}
