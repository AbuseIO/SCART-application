<?php
namespace ReporterTool\EOKM\Controllers;

use BackendMenu;
use BackendAuth;
use reportertool\eokm\classes\ertBrowser;
use reportertool\eokm\classes\ertController;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertUsers;
use reportertool\eokm\classes\ertWhois;
use ReporterTool\EOKM\Models\Grade_status;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Notification_status;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Grade_question;
use ReporterTool\EOKM\Models\Grade_question_option;
use ReporterTool\EOKM\Models\Inputbulkimport;
use Flash;
use Log;

class Report extends ertController {

    public $requiredPermissions = ['reportertool.eokm.reporting'];

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
        BackendMenu::setContext('ReporterTool.EOKM', 'Notifications');
    }

    /**
     * Filter based on dynamic data
     * - notification status
     * - logged in user
     *
     * @param $filter
     */
    public function listFilterExtendScopes($filter) {

        $recs = Grade_status::orderBy('sortnr')->select('code','title','description')->get();
        $gradeoptions = array();
        foreach ($recs AS $rec) {
            $gradeoptions[$rec->code] = $rec->title . ' - ' . $rec->description;
        }

        $recs = Notification_status::orderBy('sortnr')->select('code','title','description')->get();
        $statusoptions = array();
        foreach ($recs AS $rec) {
            $statusoptions[$rec->code] = $rec->title . ' - ' . $rec->description;
        }

        $filter->addScopes([
            'grade_code' => [
                'label' => 'classification',
                'type' => 'group',
                'conditions' => 'grade_code in (:filtered)',
                'options' => $gradeoptions,
                'default' => [
                    ERT_GRADE_ILLEGAL => $gradeoptions[ERT_GRADE_ILLEGAL],
                ],
            ],
            'status_code' => [
                'label' => 'status',
                'type' => 'group',
                'conditions' => 'status_code in (:filtered)',
                'options' => $statusoptions,
                'default' => [
                    ERT_STATUS_SCHEDULER_CHECKONLINE => $statusoptions[ERT_STATUS_SCHEDULER_CHECKONLINE],
                    ERT_STATUS_CLOSE => $statusoptions[ERT_STATUS_CLOSE],
                    ERT_STATUS_CLOSE_OFFLINE => $statusoptions[ERT_STATUS_CLOSE_OFFLINE],
                ],
            ],
        ]);

    }

    public function onShowImage() {

        $id = input('id');

        $not = Notification::find($id);
        if ($not) {
            $msgclass = 'success'; $msgtext = 'Image loaded';
        } else {
            $msgclass = 'error'; $msgtext = 'Image (notification) NOT found!?';
            ertLog::logLine("E-".$msgtext);
        }
        $src = ertBrowser::getImageBase64($not->url,$not->url_hash);
        $txt = $this->makePartial('show_image', ['src' => $src, 'msgtext' => $msgtext, 'msgclass' => $msgclass] );
        return ['show_result' => $txt ];
    }


}
