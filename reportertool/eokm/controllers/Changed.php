<?php namespace ReporterTool\EOKM\Controllers;

use reportertool\eokm\classes\ertController;
use BackendMenu;
use October\Rain\Support\Facades\Flash;
use reportertool\eokm\classes\ertBrowser;
use reportertool\eokm\classes\ertExportICCAM;
use reportertool\eokm\classes\ertICCAM2ERT;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;

class Changed extends ertController {
    public $requiredPermissions = ['reportertool.eokm.changed'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController'
    ];
    
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'Changed');
    }

    /**
     * Filter on status_code=grade
     *
     * @param $query
     */
    public function listExtendQuery($query) {
        $query->where('status_code',ERT_STATUS_ABUSECONTACT_CHANGED)->orderBy('filenumber','ASC');
    }

    /**
     * List view is for both inputs and notifications (within the database view).
     * We get (only numeric) values from getChecked within _list_toolbar
     * We have to distinct between input or notifcation record by
     *
     * @param $id
     */

    private $_subrange =  50000000000;
    private function _getRecord($id) {
        if ($id > $this->_subrange) {
            $id -= $this->_subrange;
            $record = Notification::find($id);
        } else {
            $record = Input::find($id);
        }
        return $record;
    }

    public function onCheckNTD() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $record = $this->_getRecord($check);
            if ($record) {
                $record->online_counter = 0;
                $record->status_code = ERT_STATUS_SCHEDULER_CHECKONLINE;
                $record->save();
                ertLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
            }
        }
        Flash::info('NTD process started for selected url(s)');
        return $this->listRefresh();
    }

    public function onClose() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $record = $this->_getRecord($check);
            if ($record) {
                $record->status_code = ERT_STATUS_CLOSE;
                $record->save();
                ertLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");

                // ICCAM

                if (ertICCAM2ERT::isActive()) {

                    if ($record->reference != '') {

                        // CLOSE with MOVED action

                        // ICCAM content moved (outside NL)
                        ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                            'record_type' => class_basename($record),
                            'record_id' => $record->id,
                            'object_id' => $record->reference,
                            'action_id' => ERT_ICCAM_ACTION_MO,
                            'country' => '',
                            'reason' => 'ERT found content moved (outside NL)',
                        ]);

                    }

                }

            }
        }
        Flash::info('Selected url(s) set on closed');
        return $this->listRefresh();
    }

    // if form display

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
