<?php

namespace abuseio\scart\Controllers;

/**
 * Grade function
 *
 * Show ANALYZE screen with Inputs and items.
 * Next and Previous
 * Select ALL or DESELECT
 * Set selected
 * Show and hide ignored
 * Show grade questions
 *
 * Use of general scartGrade class
 *
 */

use abuseio\scart\classes\aianalyze\scartAIanalyze;
use abuseio\scart\classes\helpers\scartImage;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Input_extrafield;
use abuseio\scart\widgets\Tiles;
use Backend\Models\User;
use Backend\Widgets\Form;
use Backend\Widgets\Lists;
use Db;
use Flash;
use Config;
use Illuminate\Validation\Rules\In;
use abuseio\scart\classes\scheduler\scartScheduler;
use abuseio\scart\models\Input_parent;
use abuseio\scart\models\Input_source;
use Validator;
use ValidationException;
use BackendMenu;
use BackendAuth;
use Illuminate\Support\Facades\Redirect;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\models\Abusecontact;
use function GuzzleHttp\Promise\all;
use Illuminate\Support\Facades\Session;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Input;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\User_options;
use abuseio\scart\models\Domainrule;
use abuseio\scart\Plugin;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Systemconfig;

class Grade extends scartController
{
    public $requiredPermissions = ['abuseio.scart.grade_notifications'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        'Backend\Behaviors\RelationController',


    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    private $_labels = [
        'registrar_owner' => 'registrar',
        'registrar_lookup' => '_domain ',
        'registrar_country' => '_country ',
        'registrar_abusecontact' => '_abuse ',
        'registrar_abusecustom' => '_custom ',
        'host_owner' => 'hoster',
        'host_lookup' => '_IP',
        'host_country' => '_country',
        'host_abusecontact' => '_abuse',
        'host_abusecustom' => '_custom',
    ];

    private $_infofields = [
        'filenumber' => 'filenumber',
        'type_code' => 'input',
        'source_code' => 'source',
        'url_type' => 'type',
    ];

    private $_displayfieldsize = [
        '1920' => 45,
        '1620' => 35,
        '1020' => 25,
        '0' => 20,
    ];
    /** SETTINGS **/

    // GRID/LIST view
    function getViewType() {
        $viewtype = scartUsers::getOption(SCART_USER_OPTION_CLASSIFY_VIEWTYPE);
        if ($viewtype=='') {
            $viewtype = Systemconfig::get('abuseio.scart::classify.viewtype_default',SCART_CLASSIFY_VIEWTYPE_GRID);
        }
        return $viewtype;
    }
    function setViewType($viewtype) {
        scartUsers::setOption(SCART_USER_OPTION_CLASSIFY_VIEWTYPE, $viewtype);
    }
    // COLUMNSIZE
    function getColumnsize() {
        $columnsize = scartUsers::getOption(SCART_USER_OPTION_SCREENCOLS);
        if ($columnsize=='') $columnsize = 3;
        return $columnsize;
    }
    function setColumnsize($columnsize) {
        scartUsers::setOption(SCART_USER_OPTION_SCREENCOLS, $columnsize);
    }
    // DISPLAY RECORDS
    function getDisplayRecords() {
        $records = scartUsers::getOption(SCART_USER_OPTION_DISPLAYRECORDS);
        if ($records=='') $records = 5;
        return $records;
    }
    function setDisplayRecords($records) {
        scartUsers::setOption(SCART_USER_OPTION_DISPLAYRECORDS, $records);
    }
    // SORT RECORDS
    function getSortField() {
        $sort = scartUsers::getOption(SCART_USER_OPTION_SORTRECORDS);
        if ($sort=='') $sort = 'filenumber';
        return $sort;
    }
    function setSortField($sort) {
        scartUsers::setOption(SCART_USER_OPTION_SORTRECORDS, $sort);
    }


    /**
     * @description Save selected inputs tmp to the database, like sortfield data
     * @param $numbers    ids of inputs
     * @return  void
     */
    public function setInputNumbers($numbers) {
        // check if the user select one or more inputs
        if(!empty($numbers)) {
            // save the input ids to the DB, so we can use them when we login again
            scartUsers::setOption(SCART_USER_OPTION_INPUTS, serialize($numbers));
        }
    }

    /**
     * @Description Save current page number in the database, so we can restore if the user login again
     * @param $number Int|bool
     * @return void
     */
    public function setCurrentPageNumber($number) {

        // if there is a number, skip if not
        if ($number) {
            scartUsers::setOption(SCART_USER_OPTION_PAGINATION, $number);
        }

    }

    /**
     * @Description get the number is the current pagenumber. This number is stored in de databasetable option
     * @return bool|mixed|string
     */
    public function getCurrentPageNumber() {
        $pageNumber = scartUsers::getOption(SCART_USER_OPTION_PAGINATION);

        return ($pageNumber == '') ? false : $pageNumber;
    }

    /**
     * @Description get the number is the current pagenumber. This number is stored in de databasetable option
     * @return bool|mixed|string
     */
    public function getInputNumbers() {
        $inputs = scartUsers::getOption(SCART_USER_OPTION_INPUTS);
        return ($inputs == '') ? false : $inputs;
    }

    // INFO field size
    private $_cachefieldsize = [];
    function getInfoFieldSize() {
        $screensize = $this->getScreensize();
        if (isset($this->_cachefieldsize[$screensize])) {
            $fieldsize = $this->_cachefieldsize[$screensize];
        } else {
            $fieldsize = $this->_displayfieldsize[0];
            foreach ($this->_displayfieldsize AS $size => $displayfieldsize) {
                if ($screensize >= $size) {
                    $fieldsize = $displayfieldsize;
                    break;
                }
            }
            $this->_cachefieldsize[$screensize] = $fieldsize;
            scartLog::logLine("D-getInfoFieldSize; screensize=$screensize, fieldsize=$fieldsize");
        }
        return $fieldsize;
    }
    //  SCREENSIZE
    function onSetScreensize() {
        $screensize = input('screensize');
        Session::put('grade_screensize',$screensize);
        scartLog::logLine("D-onSetScreensize; screensize=$screensize ");
    }
    function getScreensize() {
        return Session::get('grade_screensize','1620');
    }
    // SCROLLTOP
    function setScrollTop($scrollTop) {
        Session::put('grade_scrollTop',"$scrollTop");
    }
    function getScrollTop() {
        return Session::get('grade_scrollTop','0');
    }
    // COL IMG SIZE
    function getColimgsize() {
        return Session::get('grade_colimgsize','250');
    }
    function setColimgsize($colimgsize) {
        Session::put('grade_colimgsize',$colimgsize);
    }
    // SHOW/HIDE
    function getShowHide() {
        $show = Session::get('grade_showhide','');
        return $show;
    }
    function setShowHide($show) {
        Session::put('grade_showhide',$show);
    }
    function jsShowHide() {
        $btn = $this->getShowHide();
        return $this->makePartial('js_showhide', ['btn' => $btn] );
    }

    // MAIN RECORDS SESSION
    function getListRecords() {
        $listrecords = Session::get('grade_listrecords','');
        if ($listrecords) $listrecords = unserialize($listrecords);
        if (empty($listrecords)) $listrecords = [0];
        return ($listrecords);
    }
    function setListRecords($listrecords) {
        Session::put('grade_listrecords',serialize($listrecords));
    }
    // if start from classify then add mainurl to item list
    function checkAddParent() {
        $listrecords = $this->getListRecords();
        $txt = '';
        foreach ($listrecords AS $input_id) {
            $ipcnt = Input_parent::where('parent_id',$input_id)
                ->where('input_id',$input_id)
                ->count();
            if ($ipcnt == 0) {
                $ip = new Input_parent();
                $ip->parent_id = $ip->input_id= $input_id;
                $ip->save();
                $txt .= ( ($txt) ? ', ' : '') ."input_id=$input_id";
            }
        }
        if ($txt) scartLog::logLine("D-checkAddParents; parents added; $txt");
    }
    // called in htm
    public function getWorkuserID() {
        return scartUsers::getId();
    }

    // list_toolbar function(s)

    public function onIgnoreClose() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $input = Input::find($check);
            if ($input) {
                $items = scartGrade::getItems($input->id);
                foreach ($items AS $item) {
                    $item->grade_code = SCART_GRADE_IGNORE;

                    // log old/new for history
                    $item->logHistory(SCART_INPUT_HISTORY_STATUS,$item->status_code,SCART_STATUS_CLOSE,'Set by analist');

                    $item->status_code = SCART_STATUS_CLOSE;
                    $item->save();
                    $item->logText('closed with classification ignore');

                    // if ICCAM reportID set then export action NI
                    if (scartICCAMinterface::hasICCAMreportID($item->reference) && scartICCAMinterface::isActive()) {
                        scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                            'record_type' => class_basename($item),
                            'record_id' => $item->id,
                            'object_id' => $item->reference,
                            'action_id' => SCART_ICCAM_ACTION_NI,     // NOT_ILLEGAL
                            'country' => '',                          // hotline default
                            'reason' => 'SCART reported NI',
                        ]);
                    }

                }
                // @TO-DO; not SCART_GRADE_IGNORE?!
                $input->grade_code = SCART_GRADE_NOT_ILLEGAL;
                // log old/new for history
                $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_CLOSE,'Set by analist');
                $input->status_code = SCART_STATUS_CLOSE;
                $input->save();
                $input->logText('closed with classification ignore');

                // if ICCAM reportID set then export action NI
                if (scartICCAMinterface::hasICCAMreportID($input->reference) && scartICCAMinterface::isActive()) {
                    scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                        'record_type' => class_basename($input),
                        'record_id' => $input->id,
                        'object_id' => $input->reference,
                        'action_id' => SCART_ICCAM_ACTION_NI,     // NOT_ILLEGAL
                        'country' => '',                          // hotline default
                        'reason' => 'SCART reported NI',
                    ]);
                }

                scartLog::logLine("D-Filenumber=$input->filenumber, url=$input->url, set status_code on $input->status_code (with found items)");
            }
        }
        Flash::info('Selected url(s) set on closed');
        return $this->listRefresh();
    }

    public function onClassifySelected() {

        $checked = input('checked');
        if ($checked) {
            $this->setListRecords( $checked );

            // save the number(s) to the database, because this numbers can be use if the user login again.
            $this->setInputNumbers($checked);
            // redirect to updateS -> special route for using ListRecords
            return Redirect::to('/backend/abuseio/scart/Grade/updates');
        }

    }

    /**
     * Own index
     */

    public $inputWidget = null;
    public $rulesWidget = null;
    public $listWidget = null;
    public $tilesWidget = null;

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Grade');

        // Note: very important to bindToController in this __construct phase; else not working

        $config = $this->makeConfig('$/abuseio/scart/models/input/fields_grade.yaml');
        $config->model = new Input();
        $this->inputWidget = $this->makeWidget('Backend\Widgets\Form', $config);
        $this->inputWidget->alias = 'inputWidget';
        $this->inputWidget->bindToController();

        $config = $this->makeConfig('$/abuseio/scart/models/domainrule/fields_domaininsert.yaml');
        //$config = $this->makeConfig('$/abuseio/scart/models/domainrule/columns.yaml');
        $config->model = new Domainrule();
        $this->rulesWidget = $this->makeWidget('Backend\Widgets\Form', $config);
        $this->rulesWidget->bindToController();

        $config = $this->makeConfig('$/abuseio/scart/models/input/columns_viewlist.yaml');
        $config->model = new Input();
        $this->listWidget = $this->makeWidget('Backend\Widgets\Lists', $config);
        $this->listWidget->alias = 'listWidget';
        $this->listWidget->bindEvent('list.extendQueryBefore', function ($query) {
            return $this->itemQuery($query);
        });
        $this->listWidget->bindToController();

        $this->setScrollTop(0);
    }

    public function index() {

        $this->pageTitle = 'Classify';
        $this->bodyClass = 'compact-container ';

        // workuser
        $workuser_id = scartUsers::getId();
        $this->vars['workuser_id'] = $workuser_id;

        $this->asExtension('ListController')->index();
    }

    /** LIST VIEW CLASSIFY **/

    /**
     * Filter based on dynamic data
     * - input status
     * - logged in user
     *
     * @param $filter
     */
    public function listFilterExtendScopes($filter) {

        /**
         * NB: add only dynamic fields/options
         *
         * setup is primary done inputs/config_filter.yaml
         *
         */

        $own_work_default = Systemconfig::get('abuseio.scart::options.own_work_default',true);
        $workuser_id = scartUsers::getId();
        $filter->addScopes([
            'workuser_id' => [
                'label' => trans('abuseio.scart::lang.head.my_work'),                               // @TO-DO; lang translation
                'type' =>'checkbox',
                'default' => ($own_work_default),
                'conditions' => "workuser_id=$workuser_id",
            ],
        ]);

        if (scartAIanalyze::isActive()) {

            // hardcoded AI attributes
            $filter_attributes = [
                'Naaktheid_in_foto' => 'Naaktheid_in_foto',
                'Bevat_gezicht_in_leeftijdscategorie_0-15' => 'Bevat_gezicht_in_leeftijdscategorie_0-15',
                'Bevat_gezicht_in_leeftijdscategorie_15-20' => 'Bevat_gezicht_in_leeftijdscategorie_15-20'
            ];
            $filter->addScopes([
                'attributes' => [
                    'label' => 'attributes',
                    'type' =>'group',
                    'options' => $filter_attributes,
                    'default' => '',
                    'scope' => 'attribute',
                    'conditions' => "",
                ],
            ]);

        }

    }

    /**
     * Filter on status_code=grade
     *
     * @param $query
     */
    public function listExtendQuery($query ) {
        scartLog::logLine("D-listExtendQuery call");
        $query->where('url_type',SCART_URL_TYPE_MAINURL)->where('status_code',SCART_STATUS_GRADE);
    }

    public function listFilterExtendQuery($query,$scope) {
        scartLog::logLine("D-listFilterExtendQuery call");
        //scartLog::logLine("D-scope=" . print_r($scope,true));
    }


    /** UPDATE(S) CLASSIFY **/

    /**
     * update -> analyze screen of input (id)
     *
     * @param $recordId
     * @param null $context
     * @return mixed
     */
    public function update($recordId, $context=null) {

        $this->pageTitle = 'Classify';

        $columnsize = $this->getColumnsize();
        $screensize = $this->getScreensize();

        // if not array then set current mainurl record(s)
        if (!empty($recordId) ) $this->setListRecords( [$recordId] );
        //trace_log($this->getListRecords());

        // check if all set
        $this->checkAddParent();

        // MUST be called to init form ajax for RecordFinder
        //$domainrule = $domainrule::first();
        $dummy = $this->asExtension('FormController')->create();

        // create real form
        $next = $this->nextCall($screensize,$columnsize,'');
        return $this->makePartial('update',['nexttxt' => $next['id_grade_screen'] ]);
    }

    public function updates($context=null) {

        /*
        $this->pageTitle = 'Classify';

        // classify function need memory
        // reuse setting memory limit from scheduler...
        $memory_limit = scartScheduler::setMinMemory('2G');
        scartLog::logLine("D-scartGrade.update: set memory_limit=" . $memory_limit);

        $columnsize = $this->getColumnsize();
        $screensize = $this->getScreensize();

        // check if all set
        $this->checkAddParent();

        // MUST be called to init form ajax for RecordFinder
        $dummy = $this->asExtension('FormController')->update($recordId, $context);

        $next = $this->nextCall($screensize,$columnsize,'');
        return $this->makePartial('update',['nexttxt' => $next['id_grade_screen'] ]);
        */

        return $this->update(0,$context);
    }

    /**
     * Main query for urls on detail classify screen
     *
     * @param $query
     */
    public function itemQuery($query) {
        //scartLog::logLine("D-itemQuery call");
        $query->join(SCART_INPUT_PARENT_TABLE, SCART_INPUT_PARENT_TABLE.'.input_id', '=', SCART_INPUT_TABLE.'.id')
            ->where(SCART_INPUT_PARENT_TABLE.'.deleted_at',null)
            ->whereIn(SCART_INPUT_PARENT_TABLE.'.parent_id', $this->getListRecords())
            ->select(SCART_INPUT_TABLE.'.*');
        $query = $this->itemQueryFilter($query);
        $query = $this->itemQuerySort($query);
    }

    function itemQueryFilter($query) {
        $grade = [];
        $btn = $this->getShowHide();
        switch ($btn) {
            case 'IGN':
                $grade[] = SCART_GRADE_IGNORE;
                break;
            case 'INI':
                $grade[] = SCART_GRADE_IGNORE;
                $grade[] = SCART_GRADE_NOT_ILLEGAL;
                break;
            case 'CLS':
                $grade[] = SCART_GRADE_IGNORE;
                $grade[] = SCART_GRADE_ILLEGAL;
                $grade[] = SCART_GRADE_NOT_ILLEGAL;
                break;
        }
        if (count($grade) > 0) {
            scartLog::logLine("D-Grade: " . implode(',', $grade));
            $query->whereNotIn(SCART_INPUT_TABLE.'.grade_code',$grade);
        }
        return $query;
    }

    function itemQuerySort($query) {
        $sortfield = $this->getSortField();
        $query->orderBy(SCART_INPUT_TABLE.'.'.$sortfield,'asc');
        return $query;
    }

    public function getQueryRecords($fromID='',$take='') {
        $sortfield = $this->getSortField();
        $query = Input::join(SCART_INPUT_PARENT_TABLE, SCART_INPUT_PARENT_TABLE.'.input_id', '=', SCART_INPUT_TABLE.'.id')
            ->where(SCART_INPUT_PARENT_TABLE.'.deleted_at',null)
            ->whereIn(SCART_INPUT_PARENT_TABLE.'.parent_id', $this->getListRecords())
            ->where(SCART_INPUT_TABLE.'.'.$sortfield, '>', $fromID)
            ->select(SCART_INPUT_TABLE.'.*');
        if ($take) $query->take($take);
        $query = $this->itemQueryFilter($query);
        $query = $this->itemQuerySort($query);
        $items = $query->get();
        return $items;
    }

    public function onRefresh() {

        $scrollTop = input('scrollTop');
        if (empty($scrollTop)) $scrollTop = 0;
        scartLog::logLine('D-onrefresh; idImageScrollArea.scrollTop=' . $scrollTop);
        $this->setScrollTop($scrollTop);
        return $this->nextCall();
    }

    public function onSetworker() {

        $workuser_id = scartUsers::getId();
        $checked = input('checked');
        if (is_array($checked)) {
            $listrecords = [];
            foreach ($checked as $check) {
                $record = Input::find($check);
                if ($record) {
                    if (!scartGrade::isLocked($workuser_id,[$record->id]) ) {
                        if ($record->workuser_id!=$workuser_id) {
                            $record->logText("Set workuser_id=$workuser_id");
                            $record->workuser_id = $workuser_id;
                            $record->save();
                        }
                    } else {
                        $listrecords[] = $record->id;
                    }
                }
            }
            if (count($listrecords) > 0) {
                $lockedby = scartGrade::getLockFullnames($workuser_id,$listrecords);
                Flash::warning(trans('abuseio.scart::lang.flash.setworker_warning',['lockedby' => $lockedby]));
            } else {
                Flash::info(trans('abuseio.scart::lang.flash.setworker_info'));
            }
            return $this->listRefresh();
        }
    }

    /**
     * Next (prev) input with item(s)
     *
     * - build screen; top the input spec, below for each item (image) an image preview (small) and action buttons
     * - show previous and next based on number of current input
     *
     * @return screen output
     */

    public function nextCall($screensize=0,$columnsize=0,$warning='') {

        if (empty($screensize)) $screensize = input('screensize');
        if (empty($screensize)) $screensize = $this->getScreensize();

        if (empty($columnsize)) $columnsize = input('columnsize');
        if (empty($columnsize)) $columnsize = $this->getColumnsize();
        // save option for user
        $this->setColumnsize($columnsize);
        // save current page number, so we can restore this screen on a later process
        $this->setCurrentPageNumber(post('page', false));

        $imagelastloaded = '';
        $imagestxt = '';
        $imageshow = 0;

        // disabled -> already done for process
        //$memory_limit = scartScheduler::setMinMemory('2G');
        //scartLog::logLine("D-nextCall; set memory_limit=" . $memory_limit);

        // 2019/7/12/Gs: current user
        $workuser_id = scartUsers::getId();

        $viewtype = $this->getViewType();

        scartLog::logLine("D-nextCall; workuser_id=$workuser_id, viewtype=$viewtype, screensize=$screensize, columnsize=$columnsize");

        if ($viewtype == SCART_CLASSIFY_VIEWTYPE_LIST) {

            // ** LIST VIEW **

            // Load the conflig file for the list
            $config = $this->makeConfig('$/abuseio/scart/models/input/columns_viewlist.yaml');
            $config->model = new Input();
            $config->showSorting = false;
            $config->showSearching = false;
            $config->showSetup = false;
            $config->showPageNumbers = true;
            $config->recordsPerPage = $this->getDisplayRecords();
            $config->showCheckboxes = true;
            $config->customViewPath = '$/abuseio/scart/controllers/grade/list';
            $this->listWidget = $this->makeWidget('Backend\Widgets\Lists', $config);
            $this->listWidget->bindEvent('list.extendQueryBefore', function ($query) {
                return $this->itemQuery($query);
            });
            $this->listWidget->bindToController();

            $imagestxt = $this->makePartial('update', [
                'nexttxt' => $this->listWidget->render(),
            ]);


        } else {

            // ** GRID VIEW **
            $tilesWidget = new Tiles($this);

            // set variables
            $tilesWidget->setColumnsize($columnsize);
            $tilesWidget->setScreensize($screensize);
            $tilesWidget->setInputItems($this->getQueryRecords('',''));
            $tilesWidget->bindToController();

            $imagestxt          = $tilesWidget->render();
            $imagelastloaded    = $tilesWidget->imagelastloaded;
            $screensize         = $tilesWidget->screensize;
            $columnsize         = $tilesWidget->columnsize;

        } // einde tiles

        // ** INPUT ** //

        $locked_workuser = '';
        $listrecords = $this->getListRecords();
        if (scartGrade::isLocked($workuser_id,$listrecords)) {
            $locked_workuser = scartGrade::getLockFullnames($workuser_id,$listrecords);
            scartLog::logLine("D-nextCall; input locked by '$locked_workuser' ");
        } else {
            // set lock if work done
            if (scartGrade::countSelectedItems($workuser_id,$listrecords) > 0 ||
                scartGrade::countItemsWithGrade($listrecords,SCART_GRADE_UNSET, '<>') > 0) {
                if (!scartUsers::isAdmin()) {
                    // set locked and workuser
                    scartGrade::setLock($workuser_id,$listrecords);
                }
            }
        }

        $inputtxt = $this->makePartial('show_grade_input',
            [   'id' => '0',
                'workuser_id' => $workuser_id,
                'locked_workuser' => $locked_workuser,
                'screensize' => $screensize,
                'columnsize' => $columnsize,
                'viewtype' => $viewtype,
                'js_showhide' => $this->jsShowHide(),
            ] );


        $txt = $this->makePartial('show_grade_screen',
            [   'inputtxt' => $inputtxt,
                'imagestxt' => $imagestxt,
                'input_id' => '0',
                'imagelastloaded' => $imagelastloaded,
                'scrollTop' => $this->getScrollTop(),
            ] );


        if ($warning) {
            Flash::warning($warning);
        }

        return ['id_grade_screen' => $txt];
    }

    public function onScrollNext() {

        $viewtype = $this->getViewType();

        if ($viewtype == SCART_CLASSIFY_VIEWTYPE_GRID) {

            $lastLoading = input('lastLoading');
            $sortfield = $this->getSortField();
            scartLog::logLine("D-onScrollNext: sortfield=$sortfield, lastLoading=$lastLoading");

            // disabled -> already done for process
            //$memory_limit = scartScheduler::setMinMemory('2G');
            //scartLog::logLine("D-scartGrade.update: set memory_limit=" . $memory_limit);

            //trace_sql();
            $items = $this->getQueryRecords($lastLoading,SCART_GRADE_LOAD_IMAGE_NUMBER);
            $colimgsize = $this->getColimgsize();

            $showresult = '';
            $images = [];
            foreach ($items AS $item) {
                $imgsize = scartImage::getImageSizeAttr($item,$colimgsize);
                if ($item->url_type == SCART_URL_TYPE_IMAGEURL) {
                    $src = scartBrowser::getImageCache($item->url,$item->url_hash);
                } else {
                    $src = scartBrowser::getImageCache($item->url,$item->url_hash, false,SCART_IMAGE_IS_VIDEO);
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

    public function onUnlockInput() {

        $listrecords = $this->getListRecords();
        $workuser_id = scartUsers::getId();

        scartGrade::setLock($workuser_id,$listrecords,!scartUsers::isAdmin());
        Flash::info('Input(s) lock reset and locked by current user');

        // refresh screen
        return $this->nextCall();
    }

    public function onDone() {

        $workuser_id = scartUsers::getId();
        $screensize = input('screensize');
        $columnsize = input('columnsize');

        set_time_limit(0);

        $listrecords = $this->getListRecords();

        $cnt = scartGrade::countItemsWithGrade($listrecords,SCART_GRADE_UNSET);
        if ($cnt > 0) {

            $warning = (($cnt==1) ? "$cnt item (image)" : "$cnt items (images) are") .  " NOT CLASSIFIED - please classify first";

        } else {

            $cnt = scartGrade::countItemsIllegalWithNoRegistrarHosterSet($listrecords);
            if ($cnt > 0) {

                $warning = "Input illegal classified - but no registrar or hoster is filled";

            } else {

                scartLog::logLine("D-onDone; workuser_id=$workuser_id, screensize=$screensize, columnsize=$columnsize"  );

                $warning = '';

                // ok, do it

                foreach ($listrecords AS $input_id) {

                    // GO TO ITEMS AND HANDLE; MAINURL, IMAGE/VIDEOURL, ILLEGEL, NOT ILLEGAL AND IGNORE

                    $items = scartGrade::getItems($input_id);
                    foreach ($items AS $item) {

                        scartLog::logLine("D-onDone; handle id=$item->id, url_type=$item->url_type, grade=$item->grade_code");

                        // first look if ILLEGAL
                        if ($item->grade_code==SCART_GRADE_ILLEGAL) {

                            // check if already active -> can be linked by another input
                            if ($item->status_code != SCART_STATUS_SCHEDULER_CHECKONLINE && $item->status_code != SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) {

                                $item->firstseen_at = date('Y-m-d H:i:s');
                                $item->online_counter = 0;  // start first NTD
                                // reset error counters
                                $item->browse_error_retry = $item->whois_error_retry = 0;

                                if ($item->classify_status_code != SCART_STATUS_FIRST_POLICE) {
                                    // check alywas and set default
                                    if ($item->classify_status_code != SCART_STATUS_SCHEDULER_CHECKONLINE && $item->classify_status_code != SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL)
                                        $item->classify_status_code = SCART_STATUS_SCHEDULER_CHECKONLINE;
                                    // log old/new for history
                                    $item->logHistory(SCART_INPUT_HISTORY_STATUS,$item->status_code,$item->classify_status_code,'Classify done; illegal; checkonline');
                                    $item->status_code = $item->classify_status_code;
                                    $item->logText("Classification done; CHECKONLINE; status set on '$item->status_code' ");
                                } else {
                                    // log old/new for history
                                    $item->logHistory(SCART_INPUT_HISTORY_STATUS,$item->status_code,SCART_STATUS_FIRST_POLICE,'Classify done; illegal; first police');
                                    $item->status_code = SCART_STATUS_FIRST_POLICE;
                                    $item->logText("Classification done; FIRST POLICE; status set on '$item->status_code' ");
                                }

                                if (scartICCAMinterface::isActive()) {
                                    scartICCAMinterface::exportReport($item);
                                }

                            } else {
                                scartLog::logLine("D-onDone; item $item->filenumber already started for checkonline; online_counter=$item->online_counter " );
                            }

                        } elseif ($item->grade_code==SCART_GRADE_NOT_ILLEGAL || $item->grade_code==SCART_GRADE_IGNORE) {

                            // log old/new for history
                            $item->logHistory(SCART_INPUT_HISTORY_STATUS,$item->status_code,SCART_STATUS_CLOSE,'Classify done; not illegal or ignore');

                            $item->status_code = SCART_STATUS_CLOSE;
                            $item->firstseen_at = date('Y-m-d H:i:s');
                            $item->logText("Classification done; $item->grade_code; status set on '$item->status_code' ");

                            if (scartICCAMinterface::isActive()) {
                                scartICCAMinterface::exportReport($item);
                            }

                        }
                        $item->save();

                    }

                    scartGrade::resetLock($input_id);

                }

            }

        }

        if ($warning) {
            return $this->nextCall(0,0,$warning);
        } else {
            return Redirect::to('/backend/abuseio/scart/Grade')->with('message', 'Classify done');
        }
    }

    /**
     * POLICE button
     *
     * Return question screen
     *
     * @return bool|mixed
     */
    public function onImagePolice() {

        $workuser_id = scartUsers::getId();
        $record_id = input('record_id');
        scartLog::logLine("D-onImagePolice; record_id=$record_id");

        $single = true; $show_questions = '';
        $rec = Input::find($record_id);
        if ($rec) {


            if (in_array($rec->grade_code, [SCART_GRADE_NOT_ILLEGAL, SCART_GRADE_ILLEGAL])) {
                $show_questions = $this->show_grade_questions(SCART_GRADE_QUESTION_GROUP_POLICE,$workuser_id,$single, $rec);
            }

            else {
                Flash::warning('Is not set on a status' );
                $show_questions = $this->makePartial('show_close_popup');
            }

        } else {
            Flash::error("Unknown (image)!?");
            $show_questions = false;
        }

        return $show_questions;
    }

    public function onManualCheck() {

        $workuser_id = scartUsers::getId();
        $record_id = input('record_id');
        scartLog::logLine("D-onManualCheck; record_id=$record_id");

        // selected off
        scartGrade::setGradeSelected($workuser_id, $record_id, false);

        // zet status on SCART_GRADE_IGNORE
        $item = Input::find($record_id);
        $showresult = '';

        if ($item) {

            if ($item->grade_code == SCART_GRADE_ILLEGAL) {

                $item->classify_status_code = ($item->classify_status_code == SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) ? SCART_STATUS_SCHEDULER_CHECKONLINE : SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL;
                $item->logText("Set status_code=$item->classify_status_code");
                $item->save();

                $setbuttons = $this->setButtons($item,$workuser_id);
                $showresult = $this->makePartial('js_buttonresult',
                    ['hash' => $item->filenumber,
                        'buttonsets' => $setbuttons['buttonsets'],
                        'class' => $setbuttons['class']
                    ]
                );
                if ($item->classify_status_code == SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) {
                    Flash::info('item (image) set on manual (online) check');
                } else {
                    Flash::info('item (image) set on SCART (online) check');
                }

            } else {

                Flash::warning('item not illegal - can NOT set manual (online) check');

            }

        }

        return ['show_result' => $showresult ];
    }


    /**
     * @description Get a table with the given answers (when the user hover on a button)
     * @return string HTML view |void no information for popover
     */
    public function onGetPopoverFields()
    {
        // show images in popup or ignore (case popover)
        $record_id = input('record_id');
        scartLog::logLine("D-onGetPopoverFields; record_id=$record_id");

        // get input, like a image
        $rec = Input::find($record_id);
        if ($rec) {
            switch (input('type')) {
                case "questions":
                    $show_questions = $this->show_grade_answers($rec);
                    break;
                case "input":
                    $show_questions = $this->show_input_answers($rec);
                    break;
            }

            return $show_questions;
        } else {
            scartLog::logLine("D-onGetPopoverFields; record_id=$record_id   No grade questions available ");
        }
        // wouldnt show a popover

    }


    /**
     * ILLEGAL button
     *
     * Return question screen
     *
     * @return bool|mixed
     */
    public function onImageIllegal() {

        $workuser_id = scartUsers::getId();
        $record_id = input('record_id');
        scartLog::logLine("D-onImageIllegal; record_id=$record_id");
        $single = true;
        $rec = Input::find($record_id);
        if ($rec) {
            $show_questions = $this->show_grade_questions(SCART_GRADE_QUESTION_GROUP_ILLEGAL,$workuser_id,$single, $rec);
        } else {
            Flash::error("Unknown (image)!?");
            $show_questions = false;
        }

        return $show_questions;
    }

    /**
     * IGNORE button
     *
     * @return array
     */
    public function onImageIgnore() {

        $workuser_id = scartUsers::getId();
        $record_id = input('record_id');
        scartLog::logLine("D-onImageIgnore; record_id=$record_id ");

        // selected off
        scartGrade::setGradeSelected($workuser_id, $record_id, false);

        // zet status on SCART_GRADE_IGNORE
        $item = Input::find($record_id);
        $showresult = '';

        if ($item) {

            if ($item->grade_code == SCART_GRADE_IGNORE) {
                $item->classify_status_code = SCART_STATUS_GRADE;
                $item->grade_code = SCART_GRADE_UNSET;
            } else {
                $item->classify_status_code = SCART_STATUS_CLOSE;
                $item->grade_code = SCART_GRADE_IGNORE;
            }
            $item->logText("Set grade_code=$item->grade_code, set status_code=$item->classify_status_code");
            $item->save();

            if ($this->getShowHide() != '') {
                $showresult .= $this->makePartial('js_refreshscreen');
            } else {
                $setbuttons = $this->setButtons($item,$workuser_id);
                $showresult = $this->makePartial('js_buttonresult',
                    ['hash' => $item->filenumber,
                        'buttonsets' => $setbuttons['buttonsets'],
                        'class' => $setbuttons['class']
                    ]
                );
                Flash::info('item (image) status set on ignore');
            }

        }

        return ['show_result' => $showresult ];
    }

    /**
     * NOT ILLEGAL buttononImageSelect
     *
     * @return bool|mixed
     */
    public function onImageNotIllegal() {

        $workuser_id = scartUsers::getId();
        $record_id = input('record_id');
        scartLog::logLine("D-onImageNotIllegal; record_id=$record_id");

        $rec = Input::find($record_id);

        if ($rec) {
            $show_questions = $this->show_grade_questions(SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL,$workuser_id,true,$rec);
        } else {
            Flash::error("Unknown (image)!?");
            $show_questions = false;
        }

        return $show_questions;
    }

    /**
     * INPUT EDIT button
     *
     * @return bool|mixed
     */
    public function onInputEdit() {

        $record_id = input('record_id');
        scartLog::logLine("D-onInputEdit; record_id=$record_id ");

        $rec = Input::find($record_id);

        if ($rec) {

            // config (reuse) widget
            $config = $this->makeConfig('$/abuseio/scart/models/input/fields_grade.yaml');
            $config->model = $rec;
            $this->inputWidget = $this->makeWidget('Backend\Widgets\Form', $config);

            $workuser_id = scartUsers::getId();

            $show_whois = $this->makePartial('show_input_edit',[
                'single' => true,
                'record_id' => $record_id,
                'workuser_id' => $workuser_id,
                'inputWidget' => $this->inputWidget,
            ]);

        } else {
            scartLog::logLine("W-onInputEdit; unknown record (id=$record_id)");
            Flash::error("Unknown record!?");
            $show_whois = false;
        }

        return $show_whois;
    }

    function onInputEditSave() {

        // note: specific for input
        //trace_log(post());
        $record_id = input('record_id');
        $single = input('single');
        $workuser_id = input('workuser_id');

        $note = input('note');
        $ntd_note = input('ntd_note');
        $source_code = input('source_code');
        $type_code = input('type_code');

        scartLog::logLine("D-onInputEditSave; single=$single, record_id=$record_id");

        if ($single) {
            $rec = new \stdClass();
            $rec->input_id = $record_id;
            $recs = array($rec);
        } else {
            $listrecords = $this->getListRecords();
            $recs = scartGrade::getSelectedItems($workuser_id,$listrecords);
        }

        $setbuts = [];
        $showresult = '';
        if (count($recs) > 0) {

            foreach ($recs AS $rec) {

                // set select off
                scartGrade::setGradeSelected($workuser_id, $rec->input_id, false);

                // get record
                $item = Input::find($rec->input_id);
                if ($item) {

                    $note = trim($note);

                    $item->note = $note;
                    $item->ntd_note = $ntd_note;
                    $item->source_code = $source_code;
                    $item->type_code = $type_code;
                    $item->save();

                    $cssfield = 'idButtonNote' . $item->filenumber;
                    $cssremove = ($note=='') ? 'grade_button_notefilled' : '';
                    $cssadd = ($note!='') ? 'grade_button_notefilled' : '';
                    $showresult .= $this->makePartial('js_update_css',
                        ['cssfield' => $cssfield,
                            'cssremove' => $cssremove,
                            'cssadd' => $cssadd]);

                    $setbutton = $this->setButtons($item,$workuser_id,false);
                    $setbutton['hash'] = $item->filenumber;
                    $setbuts[] = $setbutton;

                }

            }

            $showresult .= $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts, 'resetall' => true]);
            if (!$single) {
                Flash::info('Edit items saved');
            }

        } else {
            scartLog::logLine("W-onInputEditSave; record(s) not found (record_id=$record_id) ");
            Flash::error("No record(s) found!?");
            $showresult = false;
        }

        return ['show_result' => $showresult ];
    }

    public function onDomainruleList($id=0,$refresh=false) {

        $listrecords = $this->getListRecords();
        scartLog::logLine("D-onDomainruleList; refresh=$refresh");

        $domainrule = new \abuseio\scart\models\Domainrule();
        $domainrule->setInputrecord($listrecords);
        $domainrule->setRuleExclude([SCART_RULE_TYPE_NONOTSCRAPE,
            SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL,
            SCART_RULE_TYPE_DIRECT_CLASSIFY_NOT_ILLEGAL,
            SCART_RULE_TYPE_LINK_CHECKER,
            SCART_RULE_TYPE_PROXY_SERVICE_API]);


        // To-Do: if paging is needed for the domainrules list, the paging is controlling the main list, not the domainrule list
        // workarround; set recordsPerPage on a large number

        // config LIST widget
        $config = $this->makeConfig('$/abuseio/scart/models/domainrule/columns.yaml');
        $config->model = $domainrule;
        $config->showSorting = false;
        $config->showSearching = false;
        $config->showSetup = false;
        $config->recordsPerPage = 50;
        $config->showPageNumbers = true;
        $config->showCheckboxes = false;
        $config->customViewPath = '$/abuseio/scart/controllers/grade/domainlist';
        $this->rulesWidget = $this->makeWidget('Backend\Widgets\Lists', $config);
        $this->rulesWidget->bindEvent('list.extendQueryBefore', function ($query) use ($domainrule) {
            $domains = $domainrule->getDomainOptions();
            $query->whereIn('domain',$domains);
            return $query;
        });
        $this->rulesWidget->bindToController();

        $show_domainrule = $this->makePartial('show_domainrule_list',[
            'inputWidget' => $this->rulesWidget,
        ]);

        if ($refresh) {
            return ['#spanListView' => $this->rulesWidget->render(),'#show_result2' => ''];
        } else {
            return ['result' => $show_domainrule];
        }
        //return $this->rulesWidget->listRefresh();
    }

    public function onDomainruleEdit() {

        $listrecords = $this->getListRecords();
        $id = input('id');
        scartLog::logLine("D-onDomainruleEdit; id=$id");

        $domainrule = Domainrule::find($id);
        if (!$domainrule) {
            Flash::error('Can not find domain rule!?');
            return;
        }
        $domainrule->setInputrecord($listrecords);

        $exclude = [SCART_RULE_TYPE_NONOTSCRAPE,
            SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL,
            SCART_RULE_TYPE_DIRECT_CLASSIFY_NOT_ILLEGAL,
            SCART_RULE_TYPE_LINK_CHECKER,
            SCART_RULE_TYPE_PROXY_SERVICE_API];
        // exclude also types already set
        $type_codes = [SCART_RULE_TYPE_HOST_WHOIS,SCART_RULE_TYPE_REGISTRAR_WHOIS,SCART_RULE_TYPE_SITE_OWNER,SCART_RULE_TYPE_PROXY_SERVICE];
        foreach ($type_codes AS $type_code) {
            if (Domainrule::where('domain',$domainrule->domain)
                ->where('type_code',$type_code)
                ->exists()) {
                $exclude[] = $type_code;
            }
        }
        $domainrule->setRuleExclude($exclude);

        // config FORM widget
        $config = $this->makeConfig('$/abuseio/scart/models/domainrule/fields_domainupdate.yaml');
        $config->model = $domainrule;
        $this->rulesWidget = $this->makeWidget('Backend\Widgets\Form', $config);

        $show_domainrule = $this->makePartial('show_domainrule_edit',[
            'id' => $id,
            'inputWidget' => $this->rulesWidget,
        ]);

        return ['#show_result2' => $show_domainrule];
    }

    public function onDomainruleInsert() {

        $listrecords = $this->getListRecords();
        scartLog::logLine("D-onDomainruleInsert; ");

        $domainrule = new \abuseio\scart\models\Domainrule();
        $domainrule->setInputrecord($listrecords);

        $exclude = [SCART_RULE_TYPE_NONOTSCRAPE,
            SCART_RULE_TYPE_DIRECT_CLASSIFY_ILLEGAL,
            SCART_RULE_TYPE_DIRECT_CLASSIFY_NOT_ILLEGAL,
            SCART_RULE_TYPE_LINK_CHECKER,
            SCART_RULE_TYPE_PROXY_SERVICE_API];
        /*
        // with different domains not possible
        $domains = $domainrule->getDomainOptions();
        $type_codes = [SCART_RULE_TYPE_HOST_WHOIS,SCART_RULE_TYPE_REGISTRAR_WHOIS,SCART_RULE_TYPE_SITE_OWNER,SCART_RULE_TYPE_PROXY_SERVICE];
        foreach ($domains AS $domain) {
            foreach ($type_codes AS $type_code) {
                if (Domainrule::where('domain',$domain)->where('type_code',$type_code)->exists()) {
                    $exclude[] = $type_code;
                }
            }
        }
        */
        $domainrule->setRuleExclude($exclude);

        // config widget
        $config = $this->makeConfig('$/abuseio/scart/models/domainrule/fields_domaininsert.yaml');
        $config->model = $domainrule;
        $this->rulesWidget = $this->makeWidget('Backend\Widgets\Form', $config);

        $show_domainrule = $this->makePartial('show_domainrule_edit',[
            'id' => 0,
            'inputWidget' => $this->rulesWidget,
        ]);

        return ['#show_result2' => $show_domainrule];
    }

    public function onDomainruleDelete() {

        $id = input('id');
        scartLog::logLine("D-onDomainruleDelete; id=$id");

        $domainrule = Domainrule::find($id);
        if (!$domainrule) {
            Flash::error('Can not find domain rule!?');
        } else {
            $domainrule->delete();
            Flash::info('Domain rule deleted');
            return $this->onDomainruleList(0,true);
        }

    }

    public function onDomainruleEditSave() {

        scartLog::logLine("D-onDomainruleEditSave");

        $enabled = input('enabled');
        $domain = input('domain');
        $type_code = input('type_code');
        $ip = input('ip');
        $host_abusecontact_id = input('abusecontact_id');
        $proxy_abusecontact_id = input('proxy_abusecontact_id');
        $inputs = [
            'domain' => $domain,
            'type_code' => $type_code,
            'ip' => $ip,
            'host_abusecontact_id' => $host_abusecontact_id
        ];
        trace_log($inputs);

        // validate input
        $validator = Validator::make(
            $inputs,
            [
                'domain' => 'required|string',
                'type_code' => 'required',
                'ip' => 'required_if:type_code,proxy_service',
                'host_abusecontact_id' => 'required_if:type_code,proxy_service,site_owner,host_whois,registrar_whois',
            ],
            [
                'required' => 'The :attribute field is required',
            ]
        );

        if ($validator->passes()) {

            $domainrule = Domainrule::where('domain',$domain)
                ->where('type_code',$type_code)
                ->first();
            if ($domainrule) {
                //Flash::error('Domain rule already exists - change this within the RULES function');
                $domainrule->ip = $ip;
                $domainrule->abusecontact_id = $host_abusecontact_id;
                $domainrule->proxy_abusecontact_id = $proxy_abusecontact_id;
                $domainrule->enabled = $enabled;
            } else {
                // make domainrule
                $domainrule = new Domainrule();
                $domainrule->domain = $domain;
                $domainrule->type_code = $type_code;
                $domainrule->ip = $ip;
                $domainrule->abusecontact_id = $host_abusecontact_id;
                $domainrule->proxy_abusecontact_id = $proxy_abusecontact_id;
                $domainrule->enabled = $enabled;
            }
            $domainrule->save();

            // proces on input
            //return $this->onDomainruleRunSave();
            return $this->onDomainruleList(0,true);

        } else {
            throw new ValidationException($validator);
        }

    }

    public function onCheckProxy() {

        $domain = input('domain');

        $resultproxy = Domainrule::getProxyRealIP($domain);

        if ($resultproxy['error'] == '') {
            Flash::info('Got real IP from proxy service hoster ' . $resultproxy['proxy_service_owner']);
            return [
                'proxy_service_owner' => $resultproxy['proxy_service_owner'],
                'proxy_service_id' => $resultproxy['proxy_service_id'],
                'real_ip' => $resultproxy['real_ip'],
                'real_host_contact' => $resultproxy['real_host_contact'],
                'real_host_contact_id' => $resultproxy['real_host_contact_id'],
            ];
        } else {
            Flash::warning($resultproxy['error']);
        }

    }

    public function onDomainruleEditClose() {

        return $this->onDomainruleList(0,true);
    }


    public function onDomainruleRun() {

        $listrecords = $this->getListRecords();

        // give time
        set_time_limit(0);
        // disabled -> already done for process
        //$memory_limit = scartScheduler::setMinMemory('2G');
        //scartLog::logLine("D-scartGrade.onDomainruleRunSave start; give time (timeout=0) and set memory_limit=" . $memory_limit);

        $items = scartGrade::getItems($listrecords);
        foreach ($items AS $item) {
            // verify WhoIs
            $whois = scartWhois::verifyWhoIs($item, false);
            if ($whois['status_success']) {
                // in verifyWhoIs changes of record
                $item->save();
            }
        }

        // refresh
        return Redirect::refresh();

    }

    /**
     * SELECTED ILLEGAL button
     *
     * @return mixed
     */
    public function onSelectedImageIllegal() {

        $workuser_id = scartUsers::getId();
        scartLog::logLine("D-onSelectedImageIllegal");

        $listrecords = $this->getListRecords();
        $items = scartGrade::getSelectedItems($workuser_id,$listrecords);
        if (count($items) > 0) {
            // pak de eerste als referentie
            $item = $items[0];
            $first = Input::find($item->input_id);
            // multiselect classificatie
            $show_questions = $this->show_grade_questions(SCART_GRADE_QUESTION_GROUP_ILLEGAL,$workuser_id,false,$first);
        } else {
            Flash::error('No one SELECTED');
            $show_questions = $this->makePartial('show_close_popup');
        }

        return $show_questions;
    }

    /**
     * SELECTED NOT ILLEGAL button
     *
     * @return mixed
     */
    public function onSelectedImageNotIllegal() {

        $workuser_id = scartUsers::getId();
        scartLog::logLine("D-onSelectedImageIllegal");

        $listrecords = $this->getListRecords();
        $items = scartGrade::getSelectedItems($workuser_id,$listrecords);
        if (count($items) > 0) {
            // pak de eerste als referentie
            $item = $items[0];
            $first = Input::find($item->input_id);
            $show_questions = $this->show_grade_questions(SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL,$workuser_id,false,$first);
        } else {
            Flash::error('No one SELECTED');
            $show_questions = $this->makePartial('show_close_popup');
        }

        return $show_questions;
    }

    /**
     * SELECTED IGNORE
     *
     * @return array
     */
    public function onSelectedImageIgnore() {

        $workuser_id = scartUsers::getId();
        scartLog::logLine("D-onSelectedImageIgnore; workuser_id=$workuser_id ");

        $showresult = '';

        $listrecords = $this->getListRecords();
        $items = scartGrade::getSelectedItems($workuser_id,$listrecords);
        if (count($items) > 0) {

            $setbuts = [];

            $cntdone = 0;
            foreach ($items AS $item) {

                $item = Input::find($item->input_id);

                // set select off
                scartGrade::setGradeSelected($workuser_id, $item->id, false);

                // zet status on SCART_GRADE_IGNORE
                $item->classify_status_code = SCART_STATUS_CLOSE;
                $item->grade_code = SCART_GRADE_IGNORE;
                $item->logText("Set status_code on: " . $item->classify_status_code);
                $item->save();

                $setbutton = $this->setButtons($item,$workuser_id,false);
                $setbutton['hash'] = $item->filenumber;
                $setbuts[] = $setbutton;

                $cntdone += 1;

            }

            if ($this->getShowHide() != '') {
                $showresult = $this->makePartial('js_refreshscreen');
            } else {
                $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts, 'resetall' => true]);
                Flash::info("Items ($cntdone) set on ignore");
            }

        } else {
            Flash::error('No one SELECTED');
        }

        return ['show_result' => $showresult ];
    }



    /**
     * SELECTED Manual button
     *  //SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL
     * @return mixed
     */
    public function onSelectedImageManual() {

        $workuser_id = scartUsers::getId();
        scartLog::logLine("D-onSelectedImageManual; workuser_id=$workuser_id ");

        $showresult = '';

        $listrecords = $this->getListRecords();
        $items = scartGrade::getSelectedItems($workuser_id,$listrecords);
        if (count($items) > 0) {

            $setbuts = [];

            $cntdone = 0;
            foreach ($items AS $item) {

                $item = Input::find($item->input_id);

                // set select off
                scartGrade::setGradeSelected($workuser_id, $item->id, false);


                // like the example onManualCheck: set status on SCART_GRADE_IGNORE
                if ($item && $item->grade_code == SCART_GRADE_ILLEGAL) {
                    $item->classify_status_code = ($item->classify_status_code == SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) ? SCART_STATUS_SCHEDULER_CHECKONLINE : SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL;
                    $item->logText("Set status_code=$item->classify_status_code");
                    $item->save();
                }


                $setbutton = $this->setButtons($item,$workuser_id,false);
                $setbutton['hash'] = $item->filenumber;
                $setbuts[] = $setbutton;

                $cntdone += 1;

            }

            if ($this->getShowHide() != '') {
                $showresult = $this->makePartial('js_refreshscreen');
            } else {
                $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts, 'resetall' => true]);
                Flash::info("Items ($cntdone) set on manual");
            }

    } else {

            Flash::error('No one SELECTED');

        }

        return ['show_result' => $showresult ];
    }




    /**
     * SELECTED FIRST POLICE
     *
     * @return array
     */
    public function onSelectedPolice() {

        $workuser_id = scartUsers::getId();
        scartLog::logLine("D-onSelectedPolice; workuser_id=$workuser_id ");

        $workuser_id = scartUsers::getId();
        scartLog::logLine("D-onSelectedImageIllegal");

        $show_questions = '';
        $listrecords = $this->getListRecords();
        $items = scartGrade::getSelectedItems($workuser_id,$listrecords);
        if (count($items) > 0) {

            // check if illegal
            $valid = true;
            foreach ($items AS $item) {
                $item = Input::find($item->input_id);
                if ($item->grade_code != SCART_GRADE_ILLEGAL)  {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                // pak de eerste als referentie
                $item = $items[0];
                $first = Input::find($item->input_id);
                $show_questions = $this->show_grade_questions(SCART_GRADE_QUESTION_GROUP_POLICE,$workuser_id,false,$first);
            } else {
                Flash::warning('All selected image(s) must be set on illegal');
                $show_questions = $this->makePartial('show_close_popup');
            }

        } else {
            Flash::error('No one SELECTED');
            $show_questions = $this->makePartial('show_close_popup');
        }

        return $show_questions;
    }

    public function onSelectedInputEdit() {

        $workuser_id = scartUsers::getId();
        scartLog::logLine("D-onSelectedInputEdit; workuser_id=$workuser_id ");

        $listrecords = $this->getListRecords();
        $items = scartGrade::getSelectedItems($workuser_id,$listrecords);
        if (count($items) > 0) {

            // get first item as base
            $record_id = $items[0]->input_id;
            $rec = Input::find($record_id);

            // config (reuse) widget
            $config = $this->makeConfig('$/abuseio/scart/models/input/fields_grade_multi.yaml');
            $config->model = $rec;
            $this->inputWidget = $this->makeWidget('Backend\Widgets\Form', $config);

            $show_edit = $this->makePartial('show_input_edit',[
                'single' => false,
                'record_id' => $record_id,
                'workuser_id' => $workuser_id,
                'inputWidget' => $this->inputWidget,
            ]);

        } else {
            Flash::error('No one SELECTED');
            $show_edit = $this->makePartial('show_close_popup');
        }

        return $show_edit;
    }


    /** SUB FUNCTIONS **/


    public function setButtonSelect($item,$workuser_id,$select='') {

        // selected
        if ($select==='') $select = scartGrade::getGradeSelected($workuser_id, $item->id);

        $buttonsets = [
            'SELECT' => ($select) ? 'true' : 'false',
        ];

        //scartLog::logLine("D-hash=$item->filenumber, id=$item->id, sel=$select");

        return [
            'class' => '',
            'buttonsets' => $buttonsets,
        ];
    }

    /**
     * Set button according status
     *
     * @param $item
     * @return array
     */
    public function setButtons($item,$workuser_id,$select='') {

        $class = '';
        $buttonsets = [];

        // selected
        if ($select==='') $select = scartGrade::getGradeSelected($workuser_id, $item->id);

        // default set
        $buttonsets = [
            'SELECT' => ($select) ? 'true' : 'false',
            'YES' => 'false',
            'IGNORE' => 'false',
            'NO' => 'false',
        ];

        // specific set
        if ($item->grade_code == SCART_GRADE_ILLEGAL) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'true',
                'IGNORE' => 'false',
                'NO' => 'false',
            ];
            $class = 'grade_button_illegal';

        } elseif ($item->grade_code == SCART_GRADE_IGNORE) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'false',
                'IGNORE' => 'true',
                'NO' => 'false',
            ];
            $class = 'grade_button_ignore';

        } elseif ($item->grade_code == SCART_GRADE_NOT_ILLEGAL) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'false',
                'IGNORE' => 'false',
                'NO' => 'true',
            ];
            $class = 'grade_button_notillegal';

        }

        $buttonsets['POLICE'] = ( ($item->classify_status_code==SCART_STATUS_FIRST_POLICE) ? 'true' : 'false');

        $buttonsets['MANUAL'] = ( ($item->classify_status_code==SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) ? 'true' : 'false');

        return [
            'class' => $class,
            'buttonsets' => $buttonsets,
        ];
    }

    /**
     * @description This function lookalike show_grade_answers but with less complexity.
     * The code becomes more unreadable when you mix the functions
     *
     * @param $rec (The information comes directly from the input table.)
     * @param bool $show_questions
     * @return bool|mixed
     */
    public function show_input_answers($rec, $show_questions = false){

        if ($rec->id) {
            // get the input and check if it exists
            if($input = Input::find($rec->id)) {

                // Init object for view. To keep the style the same
                // I chose to make the object allmost the same as  the method show_grade_answers this to avoid extra complexity
                $questions           = new \stdClass();
                $questions->note     = $input->note;
                $questions->ntdnote  = $input->ntd_note;
                $questions->type     = $input->type_code;


                $inputSource = new Input_source();
                $options = $inputSource->getSourceOptions();
                if(array_key_exists($input->source_code, $options)) {
                    $questions->source_code = $options[$input->source_code];
                }


                // I made a smaller view for to show this data.
                $show_questions = $this->makePartial('popover/show_input_answers',[ 'questions' => $questions]);
            }
        }
        return $show_questions;
    }

    /**
     * @description Get the questions and answers. Return a table view with those information.
     * @param $questiongroup
     * @param $workuser_id
     * @param $rec
     * @param bool $show_questions
     * @return bool|mixed
     */
    public function show_grade_answers($rec, $show_questions = false)
    {

        // check if the input (abuseio_scart_input) record has an id
        if ($rec->id) {
            // get the questions
            $grades  = Grade_question::where('questiongroup', SCART_GRADE_QUESTION_GROUP_ILLEGAL)->orderBy('sortnr')->get();

            foreach ($grades AS $grade) {
                // get answer by record id and grade_question_id
                $value = Grade_answer::where([['record_id', $rec->id],['grade_question_id', $grade->id]])->first();
                // check if there's a answer
                $values = ($value) ? unserialize($value->answer) : [];
                if (empty($values)) $values = [];

                // Init object for question / answer..
                $question           = new \stdClass();
                $question->type     = $grade->type;
                $question->label    = $grade->label;
                $question->name     = $grade->name;
                $question->iccam    = !empty($grade->iccam_field);
                $question->value    = [];

                // get answer
                if (count($values) > 0 && ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio')) {
                    $options = [];
                    $opts = Grade_question_option::where('grade_question_id',$grade->id)->orderBy('sortnr')->get();
                    foreach ($opts AS $opt) {
                        if(in_array($opt->value,$values)) {
                            $question->value = [$opt->label];
                            break;
                        }
                    }
                } else {
                    $question->value = $values;
                }
                // store object for view
                $questions[] = $question;
            } // end foreach

            // build view for popover
            $show_questions = $this->makePartial('popover/show_grade_questions',[ 'questions' => $questions]);
        }

        return $show_questions;
    }
    /**
     * Setup screen with questions
     *
     * single=true
     * - record=item; show image
     *
     * single=false
     * - record=input; show no image;
     *
     * @param $questiongroup
     * @param $workuser_id
     * @param $single
     * @param $rec
     * @return mixed
     */
    public function show_grade_questions($questiongroup,$workuser_id,$single,$rec) {

        if ($single) {
            $imgsize = scartImage::getImageSizeAttr($rec,250);
        }

        $answer_record_id = '';
        if (!$single) {
            // $rec is first from items selected
            $answer_record_id = $rec->id;
        } else {
            $answer_record_id = $rec->id;
        }
        // multiply records
        $recordtype = SCART_INPUT_TYPE;
        scartLog::logLine("D-show_grade_questions; answer_record_id=$answer_record_id, questiongroup=$questiongroup, single=$single, recordtype=$recordtype");

        $gradeitems = ($rec->url_type==SCART_URL_TYPE_MAINURL) ? 'INPUT' : (($rec->url_type==SCART_URL_TYPE_VIDEOURL) ? 'VIDEO' : 'IMAGE');
        $gradeitems .= (!$single) ? 'S' : '';

        if ($answer_record_id) {

            $questions = [];
            // questions depending on url_type
            $grades  = Grade_question::getClassifyQuestions($questiongroup,$rec->url_type);

            $toggle = true;
            foreach ($grades AS $grade) {

                $value = Grade_answer::where('record_type',$recordtype)->where('record_id',$answer_record_id)->where('grade_question_id',$grade->id)->first();
                $values = ($value) ? unserialize($value->answer) : '';
                //scartLog::logLine("D-show_grade_questions; question=$grade->name, type=$grade->type, values=" . implode(',', $values));

                $question = new \stdClass();
                $question->type = $grade->type;
                $question->label = $grade->label;
                $question->name = $grade->name;
                $question->leftright = $grade->span;
                //$question->leftright = ($toggle) ? 'left' : 'right';
                $toggle = !$toggle;

                if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

                    if ($values=='') $values = array();

                    $options = [];
                    $opts = Grade_question_option::where('grade_question_id',$grade->id)->orderBy('sortnr')->get();
                    foreach ($opts AS $opt) {
                        $option = new \stdClass();
                        $option->sortnr = $opt->sortnr;
                        $option->value = $opt->value;
                        $option->label = $opt->label;
                        $option->selected =  (in_array($option->value,$values) ? 'selected' : '');
                        $options[] = $option;
                    }
                    $question->options = $options;

                } elseif ($question->type == 'text') {

                    $question->value = $values;

                }

                $questions[] = $question;
            }

            // if single and url set then show image
            $src = ($single) ? scartBrowser::getImageCache($rec->url,$rec->url_hash) : '';

            $gradeheader = (($questiongroup==SCART_GRADE_QUESTION_GROUP_ILLEGAL) ? 'ILLEGAL' :
                (($questiongroup==SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL) ? 'NOT ILLEGAL' : 'FIRST POLICE') );

            $params = [
                'gradeitems' => $gradeitems,
                'gradeheader' => $gradeheader,
                'single' => $single,
                'record_id' => $rec->id,
                'recordtype' => $recordtype,
                'workuser_id' => $workuser_id,
                'questiongroup' => $questiongroup,
                'src' => $src,
                'imgsize' => (($single) ? $imgsize : ''),
                'questions' => $questions,
            ];

            $show_questions = $this->makePartial('show_grade_questions',$params);

        } else {

            scartLog::logLine("D-show_grade_questions; NO ITEMS (MORE) SELECTED!?");
            $show_questions = false;
        }

        return $show_questions;
    }

    /**
     * Main function receiving SAVE question (answers)
     *
     * single=true
     * - set answer for item or input
     *
     * single=false
     * - set answer for selected items
     *
     * @return array
     */
    public function onQuestionsSave() {

        $single = input('single');

        $workuser_id = scartUsers::getId();
        $record_id = input('record_id');
        $questiongroup = input('questiongroup');
        // always input type
        $recordtype = SCART_INPUT_TYPE;
        scartLog::logLine("D-onQuestionsSave; single=$single, record_id=$record_id, recordtype=$recordtype ");

        // set buttons
        $setbuts = []; $showresult = '';

        if ($single) {
            $rec = new \stdClass();
            $rec->input_id = $record_id;
            $recs = array($rec);
        } else {
            $listrecords = $this->getListRecords();
            $recs = scartGrade::getSelectedItems($workuser_id,$listrecords);
        }

        foreach ($recs AS $rec) {

            // set select off
            scartGrade::setGradeSelected($workuser_id, $rec->input_id, false);

            $item = Input::find($rec->input_id);

            // questions depending on url_type
            $grades  = Grade_question::getClassifyQuestions($questiongroup,$item->url_type);

            foreach ($grades AS $grade) {
                $inp = input($grade->name, '');
                $ans = Grade_answer::where('record_type',$recordtype)
                    ->where('record_id', $item->id)
                    ->where('grade_question_id', $grade->id)
                    ->first();
                if ($ans == '') {
                    $ans = new Grade_answer();
                    $ans->record_type = $recordtype;
                    $ans->record_id = $item->id;
                    $ans->grade_question_id = $grade->id;
                }
                // serialize -> multiselect values also
                $ans->answer = serialize($inp);
                $ans->save();
                //scartLog::logLine("D-Save question '$grade->name' (id=$grade->id), record_id=$item->id, answer=$ans->answer");
            }

            if ($questiongroup == SCART_GRADE_QUESTION_GROUP_ILLEGAL) {
                $item->classify_status_code = SCART_STATUS_SCHEDULER_CHECKONLINE;
                $item->grade_code = SCART_GRADE_ILLEGAL;
            } elseif ($questiongroup==SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL) {
                $item->classify_status_code = SCART_STATUS_CLOSE;
                $item->grade_code = SCART_GRADE_NOT_ILLEGAL;
            } elseif ($questiongroup==SCART_GRADE_QUESTION_GROUP_POLICE) {
                $item->classify_status_code = SCART_STATUS_FIRST_POLICE;
                $item->grade_code = SCART_GRADE_ILLEGAL;
            }
            $item->logText("Set classify_status_code on: " . $item->classify_status_code . ", grade_code=" . $item->grade_code);
            $item->save();

            $setbutton = $this->setButtons($item, $workuser_id, false);
            $setbutton['hash'] = $item->filenumber;
            $setbuts[] = $setbutton;


        }

        if ($this->getShowHide() != '') {
            $showresult .= $this->makePartial('js_refreshscreen');
        } else {
            $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts, 'resetall' => true]);
            if (!$single) {
                Flash::info('Items classified');
            }
        }

        return ['show_result' => $showresult ];
    }

    /** BUTTONS **/

    public function onShowHide() {

        $hide = input('hide');
        if ($hide=='') {
            $show = $current = '';
        } else {
            $current = $this->getShowHide();
            if ($current==$hide) {
                $show = '';
            } else {
                $show = $hide;
            }
        }
        $this->setShowHide($show);
        scartLog::logLine("D-onShowHide; hide=$hide, current=$current, show=$show");
        return $this->nextCall();
    }

    public function onColumnSet() {

        $columnsize = input('columnsize');
        if ($columnsize=='') $columnsize = '3';
        $this->setColumnsize($columnsize);
        scartLog::logLine("D-onColumnSet; columnsize=$columnsize");
        return $this->nextCall(0,$columnsize);
    }

    public function onViewType() {

        $viewtype = input('viewtype');
        if (!in_array($viewtype,[SCART_CLASSIFY_VIEWTYPE_LIST,SCART_CLASSIFY_VIEWTYPE_GRID])) {
            $viewtype = Systemconfig::get('abuseio.scart::classify.viewtype_default',SCART_CLASSIFY_VIEWTYPE_GRID);
        }
        $this->setViewType($viewtype);
        scartLog::logLine("D-onViewType; viewtype=$viewtype");
        return $this->nextCall(0,0,'',true);
    }

    public function onRecordsSet() {

        $records = input('records');
        if ($records=='') $records = '5';
        $this->setDisplayRecords($records);
        scartLog::logLine("D-onRecordsSet; records=$records");
        return $this->nextCall();
    }

    public function onRecordsSort() {
        $sort = input('sort');
        if ($sort=='') $sort = 'filenumber';
        $this->setSortField($sort);
        scartLog::logLine("D-onRecordsSort; sort=$sort");
        return $this->nextCall();
    }

    public function onSelect() {

        $workuser_id = scartUsers::getId();

        $select = input('select');
        $checkboxes = input('checkboxes');
        if (empty($checkboxes)) $checkboxes = [];
        $viewtype = input('viewtype');

        scartLog::logLine("D-onSelect; workuser_id=$workuser_id, select=$select, viewtype=$viewtype, checkboxes=" . implode(',',$checkboxes) );

        $items = $this->getQueryRecords();
        $cnt = count($items);

        $showresult = '';

        if ($cnt > 0) {

            $setbuts = [];

            $cntdone = $cntsel = 0;
            foreach ($items AS $item) {

                if ($viewtype == SCART_CLASSIFY_VIEWTYPE_GRID || in_array($item->id,$checkboxes)) {

                    switch ($select) {
                        case 'all':
                            $sel = true;
                            break;

                        case 'none':
                            $sel = false;
                            break;

                        case 'invers':
                            $sel = !scartGrade::getGradeSelected($workuser_id, $item->id);
                            break;

                        case 'unset':
                            $sel = ($item->grade_code==SCART_GRADE_UNSET);
                            break;
                    }

                    scartGrade::setGradeSelected($workuser_id, $item->id, $sel);

                    $cntsel += ($sel) ? 1 : 0;
                    $setbutton = $this->setButtonSelect($item,$workuser_id,$sel);
                    $setbutton['hash'] = $item->filenumber;
                    $setbuts[] = $setbutton;

                    $cntdone += 1;

                }

            }

            //scartLog::logLine("D-onSelect; stap=2 ");

            switch ($select) {
                case 'all':
                    $seltxt = 'All selected';
                    break;

                case 'none':
                    $seltxt = 'None selected';
                    break;

                case 'invers':
                    $seltxt = 'Inverted selection';
                    break;

                case 'unset':
                    $seltxt = 'selected not classified';
                    break;

            }
            $seltxt .=  ($viewtype == SCART_CLASSIFY_VIEWTYPE_LIST) ? ' (on THIS page)' : '';

            $resetall = ($cntsel != $cnt);
            $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts, 'resetall' => $resetall ]);

            Flash::info("$seltxt ($cntdone)");

        } else {

            scartLog::logLine("D-onSelectALL; NO ITEMS FOUND!?");
            Flash::error('No items?');

        }

        //scartLog::logLine("D-onSelect; done ");

        return ['show_result' => $showresult];
    }


    // on ImageSelect
    public function onImageSelectPage () {

        $checked = input('checked');
        $set = ($checked=='true');
        $checkboxes = input('checkboxes');

        // current user
        $workuser_id = scartUsers::getId();

        if ($checkboxes) {

            $setbuts = [];

            scartLog::logLine("D-onImageSelectPage; set selected=$checked ");

            foreach ($checkboxes AS $checkboxval) {
                $record = Input::find($checkboxval);
                if ($record) {
                    //scartLog::logLine("D-onImageSelectPage; url_type=$record->url_type, workuser=$workuser_id, hash=$record->filenumber, set=$checked ");
                    scartGrade::setGradeSelected($workuser_id, $record->id, $set);
                    $setbutton = $this->setButtons($record,$workuser_id,$set);
                    $setbutton['hash'] = $record->filenumber;
                    $setbuts[] = $setbutton;
                } else {
                    scartLog::logLine("E-onImageSelectPage; cannot find checkboxval=$checkboxval");
                }
            }

            $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts, 'resetall' => !$set]);

            Flash::info('Urls on page ' . (($set) ? 'selected' : 'deselected') );

        } else {
            $showresult = '';
        }

        return ['show_result' => $showresult];
    }

    // on ImageSelect
    public function onImageSelect () {

        $record_id = input('record_id');

        // current user
        $workuser_id = scartUsers::getId();

        if ($workuser_id && $record_id) {

            $toggle = !scartGrade::getGradeSelected($workuser_id, $record_id);
            scartGrade::setGradeSelected($workuser_id, $record_id, $toggle);
            scartLog::logLine("D-onImageSelect; workuser_id=" . $workuser_id . ", record_id=" . $record_id . ", toggle=$toggle");

            $item = Input::find($record_id);
            $setbuttons = $this->setButtons($item,$workuser_id);
            $showresult = $this->makePartial('js_buttonresult',
                ['hash' => $item->filenumber,
                    'buttonsets' => $setbuttons['buttonsets'],
                    'class' => $setbuttons['class']
                ]
            );
            Flash::info('Url ' . (($toggle) ? 'selected' : 'deselected') );

        } else {
            Flash::error('Unknown workuser/input!?');
            $showresult = false;
        }

        return ['show_result' => $showresult ];
    }


    /**
     * @Description  Try te restore the previous session
     * @When 1) user click on the button 'continue rating'.. 2) event
     * @Return redirection
     */

    public function onContinueRating()
    {
        //lastVisitedPage
        if ($input = $this->getCurrentPageNumber()) {
            \Session::put('widget.abuseio_scart-Grade-Lists', base64_encode(serialize(['lastVisitedPage' => $input])));
        }

        // Involved Inputs
        if ($inputs = $this->getInputNumbers()) {
            \Session::put('grade_listrecords', $inputs);
        }

        // redirect to updateS with this sessions
        return Redirect::to('/backend/abuseio/scart/Grade/updates');

    }


    public function onSaveAIattributes()
    {

        //scartLog::logLine("D-onSaveAIattributes; POST=" . print_r(input(), true));
        scartLog::logLine("D-onSaveAIattributes");

        $record_id = input('record_id');
        $showresult = '';

        if ($record_id) {

            $extrafields = Input_extrafield::where('input_id', $record_id)->get();

            $extradata = '{';

            $update = false;
            foreach ($extrafields as $extrafield) {
                if ($extrafield->type == SCART_INPUT_EXTRAFIELD_PWCAI) {
                    $fieldname = $extrafield->type . '_' . $extrafield->label;
                    if ($fieldname != 'PWCAI_Naam_afbeelding') {
                        $correction = input($fieldname);
                        if ($extradata != '{') {
                            $extradata .= ',';
                        }
                        if ($correction != $extrafield->secondvalue) {
                            $extrafield->secondvalue = $correction;
                            $extrafield->save();
                            $update = true;
                        }
                        $extradata .= "'$fieldname': ['$extrafield->value','$extrafield->secondvalue']";
                    }
                }
            }

            $extradata .= '}';

            if ($update) {

                $record = Input::find($record_id);
                $showresult = $this->makePartial('js_extrafieldsupdate',
                    ['extradata' => $extradata, 'hash' => $record->filenumber ]
                );

            }

            if ($update) Flash::info('AI attribute field(s) updated');

        } else {
            Flash::error('No record id is given to update!?');
        }

        return ['show_result' => $showresult];
    }


}
