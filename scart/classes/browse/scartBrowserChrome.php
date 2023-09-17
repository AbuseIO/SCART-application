<?php

/**
 * scartBrowserChrome
 *
 * NOTE: SCRAPE_CACHE IS ALWAYS USED FOR THIS PROVIDER
 *
 * Provider Functions
 * - getImages(url,referer,screenshot)
 *
 */

namespace abuseio\scart\classes\browse;

use Cms\Classes\Page;
use Config;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\helpers\scartLog;
use HeadlessChromium\Clip;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Communication\Connection;
use HeadlessChromium\Exception\OperationTimedOut;

class scartBrowserChrome extends scartBrowser {

    private static $_useragent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36';
    private static $_timeout = 60000;     // timeout for waiting on response (load) of browser content

    private static $_imageMimeTypes = [
        'image/svg+xml',
        'image/png',
        'image/jpeg',
        'image/tiff',
        'image/gif',
        'image/bmp',
        'image/webp',
        'image/avif',
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

    private static $_browser = null;

    public static function getChromeBrowser() {

        if (self::$_browser==null) {
            $browserFactor = new BrowserFactory();
            $browserFactor->setOptions([
                'noSandbox' => true,
                'headless' => true,
                'noProxyServer' => true,
                'userCrashDumpsDir' => '/tmp/crashdump',
                'windowSize'   => [1920, 1080],
                'enableImages' => true,
                // to-do; rotating user agent?
                'userAgent' => SELF::$_useragent,
                // max scraping
                'ignoreCertificateErrors' => true,
                // general timeout
                'sendSyncDefaultTimeout' => SELF::$_timeout,
                // set some flags preventing the use of shared memory and caching
                'customFlags' => [
                    '--disable-dev-shm-usage',
                    '--aggressive-cache-discard',
                    '--disable-cache',
                    '--disable-application-cache',
                    '--disable-offline-load-stale-cache',
                ],
            ]);
            if (SELF::$_debug) {
                $browserFactor->addOptions([
                    'debugLogger' => 'php://stdout',
                    'connectionDelay' => 0.5,
                ]);
                scartLog::logDump("D-scartBrowserChrome.getChromeBrowser; options set:",$browserFactor->getOptions());
            }
            scartLog::logLine("D-scartBrowserChrome.getChromeBrowser; create headless browser (1920,1080); user-agent='".SELF::$_useragent."' ");
            self::$_browser = $browserFactor->createBrowser();
        } else {
            scartLog::logLine("D-scartBrowserChrome.getChromeBrowser; reuse saved browserFactor->createBrowser()");
        }
        return self::$_browser;
    }

    public static function startBrowser($debug=false) {
        SELF::$_lasterror = '';
        SELF::$_debug = $debug;
    }

    public static function stopBrowser() {
        try {
            if (self::$_browser!=null) {
                self::$_browser->close();
                self::$_browser = null;
                scartLog::logLine("D-scartBrowserChrome.stopBrowser; browser unloaded" );
            }
        } catch (Exception $err) {
            scartLog::logLine("W-scartBrowserChrome.stopBrowser error: ". $err->getMessage() );
        }
    }

    static function clearBrowser($page) {

        try {
            $page->getSession()->sendMessageSync(
                new Message('Network.clearBrowserCache'),
                SELF::$_timeout
            );
            $page->getSession()->sendMessageSync(
                new Message('Network.clearBrowserCookies'),
                SELF::$_timeout
            );
            scartLog::logLine("D-scartBrowserChrome.clearBrowser; cache/cookies cleared" );
        } catch (Exception $err) {
            scartLog::logLine("W-scartBrowserChrome.clearBrowser error: ". $err->getMessage() );
        }
    }


    private static function getImageScreenshot($page,$url,$imgelm=false) {

        $image = false;

        Try {

            $heightVars = [
                'document.body.scrollHeight',
                'document.body.offsetHeight',
                'document.documentElement.clientHeight',
                'document.documentElement.scrollHeight',
                'document.documentElement.offsetHeight'];
            $pageHeigth = 0;
            foreach ($heightVars as $heightVar) {
                // some javascript vars may not be available - skip error
                try {
                    $heigth = $page->evaluate($heightVar)->getReturnValue();
                    if ($heigth > $pageHeigth) $pageHeigth = $heigth;
                } catch (\Exception $err) {
                    SELF::$_lasterror = $err->getMessage();
                    scartLog::logLine("W-scartBrowserChrome.getImageScreenshot: cannot get '$heightVar'; error: ".$err->getMessage());
                }
            }

            if ($pageHeigth > 0) {

                scartLog::logLine("D-scartBrowserChrome.getImageScreenshot; window.scrollTo(0,$pageHeigth) (for lazy loading images)");
                $page->evaluate('window.scrollTo(0,'.$pageHeigth.')')->waitForResponse();

                if ($imgelm) {
                    $pos = $imgelm->getPosition();
                    $clip = new Clip($pos->getX(), $pos->getY(), $pos->getWidth(), $pos->getHeight());
                    scartLog::logLine("D-scartBrowserChrome.getImageScreenshot; use image Clip(x,y,w,h)=(".$pos->getX().", ".$pos->getY().", ".$pos->getWidth().", ".$pos->getHeight().")");
                    // Note: clip area only works in jpeg format
                    $shotoptions = [
                        'clip' => $clip,
                        'format' => 'jpeg',
                        'quality' => 90,
                    ];
                } else {
                    $clip = $page->getFullPageClip();
                    $shotoptions = [
                        'captureBeyondViewport' => true,
                        'clip' => $clip,
                        'format' => 'png',
                    ];
                }
                //scartLog::logDump("D-scartBrowserChrome.getImageScreenshot; clip area=",$clip);
                $screenshot = $page->screenshot($shotoptions);
                $data64 = $screenshot->getBase64(SELF::$_timeout);

                $data = base64_decode($data64);
                $mimeType = 'image/'.$shotoptions['format'];
                $hash = scartBrowser::getImageHash($data);
                $base = scartBrowser::parse_base($url);
                $width = $clip->getWidth();
                $height = $clip->getHeight();

                if (self::useCached()) {
                    $cache = Scrape_cache::getCache($hash);
                    if (!$cache) {
                        $cache = "data:" . $mimeType . ";base64," . $data64;
                        Scrape_cache::addCache($hash, $cache);
                    }
                }

                $imgsize = strlen($data);

                scartLog::logLine("D-scartBrowserChrome.getImageScreenshot; got screenshot; mimeType=$mimeType, width=$width, height=$height, imgsize=$imgsize" );
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
                    'imgsize' => $imgsize,
                ];

            } else {
                scartLog::logLine("W-scartBrowserChrome.getImageScreenshot: pageHeigth=$pageHeigth; cannot get screenshot");
            }

        } catch (OperationTimedOut $err) {
            // timeout is server offline or dead -> image(s) not found
            scartLog::logLine("W-scartBrowserChrome.getImageScreenshot timeout message: ".$err->getMessage());
            $image - false;
        } catch (\Exception $err) {
            SELF::$_lasterror = $err->getMessage();
            scartLog::logLine("E-scartBrowserChrome.getImageScreenshot error: line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );
        }

        return $image;
    }

    private static function getImageFromContent(\HeadlessChromium\Page $page,$url,$referer='') {

        $image = false;

        Try {

            $use_curl = Systemconfig::get('abuseio.scart::browser.use_curl_for_image', false);

            if ($use_curl) {

                scartLog::logLine("D-scartBrowserChrome.getImageFromContent; use curl for fetch image data");
                $data64 = 'dummy';
                $base64Encoded = false;

            } else {

                scartLog::logLine("D-scartBrowserChrome.getImageFromContent; use chrome resources for fetch image data");

                $content = $page->getSession()->sendMessageSync(
                    new Message('Page.getResourceContent',
                        [
                            'frameId' => $page->getFrameManager()->getMainFrame()->getFrameId(),
                            'url' => $url,
                        ]),
                    SELF::$_timeout
                );
                //scartLog::logDump("D-scartBrowserChrome.getImages; content=",$content);

                $data64 = $content->getResultData('content');
                $base64Encoded = $content->getResultData('base64Encoded');

            }

            if (empty($data64)) {

                scartLog::logLine("W-scartBrowserChrome.getImageFromContent; content is empty (?) - fallback to (image) screenshot");

                // get image position -> put into Clip -> getImageScreenshot from this clip
                $images = $page->dom()->querySelectorAll('img');
                $imgelm = (isset($images[0])) ? $images[0] : false;

                // get schreenshot
                $image = self::getImageScreenshot($page,$url,$imgelm);
                $image['type'] = SCART_URL_TYPE_IMAGEURL;

            } else {

                scartLog::logLine("D-scartBrowserChrome.getImageFromContent; base64Encoded=$base64Encoded");
                if ($base64Encoded) {
                    // decode for image data
                    $data = base64_decode($data64);
                } else {
                    // direct (own) curl raw data reading
                    $data = self::getCurlData($url,$referer);
                    $data64 = base64_encode($data);
                }

                $hash = scartBrowser::getImageHash($data);
                $base = scartBrowser::parse_base($url);

                // try to get image size; first directly from DOM, secondly by php imagesize function

                $width = $height = $mimeType = $source = '';

                $images = $page->dom()->querySelectorAll('img');
                if (isset($images[0])) {
                    $source = "DOM";
                    $pos = $images[0]->getPosition();
                    $width = $pos->getWidth();
                    $height = $pos->getHeight();
                    $mimeType = $page->evaluate('document.contentType')->getReturnValue();
                } else {
                    $source = "getimagesizefromstring";
                    $imgsiz = @getimagesizefromstring($data);
                    if ($imgsiz !== false) {
                        $width = $imgsiz[0];
                        $height = $imgsiz[1];
                        $mimeType = image_type_to_mime_type($imgsiz[2]);
                    }
                }

                if ($mimeType) {

                    if (self::useCached()) {
                        // FILL CACHE
                        $cache = Scrape_cache::getCache($hash);
                        if (!$cache) {
                            $cache = "data:" . $mimeType . ";base64," . $data64;
                            Scrape_cache::addCache($hash, $cache);
                        }
                    }

                    $imgsize = strlen($data);

                    scartLog::logLine("D-scartBrowserChrome.getImageFromContent; url '$url'; mimeType=$mimeType, width=$width, height=$height, imgsize=$imgsize  (source=$source)" );
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
                        'imgsize' => $imgsize,
                    ];

                } else {
                    scartLog::logLine("W-scartBrowserChrome.getImageFromContent; url '$url'; can NOT get mime, width and/or height; not a valid image url=$url - skip");
                }

            }

        } catch (OperationTimedOut $err) {
            // timeout is server offline or dead -> image(s) not found
            scartLog::logLine("W-scartBrowserChrome.getImageFromContent timeout message: ".$err->getMessage());
            $image - false;
        } catch (\Exception $err) {
            SELF::$_lasterror = $err->getMessage();
            scartLog::logLine("E-scartBrowserChrome.getImageFromContent error: line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );
        }

        return $image;
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
        $step = 1;

        try {

            SELF::$_lasterror = '';
            $urlbase = self::parse_base($url);

            // get headless chrome
            $browser = self::getChromeBrowser();

            // creates a new page and navigate to the URL
            $page = $browser->createPage();
            if ($referer) $page->setExtraHTTPHeaders([
                'referer' => $referer,
            ]);

            scartLog::logLine("D-scartBrowserChrome.getImages; url($url)->waitForNavigation() ");
            $page->navigate($url)->waitForNavigation(\HeadlessChromium\Page::LOAD,SELF::$_timeout);

            $step = 2;
            $contentType = $page->evaluate('document.contentType')->getReturnValue(SELF::$_timeout);

            if ($contentType == 'text/html') {

                scartLog::logLine("D-scartBrowserChrome.getImages; got webpage text/html ");

                $step = 3;

                // catch errors, but ignore errors because of false/corrupt html documents
                try {

                    // catch possible javascript errors - ignore
                    $evaluation = $page->evaluate('document.documentElement.innerHTML')->getReturnValue(SELF::$_timeout);
                    //scartLog::logDump("D-scartBrowserChrome.evaluation; ",$evaluation);
                    scartLog::logLine("D-scartBrowserChrome.getImages; document evaluated");

                } catch (OperationTimedOut $err) {
                    // timeout is warning
                    scartLog::logLine("W-scartBrowserChrome.getImages evaluate timeout: after step=$step, message: ".$err->getMessage() );
                } catch (\Exception $err) {
                    // evaluation error is warning (javascript can be corrupt)
                    scartLog::logLine("W-scartBrowserChrome.getImages evaluate error: after step=$step, line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );
                }

                if (SELF::$_lasterror == '') {

                    // webpage
                    if ($screenshot) {

                        $step = 4;
                        // Note: in making this screenshot we also scroll to the bottom to force lazy loading
                        $image = self::getImageScreenshot($page,$url);
                        if ($image && self::$_lasterror == '') $images[] = $image;

                    } else {
                        scartLog::logLine("D-scartBrowserChrome.getImages; skip making screenshot");
                    }

                    if (!$onlyscreenshot) {

                        $step = 5;
                        // get resource of webpage directly from chrome devtools environment
                        $resources = $page->getSession()->sendMessageSync(
                            new Message('Page.getResourceTree'),
                            SELF::$_timeout
                        );
                        $framedata = $resources->getData();
                        if (isset($framedata['result']['frameTree']['resources'])) {

                            //scartLog::logDump("D-scartBrowserChrome.getImages; framedata=",$framedata);
                            $resources = $framedata['result']['frameTree']['resources'];
                            //scartLog::logDump("D-scartBrowserChrome.getImages; resources=",$resources);

                            foreach ($resources as $resource) {

                                $resource = (object) $resource;

                                scartLog::logLine("D-scartBrowserChrome.getImages; got resource; type=$resource->type, mimeType=$resource->mimeType, url=$resource->url");

                                // if image and supported mimeType and NOT datauRI
                                if ($resource->type == 'Image' && in_array($resource->mimeType,self::$_imageMimeTypes) && !scartBrowser::isDataURI($resource->url)) {

                                    $subpage = null;

                                    // skip errors on sub urls
                                    try {

                                        $subpage = $browser->createPage();
                                        // mainurl als referer
                                        if ($referer) $subpage->setExtraHTTPHeaders([
                                            'referer' => $urlbase,
                                        ]);
                                        scartLog::logLine("D-scartBrowserChrome.getImages; url($resource->url)->waitForNavigation() ");
                                        $step = 6;
                                        $subpage->navigate($resource->url)->waitForNavigation(\HeadlessChromium\Page::LOAD,SELF::$_timeout);
                                        $step = 7;
                                        $image = self::getImageFromContent($subpage,$resource->url,$referer);
                                        if ($image && self::$_lasterror == '') $images[] = $image;

                                    } catch (\Exception $err) {
                                        scartLog::logLine("W-scartBrowserChrome.getImages; skip lookup selectorimage, error: ".$err->getMessage());
                                    }

                                    if ($subpage!=null) $subpage->close();
                                }

                            }

                        } else {
                            scartLog::logLine("W-scartBrowserChrome.getImages; cannot get frame (page) resources ");
                        }

                    } else {
                        scartLog::logLine("D-scartBrowserChrome.getImages; take only the screenshot");
                    }

                }

            } elseif (in_array($contentType,self::$_imageMimeTypes)) {

                $step = 8;

                // image
                scartLog::logLine("D-scartBrowserChrome.getImages; got image '$contentType'");

                $image = self::getImageFromContent($page,$url,$referer);
                if ($image && self::$_lasterror == '') $images[] = $image;

            } elseif (in_array($contentType,self::$_videoMimeTypes)) {

                $step = 9;

                // video
                scartLog::logLine("D-scartBrowserChrome.getImages; got video '$contentType'");

                $image = self::getImageScreenshot($page,$url);
                if ($image && self::$_lasterror == '') $images[] = $image;

            } else {

                scartLog::logLine("W-scartBrowserChrome.getImages; UNKNOWN contentType '$contentType' - skip");

            }

            // clear cache/cookies
            self::clearBrowser($page);

            // close page
            $page->close();

        } catch (OperationTimedOut $err) {
            // timeout is server offline or dead -> image(s) not found
            scartLog::logLine("W-scartBrowserChrome.getImages timeout '$url': after step=$step, message: ".$err->getMessage());
            $images = [];
        } catch (\Exception $err) {
            SELF::$_lasterror = $err->getMessage();
            scartLog::logLine("E-scartBrowserChrome.getImages error: after step=$step, line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );
        }

        return $images;
    }

    /**
     *
     *
     * @param $link
     * @param string $referer
     */

    static function getCurlData($link, $referer='') {

        $response = '';

        try {

            scartLog::logLine("D-scartBrowserChrome.getCurlData; read link=$link");
            $request = curl_init();
            $options = array(
                CURLOPT_URL => $link,
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
                scartLog::logLine("W-scartBrowserChrome.getCurlData error: ".SELF::$_lasterror );
            } else {
                SELF::$_lasterror = '';
            }
            curl_close($request);

        } catch (Exception $err) {

            SELF::$_lasterror = $err->getMessage();
            scartLog::logLine("W-scartBrowserChrome.getCurlData error: ".SELF::$_lasterror );

        }

        return $response;
    }

    /** To-Do; clean below because obsolute (?) **/

    /**
     * getImageData
     * Get image (url) direct
     *
     * @param $data
     * @param string $referer
     * @return mixed
     */
    public static function getImageData($url,$referer='') {

        $images =  SELF::getImages($url,$referer);
        // first one
        return (!empty($images)) ? $images[0] : false;
    }

    /**
     * TESTING browse url and get data (url response)
     *
     * @param $url
     * @param string $referer
     * @return mixed
     */

    public static function getData($url, $referer='') {

        $response = '';

        /**
         * TESTING TESTING TESTING CHROMIUM
         *
         */

        try {

            SELF::$_lasterror = '';

            // starts headless chrome
            $browser = self::getChromeBrowser();

            // creates a new page and navigate to an URL
            $page = $browser->createPage();
            if ($referer) $page->setExtraHTTPHeaders([
                'referer' => $referer,
            ]);

            scartLog::logLine("D-scartBrowserChrome.getData; url($url)->waitForNavigation() ");
            $page->navigate($url)->waitForNavigation(\HeadlessChromium\Page::LOAD,SELF::$_timeout);

            $contentType = $page->evaluate('document.contentType')->getReturnValue();
            scartLog::logLine("D-scartBrowserChrome; contentType=$contentType");


            // evaluate script in the browser
            //$evaluation = $page->evaluate('document.documentElement.innerHTML');
            //scartLog::logDump("D-scartBrowserChrome.evaluation; ",$evaluation);
            //scartLog::logLine("D-scartBrowserChrome.evaluated");

            //$pageTitle = $page->evaluate('document.title')->getReturnValue();
            //scartLog::logLine("D-scartBrowserChrome.getData; pageTitle='$pageTitle' ");

            $heightVars = [
                'document.body.scrollHeight',
                'document.body.offsetHeight',
                'document.documentElement.clientHeight',
                'document.documentElement.scrollHeight',
                'document.documentElement.offsetHeight'];
            $pageHeigth = 0;
            foreach ($heightVars as $heightVar) {
                $heigth = $page->evaluate($heightVar)->getReturnValue();
                if ($heigth > $pageHeigth) $pageHeigth = $heigth;
            }
            scartLog::logLine("D-scartBrowserChrome.getData; getPageHeight()=$pageHeigth ");

            /*
            scartLog::logLine("D-scartBrowserChrome.getData; addPreScript ");
            $page->addScriptTag(['content' => '
function getPageHeight() {
    var html = document.documentElement;
    var pageHeight = Math.max(document.body.scrollHeight, document.body.offsetHeight, html.clientHeight, html.scrollHeight, html.offsetHeight);
    return pageHeight;
}
function getImages() {
    var images = document.getElementsByTagName("img");
    var srcList = [], imgobj= {};
    for(var i = 0; i < images.length; i++) {
        imgobj = {};
        imgobj.src = images[i].src;
        imgobj.width = images[i].width;
        imgobj.height = images[i].height;
        srcList.push(imgobj);
    }
    return srcList;
}
'])->waitForResponse();

            //$pageHeigth = $page->evaluate('findHighestNode(document.documentElement.childNodes)')->getReturnValue();
            //scartLog::logLine("D-scartBrowserChrome.getData; findHighestNode()=$pageHeigth ");
            //$goBottom = $page->evaluate('scrollBottom()')->waitForResponse()->getReturnValue();
            //scartLog::logLine("D-scartBrowserChrome.getData; scrollBottom()=$goBottom ");

            $pageHeigth = $page->evaluate('getPageHeight()')->getReturnValue();
            scartLog::logLine("D-scartBrowserChrome.getData; getPageHeight()=$pageHeigth ");

            $images = $page->evaluate('getImages()')->getReturnValue();
            scartLog::logDump("D-scartBrowserChrome.getImages=",$images);

            */


            $images = $page->dom()->querySelectorAll('img');
            //scartLog::logDump("D-scartBrowserChrome.getImages=",$images);
            foreach ($images as $image) {
                scartLog::logDump("D-scartBrowserChrome.image.src=",$image->getAttribute('src'));
            }


            $format = 'png';
            $clip = $page->getFullPageClip();
            scartLog::logDump("D-scartBrowserChrome.getData; getFullPageClip=",$clip);

            //$page->setViewport($clip->getWidth(),$clip->getHeight())->await();

            //$clip = new Clip(0,0,800,800);
            //scartLog::logDump("D-scartBrowserChrome.getData; clip=",$clip);

            //scartLog::logLine("D-scartBrowserChrome.getData; get screenshot base64 ");
            $screenshot = $page->screenshot([
                'captureBeyondViewport' => true,
                'clip' => $clip,
                'format' => $format, // default to 'png' - possible values: 'png', 'jpeg',
            ]);
            /*
            $screenshot = $page->screenshot([
                'format' => 'jpeg', // default to 'png' - possible values: 'png', 'jpeg',
                'quality' => 80,
            ]);
            */

            //scartLog::logLine("D-scartBrowserChrome.getData; getBase64()");
            //$data = $screenshot->getBase64();

            scartLog::logLine("D-scartBrowserChrome.getData; save into 'screenshot.$format' ");
            $screenshot->saveToFile('screenshot.'.$format,30000);

            //$response = $screenshot->getBase64(20000);


            $browser->close();


        } catch (Exception $err) {
            SELF::$_lasterror = $err->getMessage();
            // handle this error in calling function
            scartLog::logLine("W-scartBrowserChrome.getData error: line=".$err->getLine()." in ".$err->getFile().", message: ".SELF::$_lasterror );
        }

        return $response;
    }


}
