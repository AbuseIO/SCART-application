<?php namespace abuseio\scart\Controllers;

use abuseio\scart\models\Systemconfig;
use abuseio\scart\widgets\Dropdown;
use BackendMenu;
use BackendAuth;
use Config;
use Lang;
use Session;
use Redirect;
use October\Rain\Support\Facades\Flash;
use abuseio\scart\classes\helpers\scartExportICCAM;
use abuseio\scart\classes\iccam\scartICCAMmapping;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\online\scartAnalyzeInput;
use Db;
use Carbon\Carbon;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_source;
use abuseio\scart\models\Input_status;
use abuseio\scart\models\Ntd;
use October\Rain\Translation\Translator;

class Inputs extends scartController {

    public $requiredPermissions = ['abuseio.scart.startpage'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';
    public $importExportConfig = 'config_import_export.yaml';
    protected $dropdownWidget;

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Inputs');
    }

    /**
     * Filter based on dynamic data
     * - notification status
     * - logged in user
     *
     * @param $filter
     */
    public function listFilterExtendScopes($filter) {

        /**
         * NB: add only dynamic fields/options
         *
         */

        $own_work_default = Systemconfig::get('abuseio.scart::options.own_work_default',true);
        $input_status= new Input_status();
        $workuser_id = scartUsers::getId();
        $filter->addScopes([
            'status_code' => [
                'label' => 'status',
                'type' => 'group',
                'conditions' => 'status_code in (:filtered)',
                'options' => $input_status->getStatusOptions(),
            ],
            'workuser_id' => [
                'label' => trans('abuseio.scart::lang.head.my_work'),
                'type' =>'checkbox',
                'default' => $own_work_default,
                'conditions' => "workuser_id=$workuser_id",
            ],
            'grade_code' => [
                'label' => 'classified',
                'type' => 'group',
                'conditions' => 'grade_code in (:filtered)',
                'options' => $input_status->getGradeCodes(),
            ],
            'published_at' => [
                'label' => 'date',
                'type' => 'daterange',
                'modelClass' => 'abuseio\scart\models\Input',
                'conditions' => "received_at >= ':after' AND received_at <= ':before'",
                'default' => $this->myDefaultTime(),
                // set the format in backend preference (login)
            ],
            'url' => [
                'type' => 'group',
                'modelClass' => 'abuseio\scart\models\Input',
                'conditions' => "received_at >= ':after' AND received_at <= ':before'",
                'default' => $this->myDefaultPeriod(),
                'options' => [
                    'lastday' => 'last day',
                    'lastweek' => 'last week',
                    'lastmonth' => 'last month',
                    'lastyear' => 'last year'
                ]
            ],
        ]);

    }


    public function myDefaultPeriod()
    {
        // check if there is defaultime session set.
        if (Session::has('defaultTime')) {
            // get the value of this session (do not destroy))
            if($period = Session::get('defaultTime', 'last day')) {
                $period = strtolower($period);
                return [str_replace(' ', '', $period) => $period];
            }
        }
    }

    /**
     * @Description set default time object for the daterange widget
     * @param  Session variable default time (from on Ajax functionality)
     * @return array
     */
    public function myDefaultTime()
    {

        // check if there is defaultime session set.
        if (Session::has('defaultTime')) {
            // get the value of this session and forget/destroy session
            switch (Session::pull('defaultTime', 'last day')) {
                case "last day":
                    $firstDate = Carbon::parse('yesterday');
                    break;
                case "last week":
                    $firstDate = Carbon::parse('7 days ago');
                    break;
                case "last month":
                    $firstDate = Carbon::parse('last month');
                    break;
                case "last year":
                    $firstDate = Carbon::parse('last year');
                    break;
            }

            // set default value for datepicker
            return [
                0 => $firstDate,
                1 => Carbon::today(),
            ];
        }
    }

    public function listExtendQuery($query) {
        $query->where('url_type',SCART_URL_TYPE_MAINURL);
    }

    public function onAnalyseInput() {

        // get current record
        $input_id = input('input_id');
        $input = Input::find($input_id);
        scartLog::logLine("D-onAnalyseInput; input_id=" . $input_id);

        // analyze Input
        //$input->logText("onAnalyseInput");
        $result = scartAnalyzeInput::doAnalyze($input);
        if (!$result) {
            Flash::error('Analyze error(s)');
        } else {
            Flash::info('Analyze done');
        }

        // init/refresh controller and relation on screen
        $this->initForm($input);
        $this->initRelation($input,'logs');
        $this->initRelation($input,'items');

        return array_merge($this->relationRefresh('logs'), $this->relationRefresh('items') );
    }

    public function onSetScrape() {

        $checked = input('checked');
        if (is_array($checked)) {
            foreach ($checked AS $check) {
                $record = Input::find($check);
                if ($record) {

                    // check if status is checkonline then remove from ntd
                    $record->removeNtdIccam(false);

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_SCHEDULER_SCRAPE,'Inputs; set by analist');

                    $record->status_code = SCART_STATUS_SCHEDULER_SCRAPE;
                    $record->logText("Set status_code=$record->status_code by " . scartUsers::getFullName() );
                    $record->save();
                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
                }
            }
            Flash::info('Scrape process started for selected url(s)');
            return $this->listRefresh();
        }
    }

    public function onSetClassify() {

        $checked = input('checked');
        if (is_array($checked)) {
            foreach ($checked as $check) {
                $record = Input::find($check);
                if ($record) {

                    // check if status is checkonline then remove from ntd
                    $record->removeNtdIccam(false);

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_GRADE,'Inputs; set by analist');

                    $record->status_code = SCART_STATUS_GRADE;
                    $record->logText("Set status_code=$record->status_code by " . scartUsers::getFullName() );
                    $record->save();
                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
                }
            }
            Flash::info('Classify status set for selected url(s)');
            return $this->listRefresh();
        }
    }

    public function onSetCloseOffline() {

        $checked = input('checked');
        if (is_array($checked)) {
            foreach ($checked as $check) {
                $record = Input::find($check);
                if ($record) {

                    // check if status is checkonline then remove from ntd/inform ICCAM
                    $record->removeNtdIccam(true);

                    // log old/new for history
                    $new = ($record->status_code==SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) ? SCART_STATUS_CLOSE_OFFLINE_MANUAL : SCART_STATUS_CLOSE_OFFLINE;
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,$new,'Inputs; set by analist');
                    $record->status_code = $new;
                    $record->logText("Set status_code=$record->status_code by " . scartUsers::getFullName() );
                    $record->save();
                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
                }
            }
            Flash::info('Close (offline) set for selected url(s)');
            return $this->listRefresh();
        }


    }

    /**
     * @description share the incoming data with datarange widget
     * @return  redirect
     */
    public function onSendtoDatarange() {
        $test = 1234;

        if ($period = input('value', false)) {
            Session::put('defaultTime', $period);
        }

        return Redirect::to('/backend/abuseio/scart/Inputs');

    }



    public function onResetfilters()
    {
        // get session of widget
       if ($widget = Session::pull('widget', false)){
            // unserialize to writeble code
           $array = unserialize(base64_decode($widget['abuseio_scart-Inputs-Filter-listFilter']));

           // reset
           unset($array['scope-published_at']);
           unset($array['scope-url']);

           // encode and serialize for the session
           $encodedfilterpref = base64_encode(serialize($array));
           $widget['abuseio_scart-Inputs-Filter-listFilter'] = $encodedfilterpref;

           // make new session
           Session::put('widget', $widget);
       };

    }

}
