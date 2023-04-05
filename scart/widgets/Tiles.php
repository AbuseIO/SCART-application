<?php
namespace abuseio\scart\widgets;

use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\helpers\scartImage;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\scheduler\scartScheduler;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\Controllers\Grade;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\widgets\tiles\classes\helpers\Image;
use abuseio\scart\widgets\tiles\classes\helpers\Input;
use abuseio\scart\widgets\tiles\classes\helpers\Form;
use abuseio\scart\widgets\tiles\classes\helpers\Lists;
use Backend\Classes\WidgetBase;
use Illuminate\Support\Facades\Session;

class Tiles extends WidgetBase
{
    /**
     * @var constant The default error for empty items list
     */
    const DEFAULT_ERROR = 'Items list empty. First add items to the related model.';

    /**
     * @var string A unique alias to identify this widget.
     */
    protected $defaultAlias = 'tiles';

    /**
     * @var string Default error for empty items list
     */
    protected $defaultError = self::DEFAULT_ERROR;

    // input items
    private $inputItems;

    // Default values
    private $colnum;
    private $coladdnum          = 0;
    private $coltxtsize         = 10;
    private $colspan            = 2;
    private $colboxheightsize   = 400;
    private $colimgsize         = 380;
    private $imageshow          = 0;
    public  $screensize         = 0;
    public  $columnsize         = 3;
    public $imagelastloaded;

    /**
     * Tiles constructor.
     * @param $controller
     * @param constant $defaultError
     */
    public function __construct($controller, $defaultError = self::DEFAULT_ERROR)
    {
        parent::__construct($controller);
        $this->defaultError = $defaultError;
        $this->init();
    }

   public function init() {

       $this->workuser_id =  scartUsers::getId();
//       $this->addJs('js/lazyloading.js');

       $this->config = $this->makeConfig(__DIR__. '/tiles/config/config.yaml');
   }

    /**
     * @param int $size
     * @param $displayColumns
     * @return void
     */
    public function setScreensize(int $size) :void
    {
        if ($size > 0) {
            $this->screensize = $size;
        }

        foreach ($this->config->displaycolumn AS $size => $displaycolumn) {
            if ($this->screensize >= $size) {
                $this->colspan          = $displaycolumn[$this->columnsize]['colspan'];
                $this->colimgsize       = $displaycolumn[$this->columnsize]['imgsize'];
                $this->colboxheightsize = $displaycolumn[$this->columnsize]['boxheightsize'];
                $this->coltxtsize       = $displaycolumn[$this->columnsize]['txtsize'];
                scartLog::logLine("D-nextCall; use viewport-size=$size; colspan=$this->colspan, colimgsize=$this->colimgsize, colboxheightsize=$this->colboxheightsize, coltxtsize=$this->coltxtsize");
                break;
            }
        }

        // set session
        Session::put('grade_colimgsize', $this->colimgsize);
    }

    /**
     * @description // Load images (items) with buttons (partials)
     */
    private function buildTiles() {
        $imagestxt = '';
        $sortfield = Input::getSortField();

        $cnt = 0;

        foreach ($this->inputItems as $item) {

            // check if first of last row
            $this->rowfirst = $this->rowlast = false;
            if ($this->colnum==0) {
                $this->rowfirst = true;
            } elseif ($this->colnum >= $this->colspanlast) {
                $this->rowlast = true;
                $this->colnum = -$this->colspan;
            }
            $this->colnum += $this->colspan;

            // detect img size setting
            // fill fields
            $infodatas = $item->getInfoDataAttribute();
            $info = [];
            foreach ($infodatas as $key => $infodata) {
                if ($key!='whoisraw' && $key!='extra' && $key!='extradata') {
                    $info = array_merge($info,$infodata);
                }
            } // end foreach

            $extra = (isset($infodatas['extra'])?$infodatas['extra']:[]);
            $extradata = (isset($infodatas['extradata'])?$infodatas['extradata']:"{}");
            $whoisraw = $infodatas['whoisraw'];


            /**
             * if manual add an url then hash can be empty
             * always fill for unique javascript reference
             */

            if (empty($item->url_hash)) {
                $hash = sprintf('%s%016d', md5($item->url),strlen($item->url));   // 48 tekens
                $item->url_hash = $hash;
                scartLog::logLine("D-nextCall; item with empty hash; id=$item->id; url_hash=$hash");
                $item->save();
            }

            $setbuttons = Form::setButtons($item,$this->workuser_id);
            $showresult = $this->makePartial('js_buttonresult',
                ['hash' => $item->filenumber,
                    'buttonsets' => $setbuttons['buttonsets'],
                    'class' => $setbuttons['class']
                ]
            );

            // create partial for this item (image)
            $ignoretxt = (($item->grade_code==SCART_GRADE_IGNORE)? 'on ignore (skip grading)' : 'NOT on ignore (anymore)');

            $cssnote = ($item->note!='') ? 'grade_button_notefilled' : '';

            // 2022/2/1/Gs: not found already done in $item->getUrlDataAttribute();
            // check if the url contains a real image, if not, use a placeholder
            //$urldata = Grade::CheckifImagesExists($item);

            if ($this->imageshow < SCART_GRADE_LOAD_IMAGE_NUMBER) {
                $src = $item->getUrlDataAttribute();
                $this->imagelastloaded = $item->$sortfield;
            } else {
                $src = '';
            }

            $imgsize     = scartImage::getImageSizeAttr($item,$this->colimgsize);
            $imgbigsize  = scartImage::getImageSizeAttr($item,$this->colimgsize,true);
            $extrasizes  = scartImage::getImageSizeAttr($item,$this->colimgsize,true,true);
            $extraheight = ($extrasizes[1] > 0)? $extrasizes[1] : $extrasizes[0];

            $tabhead = (($item->url_type==SCART_URL_TYPE_MAINURL) ? 'MAIN' : (($item->url_type==SCART_URL_TYPE_IMAGEURL) ? 'IMAGE' : 'VIDEO') );
            $cnt += 1;

            $imagestxt .= $this->makePartial('show_grade_image',
                [   'record_id' => $item->id,
                    'cnt' => $cnt,
                    'workuser_id' => $this->workuser_id,
                    'base' => $item->url_base,
                    'url' => $item->url,
                    'url_type' => $item->url_type,
                    'src' => $src,
                    'tabtype' => $tabhead,
                    'hash' => $item->filenumber,
                    'imgsize' => $imgsize,
                    'imgbigsize' => $imgbigsize,
                    'imgtitle' => (isset($item->image_title)) ? $item->image_title : $item->url, // debug: important for viewing the right url
                    'boximgsize' => $this->colimgsize,
                    'boxheightsize' => $this->colboxheightsize,
                    'txtsize' => $this->coltxtsize,
                    'rowfirst' => $this->rowfirst,
                    'rowlast' => $this->rowlast,
                    'colspan' => $this->colspan,
                    'coladdnum' => $this->coladdnum,
                    'ignoretxt' => $ignoretxt,
                    'cssnote' => $cssnote,
                    'info' => $info,
                    'extra' => $extra,
                    'extradata' => $extradata,
                    'extraheight' => $extraheight,
                    'imagewhoisraw' => scartWhois::htmlOutputRaw($whoisraw),
                    'js_result' => $showresult,
                    'hashcheck_return' => $item->hashcheck_return,
                    'proxy_call_error' => $item->proxy_call_error,
                ] );

            $this->imageshow += 1;

        } // end foreach items


        if ($this->colnum > 0) {
            $this->coladdnum = 12 - $this->colnum;

            if($this->coladdnum > 0) {
                $imagestxt .= $this->makePartial('show_grade_image',
                    [   'rowfirst' => false,
                        'rowlast' => true,
                        'coladdnum' => $this->coladdnum,
                    ] );
            }
        }

        $listsrecords = Lists::getListRecords();
        $cntitems = scartGrade::countItems($listsrecords);

        if ($cntitems == 0) {
            $imagestxt = '<h4>No items to analyze </h4>';
        } elseif ($this->imageshow == 0 && $cntitems > 0) {
            $imagestxt = '<h4>No items to show (are hidden) </h4>';
        } else {
            scartLog::logLine("D-Sortfield=$sortfield, imagelastloaded=$this->imagelastloaded");
        }


        return $imagestxt;
    }


    /**
     * @param $columnsize
     * @return void
     */
    public function setColumnsize($columnsize) {
        // check screensize
        if (in_array($columnsize, array('2','3','4'))) $this->columnsize = $columnsize;
    }

    /**
     * @param $inputItems
     */
    public function setInputItems($inputItems){
        $this->inputItems = $inputItems;
    }

    /**
     * Renders the widget.
     */
    public function render()
    {
        $tiles = $this->buildTiles();
        return $tiles;
    }

    /**
     * Prepares the view data
     */
    public function prepareVars()
    {
        $this->vars['index'] = $this->getActiveIndex();
        $this->vars['items'] = $this->getListItems();
        $this->vars['error_message'] = $this->defaultError;
    }

    public function onItemChange()
    {
        /*
         * Save or reset dropdown index in session
         */
        $this->setActiveIndex(post('index'));
        $widgetId = '#' . $this -> getId();
        $listId = '#' . $this->controller->listGetWidget()->getId();
        $listRefreshData = $this->controller->listRefresh();

        return [
            $listId => $listRefreshData[$listId],
            $widgetId => $this->makePartial('dropdown', ['index' => $this->getActiveIndex(), 'items' => $this->getListItems()])
        ];
    }

    /**
     * Gets the list items array for this widget instance.
     */
    public function getListItems()
    {
        return $this->listItems;
    }

    /**
     * Sets the list items array for this widget instance.
     */
    public function setListItems($listItems)
    {
        $this->listItems = $listItems;
    }

    /**
     * Gets the error message for this widget instance.
     */
    public function getErrorMessage()
    {
        return $this->defaultError;
    }

    /**
     * Sets the error message for this widget instance.
     */
    public function setErrorMessage($message)
    {
        $this->defaultError = $message;
    }

    /**
     * Returns an active index for this widget instance.
     */
    public function getActiveIndex()
    {
        return $this->index = $this->getSession('index', 1);
    }

    /**
     * Sets an active index for this widget instance.
     */
    public function setActiveIndex($index)
    {
        if ($index) {
            $this->putSession('index', $index);
        }
        else {
            $this->resetSession();
        }

        $this->index = $index;
    }

    /**
     * Returns a value suitable for the field name property.
     * @return string
     */
    public function getName()
    {
        return $this->alias . '[index]';
    }

    public function onScrollNext() {


        $viewtype = (!empty(scartUsers::getOption(SCART_USER_OPTION_CLASSIFY_VIEWTYPE))) ? scartUsers::getOption(SCART_USER_OPTION_CLASSIFY_VIEWTYPE) : Systemconfig::get('abuseio.scart::classify.viewtype_default',SCART_CLASSIFY_VIEWTYPE_GRID);


        if ($viewtype == SCART_CLASSIFY_VIEWTYPE_GRID) {

            $lastLoading = input('lastLoading');
            $sortfield = Input::getSortField();
            scartLog::logLine("D-onScrollNext: sortfield=$sortfield, lastLoading=$lastLoading");

            // disabled -> already done for process
            //$memory_limit = scartScheduler::setMinMemory('2G');
            //scartLog::logLine("D-scartGrade.update: set memory_limit=" . $memory_limit);

            $gradeclass = new Grade();
            $items = $gradeclass->getQueryRecords($lastLoading,SCART_GRADE_LOAD_IMAGE_NUMBER);
            $colimgsize = $gradeclass->getColimgsize();

            $showresult = '';
            $images = [];

            foreach ($items AS $item) {
                $imgsize = scartImage::getImageSizeAttr($item,$colimgsize);
                if ($item->url_type == SCART_URL_TYPE_IMAGEURL) {
                    $src = scartBrowser::getImageBase64($item->url,$item->url_hash);
                } else {
                    $src = scartBrowser::getImageBase64($item->url,$item->url_hash, false,SCART_IMAGE_IS_VIDEO);
                }
                $images[] = [
                    'data' => $src,
                    'hash' => $item->filenumber,
                    'imgsize' => $imgsize,
                ];
                $lastLoading = $item->$sortfield;
            }

            if (count($images) > 0) {
                scartLog::logLine("D-onScrollNext: found new lastLoading=$lastLoading, count=" . count($images) );
                $showresult = $this->makePartial('js_load_images',
                    [
                        'images' => $images,
                        'lastLoading' => $lastLoading,
                    ]
                );
            }

        } else {
            $showresult = '';
        }

        return ['show_result' => $showresult];
    }
}
