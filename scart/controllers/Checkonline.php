<?php namespace abuseio\scart\Controllers;

use abuseio\scart\models\Ntd;
use October\Rain\Support\Facades\Flash;
use abuseio\scart\classes\base\scartController;
use BackendMenu;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_status;

class Checkonline extends scartController
{
    public $requiredPermissions = ['abuseio.scart.checkonline'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Checkonline');
    }

    public function listExtendQuery($query) {
        $query->whereIn('status_code',[SCART_STATUS_SCHEDULER_CHECKONLINE, SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL]);
    }

    /**
     * Filter based on dynamic data
     * - notification status
     * - logged in user
     *
     * @param $filter
     */
    public function listFilterExtendScopes($filter) {

        $input_status = new Input_status();
        $options = $input_status->getStatusOptions();

        $input_status =  [
            SCART_STATUS_SCHEDULER_CHECKONLINE => $options[SCART_STATUS_SCHEDULER_CHECKONLINE],                 // @TO-DO; lang translation
            SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL => $options[SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL],                 // @TO-DO; lang translation
        ];

        $filter->addScopes([
            'status_code' => [
                'label' => 'status',
                'type' => 'group',
                'conditions' => 'status_code in (:filtered)',
                'options' => $input_status,
//                'default' => $input_status,
            ],
        ]);

        //trace_log($filter->getScopes() );

    }

    public function onCloseOffline() {

        $checked = input('checked');
        if (is_array($checked)) {
            foreach ($checked as $check) {
                $record = Input::find($check);
                if ($record) {

                    // check if status is checkonline then remove from ntd/inform ICCAM
                    $record->removeNtdIccam(true);

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_CLOSE_OFFLINE,'Set by analist');

                    // set status
                    $record->status_code = SCART_STATUS_CLOSE_OFFLINE;
                    $record->logText("Set status_code=$record->status_code by " . scartUsers::getFullName() );
                    $record->save();

                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
                }
            }
            Flash::info('Close (offline) set for selected url(s)');
            return $this->listRefresh();
        }

    }

    // Note: used for testing
    public function onChanged() {

        $checked = input('checked');
        if (is_array($checked)) {
            foreach ($checked as $check) {
                $record = Input::find($check);
                if ($record) {

                    // check if status is checkonline then remove from ntd/inform ICCAM
                    $record->logText("Set on status CHANGED; remove from any (grouping) NTD's");
                    Ntd::removeUrlgrouping($record->url);

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_ABUSECONTACT_CHANGED,'Set by analist');

                    // set status
                    $record->status_code = SCART_STATUS_ABUSECONTACT_CHANGED;
                    $record->logText("Set status_code=$record->status_code by " . scartUsers::getFullName() );
                    $record->save();
                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
                }
            }
            Flash::info('CHANGED set for selected url(s)');
            return $this->listRefresh();
        }

    }

}
