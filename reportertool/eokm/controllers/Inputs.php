<?php namespace ReporterTool\EOKM\Controllers;

use BackendMenu;
use BackendAuth;
use Illuminate\Notifications\Console\NotificationTableCommand;
use October\Rain\Support\Facades\Flash;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertController;
use reportertool\eokm\classes\ertAnalyzeInput;
use Db;

use reportertool\eokm\classes\ertUsers;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Input_bulkimport;
use ReporterTool\EOKM\Models\Input_source;
use ReporterTool\EOKM\Models\Input_status;
use ReporterTool\EOKM\Models\Notification;

class Inputs extends ertController {

    public $requiredPermissions = ['reportertool.eokm.startpage'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController',
        'Backend\Behaviors\ImportExportController',
    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';
    public $importExportConfig = 'config_import_export.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'Inputs');
    }

    /**
     * Filter based on dynamic data
     * - notification status
     * - logged in user
     *
     * @param $filter
     */
    public function listFilterExtendScopes($filter) {

        $recs = Input_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $options = array();
        foreach ($recs AS $rec) {
            $options[$rec->code] = $rec->title . ' - ' . $rec->description;
        }

        $recs = Input_source::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $sourceoptions = array();
        foreach ($recs AS $rec) {
            $sourceoptions[$rec->code] = $rec->title . ' - ' . $rec->description;
        }

        $workuser_id = ertUsers::getId();

        $filter->addScopes([
            'source_code' => [
                'label' => 'source',
                'type' => 'group',
                'conditions' => 'source_code in (:filtered)',
                'options' => $sourceoptions,
                'default' => [
                ],
            ],
            'status_code' => [
                'label' => 'status',
                'type' => 'group',
                'conditions' => 'status_code in (:filtered)',
                'options' => $options,
                'default' => [
                    ERT_STATUS_OPEN => $options[ERT_STATUS_OPEN],                 // @TO-DO; lang translation
                    ERT_STATUS_WORKING => $options[ERT_STATUS_WORKING],                 // @TO-DO; lang translation
                    ERT_STATUS_SCHEDULER_SCRAPE => $options[ERT_STATUS_SCHEDULER_SCRAPE],          // @TO-DO; lang translation
                    ERT_STATUS_CANNOT_SCRAPE => $options[ERT_STATUS_CANNOT_SCRAPE],          // @TO-DO; lang translation
                ],
            ],
            'workuser_id' => [
                'label' => 'My work',                               // @TO-DO; lang translation
                'type' =>'checkbox',
                'default' => 1,
                'conditions' => "workuser_id=$workuser_id",
            ],
        ]);

    }

    public function onAnalyseInput() {

        // get current record
        $input_id = input('input_id');
        $input = Input::find($input_id);
        ertLog::logLine("D-onAnalyseInput; input_id=" . $input_id);

        // analyze Input
        //$input->logText("onAnalyseInput");
        $result = ertAnalyzeInput::doAnalyze($input);
        if (!$result) {
            Flash::error('Analyze error(s)');
        } else {
            Flash::info('Analyze done');
        }

        // init/refresh controller and relation on screen
        $this->initForm($input);
        $this->initRelation($input,'logs');
        $this->initRelation($input,'notifications');

        return array_merge($this->relationRefresh('logs'), $this->relationRefresh('notifications') );
    }

    public function getImportGradeQuestions() {
        return Input_bulkimport::getImportGradeQuestions();
    }

    public function onSetScrape() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $record = Input::find($check);
            if ($record) {
                $record->status_code = ERT_STATUS_SCHEDULER_SCRAPE;
                $record->save();
                $record->logText('Set status_code='.$record->status_code);
                ertLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
            }
        }
        Flash::info('Scrape process started for selected url(s)');
        return $this->listRefresh();
    }


    public function onSetClassify() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $record = Input::find($check);
            if ($record) {
                $record->status_code = ERT_STATUS_GRADE;
                $record->save();
                $record->logText('Set status_code='.$record->status_code);
                ertLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
            }
        }
        Flash::info('Scrape process started for selected url(s)');
        return $this->listRefresh();
    }


}
