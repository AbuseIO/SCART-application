<?php

/**
 * scartBrowserDragan
 *
 * Wrapper for DataDragon from WEBIQ
 * NOTE: SCRAPE_CACHE IS ALWAYS USED FOR THIS PROVIDER
 *
 * 2019/12
 * Added
 *
 * Provider Functions
 * - getData(url,referer)
 * - getImages(url,referer,screenshot)
 * - getImageData(url)
 *
 */

namespace abuseio\scart\classes\browse;

use Config;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;

class scartBrowserDragon extends scartBrowser {

    private static $_useragent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0';
    private static $_curltimeout = 300;     // 5 minuut

    private static $_imageMimeTypes = [
        'image/svg+xml',
        'image/png',
        'image/jpeg',
        'image/tiff',
        'image/gif',
        'image/bmp',
        'image/webp',
    ];

    private static $_videoMimeTypes = [
        'application/ogg',
        'application/x-mpegurl',
        'application/vnd.apple.mpegurl',
        'video/3gpp',
        'video/3gppv',
        'video/mp4',
        'video/mpeg',
        'video/webm',
        'video/ogg',
        'video/x-flv',
        'video/x-m4v',
        'video/MP2T',
        'video/x-msvideo',
        'video/x-ms-wmv',
        'video/quicktime',
        'video/ms-asf',
        'video/quicktime',
    ];

    private static $_audioMimeTypes = [
        'audio/basic',
        'audio/L24',
        'audio/mid',
        'audio/mpeg',
        'audio/mp4',
        'audio/x-aiff',
        'audio/x-mpegurl',
        'audio/x-wav',
    ];

    /**
     * General START and STOP functions
     * Can be overrules by specific browser provider for optimalization
     */
    public static function startBrowser() {
        SELF::$_lasterror = '';
    }
    public static function stopBrowser() {
    }

    /**
     *
     * browse url and get data (url response)
     *
     * @param $url
     * @param string $referer
     * @return mixed
     */

    public static function getData($url, $referer='') {

        //curl "http://ert.bioffice01.nl:9123/fetch/default" --data "{""namespace"":""default"",""url"":""http://example.com/"",""scriptConfig"":{}}"

        $response = '';

        try {

            SELF::$_lasterror = '';

            $link = Systemconfig::get('abuseio.scart::browser.provider_api', '');

            if ($link) {

                $post = "{\"namespace\":\"default\",\"scriptConfig\":{},\"url\": \"" . $url ."\"}";

                $request = curl_init();
                $options = array(
                    CURLOPT_URL => $link,
                    CURLOPT_RETURNTRANSFER => true,
                    // No longer supported
                    // CURLOPT_USERAGENT, self::$_useragent,
                    CURLOPT_HEADER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => self::$_curltimeout,    // time-out on connect
                    CURLOPT_TIMEOUT => self::$_curltimeout,    // time-out on response
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $post,
                );
                if ($referer) {
                    $options[CURLOPT_REFERER] = $referer;
                }
                curl_setopt_array($request, $options);
                scartLog::logLine("D-scartBrowserDragon.getData; call $link for $url...");
                $response = curl_exec($request);
                if (curl_error($request)) {
                    SELF::$_lasterror = curl_error($request);
                    scartLog::logLine("W-scartBrowserDragon.getData; error: ".SELF::$_lasterror );
                    // zet on error
                    $response = '';
                } else {
                    scartLog::logLine("D-scartBrowserDragon.getData; link=$link, post=$post "  );
                    @curl_close($request);
                }

            } else {

                scartLog::logLine("E-scartBrowserDragon.getData; provider_api NOT set" );

            }

        } catch (Exception $err) {

            SELF::$_lasterror = $err->getMessage();
            // handle this error in calling function
            scartLog::logLine("W-scartBrowserDragon.getData error: line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );

        }

        return $response;
    }

    public static function getRawContent($url,$referer='') {


        $response = SELF::getData($url,$referer);

        if ($response) {

            try {

                $response = json_decode($response);

                $firstcontenttype = $response->log->entries[0]->response->content->mimeType ?? '?';
                scartLog::logDump("D-scartBrowserDragon.getRawContent; first contenttype is: ",$firstcontenttype);


            } catch (Exception $err) {

                SELF::$_lasterror = $err->getMessage();
                scartLog::logLine("E-scartBrowserDragon.getRawContent error: line=" . $err->getLine() . " in " . $err->getFile() . ", message: " . SELF::$_lasterror);

            }

        }

        return $response;
    }

        /**
     * browse url and get all images
     *
     * type=imageurl -> image
     * type=screenshot -> screenshot page
     *
     * @param $url
     * @param string $referer
     * @return mixed
     */

    public static function getImages($url,$referer='', $screenshot=true, $onlyscreenshot=false) {

        $images = [];

        $response = SELF::getData($url,$referer);

        if ($response) {

            try {

                $decode = json_decode($response);

                // get screenshot
                $pages = (isset($decode->log->pages)) ? $decode->log->pages : [];
                if (count($pages)) {

                    //$capture->screenshot = '(deleted)';
                    //scartLog::logLine("D-pages._captures" . print_r($pages[0]->_captures[0],true));

                    if ($screenshot && isset($pages[0]->_captures[0]->screenshot)) {

                        $capture = $pages[0]->_captures[0];

                        // add as type=screenshot

                        // base64 in text
                        $data = base64_decode($capture->screenshot);
                        $hash = scartBrowser::getImageHash($data);

                        $mimeType = 'image/png';

                        if (self::useCached()) {
                            // FILL CACHE
                            $cache = Scrape_cache::getCache($hash);
                            if (!$cache) {
                                $cache = "data:" . $mimeType . ";base64," . $capture->screenshot;
                                Scrape_cache::addCache($hash, $cache);
                                //scartLog::logLine("D-scartBrowserDragon.getImages; url=$url; scrape cached filled ");
                            }
                        }

                        $base = self::parse_base($url);

                        if (isset($capture->size->width)) {
                            $width = $capture->size->width;
                            $height = $capture->size->height;
                        }

                        scartLog::logLine("D-scartBrowserDragon.getImages; got screenshot; mimeType=$mimeType, url=$url" );

                        $image = [
                            'src' => $url,
                            'type' => SCART_URL_TYPE_SCREENSHOT,
                            'host' => self::get_host($base),
                            'data' => $data,
                            'base' => $base,
                            'hash' => $hash,
                            'width' => $width,
                            'height' => $height,
                            'mimetype' => $mimeType,
                            'isBase64' => false,
                            'imgsize' => strlen($data),
                        ];
                        $images[] = $image;

                    } else {

                        scartLog::logLine("D-scartBrowserDragon.getImages; skip making screenshot" );

                    }
                }

                // get response entries
                $entries = $decode->log->entries ?? [];

                if (count($entries) > 0) {

                    if ($onlyscreenshot) {
                        // only active when website (text/html) url
                        $firstcontenttype = $entries[0]->response->content->mimeType ?? '?';
                        $onlyscreenshot = ($firstcontenttype == 'text/html');
                        if (!$onlyscreenshot) {
                            // remove screenshot
                            $images = [];
                        }
                    }

                    if ($onlyscreenshot) {

                        scartLog::logLine("D-scartBrowserDragon.getImages; only screenshot is set; content='text/html', skip loading rest of the entries" );

                    } else {

                        // each entry
                        foreach ($entries AS $entry) {

                            $url = (isset($entry->request->url)) ? $entry->request->url : '';

                            if ($url) {

                                $ip = (isset($entry->serverIPAddress)) ? $entry->serverIPAddress : '?';

                                $status = (isset($entry->response->status)) ? $entry->response->status : '';
                                $content = (isset($entry->response->content)) ? $entry->response->content : '';

                                if ($content) {

                                    $encoding = (isset($content->encoding)) ? $content->encoding : '?';
                                    $mimeType = (isset($content->mimeType)) ? $content->mimeType : '?';
                                    $text = (isset($content->text)) ? $content->text : '';
                                    $size = strlen($text);
                                    $mem = memory_get_usage();
                                    //scartLog::logLine("D-scartBrowserDragon.getImages; status=$status, size=$size, memory=$mem, mimeType=$mimeType, encoding=$encoding, ip=$ip, url=$url" );

                                    if (in_array($mimeType,SELF::$_imageMimeTypes) ) {

                                        if ($text != '' && $encoding == 'base64') {

                                            //scartLog::logLine("D-scartBrowserDragon.getImages; FOUND IMAGE; status=$status, mimeType=$mimeType, encoding=$encoding, ip=$ip, url=$url" );

                                            // base64 in text
                                            $data = base64_decode($text);
                                            $hash = scartBrowser::getImageHash($data);

                                            // if not set, try ourself
                                            $imgsiz = @getimagesizefromstring($data);
                                            if ($imgsiz !== false) {
                                                $width = $imgsiz[0];
                                                $height = $imgsiz[1];

                                                if (self::useCached()) {
                                                    // FILL CACHE
                                                    $cache = Scrape_cache::getCache($hash);
                                                    if (!$cache) {
                                                        $cache = "data:" . $content->mimeType . ";base64," . $text;
                                                        Scrape_cache::addCache($hash, $cache);
                                                        //scartLog::logLine("D-scartBrowserDragon.getImages; url=$url; scrape cached filled ");
                                                    }
                                                }

                                                $base = self::parse_base($url);

                                                $image = [
                                                    'src' => $url,
                                                    'type' => SCART_URL_TYPE_IMAGEURL,
                                                    'host' => self::get_host($base),
                                                    'data' => $data,
                                                    'base' => $base,
                                                    'hash' => $hash,
                                                    'width' => $width,
                                                    'height' => $height,
                                                    'mimetype' => $mimeType,
                                                    'isBase64' => false,
                                                    'imgsize' => strlen($data),
                                                    //'data' => $content->text,
                                                ];
                                                $images[] = $image;

                                            } else {
                                                scartLog::logLine("W-scartBrowserDragon.getImages can NOT get imagesize; no valid image url=$url");
                                            }

                                        } else {
                                            if ($text=='') {
                                                scartLog::logLine("W-scartBrowserDragon.getImages; url=$url; empty TEXT (data) field ");
                                            } else {
                                                scartLog::logLine("W-scartBrowserDragon.getImages; url=$url; NOT encodeing=base64 ");
                                            }
                                        }

                                    }

                                    // VIDEO -> only get url, not scrape cache

                                    if (in_array($mimeType,SELF::$_videoMimeTypes) ) {

                                        if ($encoding == 'base64') {

                                            //scartLog::logLine("D-scartBrowserDragon.getImages; FOUND VIDEO; status=$status, mimeType=$mimeType, encoding=$encoding, ip=$ip, url=$url" );

                                            // text containts image of video

                                            //if (isset($content->text)) $content->text = ''; scartLog::logLine("D-scartBrowserDragon.video content: " . print_r($content, true)) ;

                                            // create special unique HASH
                                            // base on url and ip
                                            $text .= $url . $ip;
                                            $hash = sprintf('%s%016d', md5($text),strlen($text));   // 48 tekens

                                            $width = $height = 100;

                                            $base = scartBrowser::parse_base($url);

                                            $image = [
                                                'src' => $url,
                                                'type' => SCART_URL_TYPE_VIDEOURL,
                                                'host' => self::get_host($base),
                                                'data' => '',
                                                'base' => $base,
                                                'hash' => $hash,
                                                'width' => $width,
                                                'height' => $height,
                                                'mimetype' => $mimeType,
                                                'isBase64' => false,
                                                'imgsize' => 0,
                                                //'data' => $content->text,
                                            ];
                                            $images[] = $image;

                                        }

                                    }

                                }

                            }


                        }

                    }

                }

                scartLog::logLine("D-scartBrowserDragon.getImages; image(s) scraped: " . count($images) );

            } catch (Exception $err) {

                SELF::$_lasterror = $err->getMessage();
                scartLog::logLine("E-scartBrowserDragon.getImages error: line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );

            }

        }

        return $images;
    }

    /**
     * getImageData
     *
     * Get image (url) direct
     *
     * DataDragin -> reuse getImages; first image = image
     *
     * @param $data
     * @param string $referer
     * @return mixed
     */
    public static function getImageData($url,$referer='') {

        $images =  SELF::getImages($url,$referer);
        // first one
        return ($images) ? $images[0] : false;
    }

}
