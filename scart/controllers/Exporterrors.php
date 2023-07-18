<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\base\scartController;
use Backend\Classes\Controller;
use BackendMenu;

class Exporterrors extends scartController
{
    public $requiredPermissions = ['abuseio.scart.exporterrors'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController',
        'Backend.Behaviors.ImportExportController',
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $importExportConfig = 'config_export.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'Exporterrors');
    }

    /**
     * Filter on status_code=grade
     *
     * @param $query
     */
    public function listExtendQuery($query) {
        //scartLog::logLine("D-listExtendQuery call"); trace_sql();
        $query->withTrashed()
            ->whereIn('action',[SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT])
            ->where('status','<>',SCART_IMPORTEXPORT_STATUS_SUCCESS);
    }


}
