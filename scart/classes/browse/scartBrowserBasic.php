<?php

/**
 * scartBrowserBasic
 *
 * Simple CURL/libxml/DOMDocument implementation of scraping
 * - no javascript or other interactive actions
 * - when provider_cache=false, no caching of images
 *
 * Provider Functions
 * - getData(url,referer)
 * - getImages(url)
 * - getImageData(url)
 *
 * Note:
 * - log no errors, just ignore as warning
 *
 */

namespace abuseio\scart\classes\browse;

use Config;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\classes\helpers\scartLog;

class scartBrowserBasic extends scartBrowser {

    private static $_useragent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0';

    /**
     * General START and STOP functions
     * Can be overrules by specific browser provider for optimalization
     */
    public static function startBrowser() {
        SELF::$_lasterror = '';
    }
    public static function stopBrowser() {
    }

    // ** 1: Browse (access) external link ** //

    private static function _removeBom($var) {
        //return preg_replace('/\\0/', "", $var);
        return str_replace("\0", '', $var);
    }

    public static function getData($link, $referer='') {

        $response = '';

        try {

            $request = curl_init();
            $options = array(
                CURLOPT_URL => self::_removeBom($link),
                CURLOPT_RETURNTRANSFER => 1,
                // No longer supported
                // CURLOPT_USERAGENT, self::$_useragent,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: '. self::$_useragent,
                ],
                CURLOPT_HEADER => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_MAXREDIRS => 10);
            if ($referer) {
                $options[CURLOPT_REFERER] = $referer;
            }
            curl_setopt_array($request, $options);



            $response = curl_exec($request);
            if (curl_error($request)) {
                SELF::$_lasterror = curl_error($request);
                scartLog::logLine("W-scartBrowserBasic.loadLink error: ".SELF::$_lasterror );
            } else {
                if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.loadLink; link $link loaded " . (($referer) ? " (referer=$referer)" : '') );
                SELF::$_lasterror = '';
            }
            curl_close($request);


        } catch (Exception $err) {

            SELF::$_lasterror = $err->getMessage();
            scartLog::logLine("W-scartBrowserBasic.loadLink error: ".SELF::$_lasterror );

        }

        return $response;
    }

    static function make_absolute($url, $base) {

        // Return base if no url
        if( ! $url) return $base;

        // Return if already absolute URL
        if(parse_url($url, PHP_URL_SCHEME) != '') return $url;

        // Urls only containing query or anchor
        if($url[0] == '#' || $url[0] == '?') return $base.$url;

        // Parse base URL and convert to local variables: $scheme, $host, $path
        $scheme = $host = $path = '';
        extract(parse_url($base));

        // If no path, use /
        if( ! isset($path)) $path = '/';

        // Remove non-directory element from path
        $path = preg_replace('#/[^/]*$#', '', $path);

        // Destroy path if relative url points to root
        if($url[0] == '/') $path = '';

        // Dirty absolute URL
        $abs = "$host$path/$url";

        // Replace '//' or '/./' or '/foo/../' with '/'
        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}

        // Absolute URL is ready!
        return $scheme.'://'.$abs;
    }

    // ** 2: HTML/IMAGE extracting info

    static function isHTML($html) {

        $ishtml = false;

        try {

            //$ishtml = ($html != strip_tags($html));
            $ishtml = (preg_match("/<[^<]+>/",$html,$m) != 0);
            //if ($ishtml) trace_log($html);
            //trace_log($m);

            if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.isHTML; ishtml=$ishtml");

        } catch (Exception $err) {
            scartLog::logLine("W-scartBrowserBasic.isHTML error: ".$err->getMessage());
        }

        return $ishtml;
    }

    static function isBase64code($data) {

        $isbase64code = false;

        try {

            $imgdata = explode(',',$data);
            if (count($imgdata) > 1) {

                $imgdata1 = explode (';', $imgdata[0]);
                if (in_array('base64', $imgdata1)) {
                    $isbase64code = (base64_decode($imgdata[1], true) !== false);
                }

            }
            if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.isBase64code; isbase64code=$isbase64code");

        } catch (Exception $err) {

            scartLog::logLine("E-scartBrowserBasic.isBase64code error: ".$err->getMessage());

        }

        return $isbase64code;
    }

    static function isImage($data) {

        $isimage = false;

        try {

            if ($data) {
                $img = @imagecreatefromstring($data);
                $isimage = ($img!==false);
            }
            if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.isImage; isimage=" . (($isimage) ? 'true':'false') );

        } catch (Exception $err) {

            scartLog::logLine("W-scartBrowserBasic.isImage error: ".$err->getMessage());

        }

        return $isimage;
    }

    /**
     * getImageData;
     * - src
     * - hash
     * - width
     * - height
     * - isBase64 (true/false)
     *
     * @param $data
     * @return $imgdata
     *
     */
    static function getImageData($src,$referer='') {

        $imgdata = '';

        try {

            // 2019/4/24/Gs: detect dataURI and skip

            if ($isDataURI = self::isDataURI($src)) {

                $imgdata = '';
                if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.getImageData; skip DataURI (src=$src)" );

            } else {

                /*
                // Check if BASE64 image string
                if ($isBase64 = self::isBase64code($data)) {
                    $imgsrc = base64_decode(explode(',',$data)[1]);
                } else {
                    $imgsrc = self::getData($data);
                }
                */

                $data = self::getData($src,$referer);
                $hash = scartBrowser::getImageHash($data);

                $imgsiz = @getimagesizefromstring($data);
                if ($imgsiz!==false) {

                    $mimetype = image_type_to_mime_type($imgsiz[2]);

                    $imgdata = array(
                        'src' => $src,
                        'type' => SCART_URL_TYPE_IMAGEURL,
                        'hash' => $hash,
                        'data' => $data,
                        'width' => $imgsiz[0],
                        'height' => $imgsiz[1],
                        'mimetype' => $mimetype,
                        'isBase64' => false,
                        'imgsize' => strlen($data),
                    );

                    if (self::useCached()) {
                        // FILL CACHE
                        $cache = Scrape_cache::getCache($hash);
                        if (!$cache) {
                            $cache = "data:" . $mimetype . ";base64," . base64_encode($data) ;
                            Scrape_cache::addCache($hash,$cache);
                            //scartLog::logLine("D-scartBrowserBasic.getImageData; url=$src; scrape cached filled " );
                        }
                    } else {
                        scartLog::logLine("D-scartBrowserBasic.getImageData; no caching of image ");
                    }

                }

            }


        } catch (Exception $err) {

            scartLog::logLine("W-scartBrowserBasic.getImageData error: ".$err->getMessage());
            $imgdata = false;

        }

        return $imgdata;
    }

    /**
     * getImageCache
     *
     * Get image base64 source data
     * Check cache -> if filed then use this
     *
     */

    public static function getImageCache($url,$hash='',$ifemptyshowurl=true,$imagenotfound=SCART_IMAGE_NOT_FOUND) {

        // always show url
        $imagebase65 = $url;
        return $imagebase65;
    }


    /**
     * getImages from link:
     * - image jpeg/png/...
     * - html page
     *
     * Return array with imgdata[]:
     * - src
     * - hash
     * - width
     * - height
     * - isBase64
     * - base
     * - valid
     *
     * Note: key is hash so duplicated images are ignored
     *
     * Note: $screenshot and $onlyscreenshot not used
     *
     *
     * @param $input (object)
     * @return array
     */

    public static function getImages($url,$referer='', $screenshot=true, $onlyscreenshot=false) {

        if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.getImages($url,$referer)");

        $images = array();

        // first parse link into base (init)
        $base = scartBrowser::parse_base($url);

        if ($base) {

            // load data from link
            $data = self::getData($url, $referer);

            if ($data) {

                // check if image -> If not then handle (possible) html
                if (!self::isImage($data)) {

                    // no image, check if html with images
                    libxml_use_internal_errors(true);
                    $doc = new \DOMDocument();
                    $doc->loadHTML($data);
                    libxml_clear_errors();

                    // get first BASE tag, if found
                    $tags = $doc->getElementsByTagName('base');
                    foreach ($tags as $tag) {
                        $base = $tag->getAttribute('href');
                        break;
                    }

                    foreach($doc->getElementsByTagName('img') as $img)
                    {
                        // Extract what we want
                        $img = self::make_absolute($img->getAttribute('src'), $base);

                        // Skip images without src
                        if(!$img) continue;

                        if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.getImages; found img src=$img");

                        // get image data
                        // NB: getImageData also check if it is an valid image
                        $imgdata = self::getImageData($img,$referer);
                        if ($imgdata) {
                            $imgdata['base'] = $base;
                            $imgdata['host'] = self::get_host($base);
                            $images[$imgdata['hash']] = $imgdata;
                        } elseif ($imgdata==='') {
                            // not valid image
                            if (SELF::$_debug) scartLog::logLine("D-scartBrowserBasic.getImages; skip not supported or invalid (temp) response");
                        }

                    }

                    // ** END BROWSE HTML **/

                    if (count($images) == 0) {

                        /**
                         * In html (?) no (valid) img tag can be found;
                         *
                         * a) indeed no img in html
                         * b) javascript in html with dynamic img
                         * c) login page of andere eerste pagina die display van image tegenhoud
                         *
                         */

                        scartLog::logLine("W-scartBrowserBasic.getImages; no (valid) image(s) found");

                    }

                } else {

                    // get image data
                    $imgdata = self::getImageData($url,$referer);
                    if ($imgdata) {
                        $imgdata['base'] = $base;
                        $imgdata['host'] = self::get_host($base);
                        $images[$imgdata['hash']] = $imgdata;
                    }

                }

            } else {

                // no data?
                scartLog::logLine("W-Warning; cannot load url (data) error: " . SELF::$_lasterror);

            }

        } else {

            // no base
            scartLog::logLine("W-Warning; url not valid, cannot extract host/domain");

        }

        return $images;
    }




}
