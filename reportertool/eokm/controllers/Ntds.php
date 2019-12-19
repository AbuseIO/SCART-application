<?php namespace ReporterTool\EOKM\Controllers;

use Flash;
use reportertool\eokm\classes\ertController;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Ntd;
use ReporterTool\EOKM\Models\Ntd_status;
use BackendMenu;

class Ntds extends ertController {

    public $requiredPermissions = ['reportertool.eokm.ntds'];

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
        BackendMenu::setContext('ReporterTool.EOKM', 'Ntds');
    }

    // filter LIST
    public function listExtendQuery($query) {
/*
//            ->join('reportertool_eokm_ntd','reportertool_eokm_ntd.id','=','reportertool_eokm_ntd_url.ntd_id')
            ->whereIn('reportertool_eokm_ntd.status_code',[ERT_NTD_STATUS_QUEUED,ERT_NTD_STATUS_SENT_FAILED,ERT_NTD_STATUS_SENT_SUCCES]);
*/
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

        $filter->addScopes([
            'status_code' => [
                'label' => 'status',
                'type' => 'group',
                'conditions' => 'status_code in (:filtered)',
                'options' => $options,
                'default' => [
                    ERT_NTD_STATUS_QUEUED => $options[ERT_NTD_STATUS_QUEUED],
                    ERT_NTD_STATUS_SENT_SUCCES => $options[ERT_NTD_STATUS_SENT_SUCCES],
                    ERT_NTD_STATUS_SENT_FAILED => $options[ERT_NTD_STATUS_SENT_FAILED],
                ],
            ],
        ]);

    }

}
