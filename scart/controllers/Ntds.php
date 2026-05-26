<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\mail\scartSendNTD;
use Flash;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_status;
use BackendMenu;
use Illuminate\Support\Facades\Redirect;

class Ntds extends scartController {

    public $requiredPermissions = ['abuseio.scart.ntds'];

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
        BackendMenu::setContext('abuseio.scart', 'NTD');
    }

    /**
     * Filter based on dynamic data
     * - notification status
     * - logged in user
     *
     * @param $filter
     */
    public function listFilterExtendScopes($filter) {

        $recs = Ntd_status::orderBy('sortnr')->select('code','title','description')->get();
        $options = array();
        foreach ($recs AS $rec) {
            $options[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        //scartLog::logLine("D-options=" . print_r($options,true));

        $filter->addScopes([
            'status_code' => [
                'label' => 'status',
                'type' => 'group',
                'conditions' => 'status_code in (:filtered)',
                'options' => $options,
// confusing defaults -> new defaults not set
//                'default' => [
//                    SCART_NTD_STATUS_QUEUED => $options[SCART_NTD_STATUS_QUEUED],
//                    SCART_NTD_STATUS_SENT_SUCCES => $options[SCART_NTD_STATUS_SENT_SUCCES],
//                    SCART_NTD_STATUS_SENT_FAILED => $options[SCART_NTD_STATUS_SENT_FAILED],
//                    SCART_NTD_STATUS_SENT_API_SUCCES => $options[SCART_NTD_STATUS_SENT_API_SUCCES],
//                    SCART_NTD_STATUS_SENT_API_FAILED => $options[SCART_NTD_STATUS_SENT_API_FAILED],
//                ],
            ],
        ]);

    }

    public function onSelectedClose() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $ntd = Ntd::find($check);
            if ($ntd) {
                $ntd->status_code = SCART_NTD_STATUS_CLOSE;
                $ntd->save();
                $ntd->logText("Set manual on $ntd->status_code");
            }
        }
        Flash::info('Selected NTDs closed');
        return $this->listRefresh();
    }

    public function onSendAgain() {

        $ntd_id = input('ntd_id','');
        scartLog::logLine("D-onSendAgain; ntd_id=$ntd_id");

        $ntd = Ntd::where('id',$ntd_id)
            ->first();
        if ($ntd) {
            if (in_array($ntd->status_code,[
                SCART_NTD_STATUS_SENT_FAILED,
                SCART_NTD_STATUS_SENT_SUCCES,
                SCART_NTD_STATUS_SENT_API_SUCCES,
                SCART_NTD_STATUS_SENT_API_FAILED])) {

                $result = scartSendNTD::sendDirectNtd($ntd);

                scartLog::logLine("D-onSendAgain; result=$result");

                Flash::info($result);

            } else {
                Flash::warning('NTD status is '.$ntd->status_code.'; cannot resend');
            }
        } else {
            Flash::warning('NTD not found!?');
        }

        return Redirect::refresh();
    }

}
