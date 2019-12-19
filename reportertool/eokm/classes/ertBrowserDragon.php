<?php

/**
 * ertBrowserDragan
 *
 * Wrapper for DataDragon from WEBIQ
 * NOTE: SCRAPE_CACHE IS ALWAYS USED FOR THIS PROVIDER
 *
 * 2019/12
 * Added
 *
 * Provider Functions
 * - getData(url,referer)
 * - getImages(url)
 * - getImageData(url)
 *
 */

namespace reportertool\eokm\classes;

use Config;
use reportertool\eokm\classes\ertBrowser;
use ReporterTool\EOKM\Models\Scrape_cache;

class ertBrowserDragon extends ertBrowser {

    private static $_useragent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:67.0) Gecko/20100101 Firefox/67.0';
    private static $_lasterror = '';

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

            $link = Config::get('reportertool.eokm::browser.provider_api', '');

            if ($link) {

                $post = "{\"namespace\":\"default\",\"scriptConfig\":{},\"url\": \"" . $url ."\"}";

                $request = curl_init();
                $options = array(
                    CURLOPT_URL => $link,
                    CURLOPT_RETURNTRANSFER => TRUE,
                    CURLOPT_USERAGENT, self::$_useragent,
                    CURLOPT_HEADER => FALSE,
                    CURLOPT_SSL_VERIFYHOST => FALSE,
                    CURLOPT_SSL_VERIFYPEER => FALSE,
                    CURLOPT_FOLLOWLOCATION => TRUE,
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_POST => TRUE,
                    CURLOPT_POSTFIELDS => $post,
                );
                if ($referer) {
                    $options[CURLOPT_REFERER] = $referer;
                }
                curl_setopt_array($request, $options);
                $response = curl_exec($request);
                if (curl_error($request)) {
                    SELF::$_lasterror = curl_error($request);
                    ertLog::logLine("E-ertBrowserDragon.getData; error: ".SELF::$_lasterror );
                } else {
                    ertLog::logLine("D-ertBrowserDragon.getData; link=$link, post=$post "  );
                    SELF::$_lasterror = '';
                }
                @curl_close($request);

            } else {

                ertLog::logLine("E-ertBrowserDragon.getData; provider_api NOT set" );

            }

        } catch (Exception $err) {

            SELF::$_lasterror = $err->getMessage();
            ertLog::logLine("E-ertBrowserDragon.getData error: line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );

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

    public static function getImages($url,$referer='') {

        $images = [];

        $response = SELF::getData($url,$referer);

        if ($response) {

            try {

                $decode = json_decode($response);

                // get screenshot
                $pages = (isset($decode->log->pages)) ? $decode->log->pages : [];
                if (count($pages)) {
                    //$capture = $pages[0]->_captures[0];
                    //$capture->screenshot = '(deleted)';
                    //ertLog::logLine("D-pages._captures" . print_r($pages[0]->_captures[0],true));
                    if (isset($pages[0]->_captures[0]->screenshot)) {

                        // add as type=screenshot


                    }
                }

                // get response entries
                $entries = (isset($decode->log->entries)) ? $decode->log->entries : [];

                if (count($entries) > 0) {

                    //ertLog::logLine("D-images: \n" . print_r($entries, true) );

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
                                //ertLog::logLine("D-ertBrowserDragon.getImages; status=$status, size=$size, memory=$mem, mimeType=$mimeType, encoding=$encoding, ip=$ip, url=$url" );

                                if (in_array($mimeType,SELF::$_imageMimeTypes) ) {

                                    if ($text != '' && $encoding == 'base64') {

                                        //ertLog::logLine("D-ertBrowserDragon.getImages; FOUND IMAGE; status=$status, mimeType=$mimeType, encoding=$encoding, ip=$ip, url=$url" );

                                        // base64 in text
                                        $data = base64_decode($text);
                                        $hash = ertBrowser::getImageHash($data);

                                        // if not set, try ourself
                                        $imgsiz = @getimagesizefromstring($data);
                                        if ($imgsiz !== false) {
                                            $width = $imgsiz[0];
                                            $height = $imgsiz[1];

                                            if (self::useCached()) {
                                                // FILL CACHE
                                                $cache = Scrape_cache::getCache($hash);
                                                if (!$cache) {
                                                    $cache = "data:" . $content->mimeType . ";base64," . base64_encode($data);
                                                    Scrape_cache::addCache($hash, $cache);
                                                    //ertLog::logLine("D-ertBrowserDragon.getImages; url=$url; scrape cached filled ");
                                                }
                                            }

                                            $base = self::parse_base($url);

                                            $image = [
                                                'src' => $url,
                                                'type' => ERT_URL_TYPE_IMAGEURL,
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
                                            ertLog::logLine("W-ertBrowserDragon.getImages can NOT get imagesize; no valid image url=$url");
                                        }

                                    } else {
                                        if ($text=='') {
                                            ertLog::logLine("W-ertBrowserDragon.getImages; url=$url; empty TEXT (data) field ");
                                        } else {
                                            ertLog::logLine("W-ertBrowserDragon.getImages; url=$url; NOT encodeing=base64 ");
                                        }
                                    }

                                }

                                // VIDEO -> only get url, not scrape cache

                                if (in_array($mimeType,SELF::$_videoMimeTypes) ) {

                                    if ($encoding == 'base64') {

                                        //ertLog::logLine("D-ertBrowserDragon.getImages; FOUND VIDEO; status=$status, mimeType=$mimeType, encoding=$encoding, ip=$ip, url=$url" );

                                        // text containts image of video

                                        //if (isset($content->text)) $content->text = ''; ertLog::logLine("D-ertBrowserDragon.video content: " . print_r($content, true)) ;

                                        // create special unique HASH
                                        // base on url and ip
                                        $text .= $url . $ip;
                                        $hash = sprintf('%s%016d', md5($text),strlen($text));   // 48 tekens

                                        $width = $height = 100;

                                        $base = ertBrowser::parse_base($url);

                                        $image = [
                                            'src' => $url,
                                            'type' => ERT_URL_TYPE_VIDEOURL,
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

                ertLog::logLine("D-ertBrowserDragon.getImages; image(s) scraped: " . count($images) );

            } catch (Exception $err) {

                SELF::$_lasterror = $err->getMessage();
                ertLog::logLine("E-ertBrowserDragon.getImages error: line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );

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
