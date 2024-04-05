<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\aianalyze\scartAIanalyze;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Systemconfig;
use Redirect;
use Response;
use Backend;
use BackendMenu;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Report;
use abuseio\scart\classes\export\scartExport;

class Reports extends scartController
{
    public $requiredPermissions = ['abuseio.scart.reporting'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Reports');
    }

    public function formExtendFields($form, $fields) {

        if (!scartAIanalyze::isActive()) {
            // disable AI attribute export
            $form->removeField('filter_type');
            $form->removeField('filter_section');
        }

        if (!Systemconfig::get('abuseio.scart::scheduler.createreports.anonymous',false)) {
            $form->removeField('anonymous');
            $form->removeField('sent_to_email');
        }

        if (!Systemconfig::get('abuseio.scart::scheduler.createreports.sendpolice',false)) {
            $form->removeField('sendpolice');
            $form->removeField('sent_to_email_police');
        }

        foreach ($fields AS $field) {
            if ($field->fieldName == 'export_columns') {
                // fill defaults
                if  (is_null($field->value)) {
                    $columns = (new Report())->getColumnDefaultOptions();
                    $export_columns = [];
                    foreach ($columns AS $column) {
                        $export_columns[] = ['column' => $column];
                    }
                    $field->value = $export_columns;

                }
            }
        }

    }



    public function update($recordId, $context=null) {

        // fill download file

        $this->vars['recordId'] = $recordId;
        $report = Report::find($recordId);
        if ($report && $report->status_code==SCART_STATUS_REPORT_DONE) {
            $this->vars['downloadname'] = $report->downloadfile->file_name;
            $this->vars['downloadfile'] = $report->downloadfile->getPath();
        } else {
            $this->vars['downloadname'] = $this->vars['downloadfile'] = '';
        }

        if ($report) {

            // convert filter parameter(s)

            if (!is_array($report->filter_grade)) {
                //scartLog::logLine("D-filter_grade=" . print_r($report->filter_grade,true) );
                if ($report->filter_grade != '*') {
                    $report->filter_grade = [
                        ['filter_grade' => $report->filter_grade],
                    ];
                } else {
                    $report->filter_grade = [];
                }
                $report->save();
                //scartLog::logLine("D-AFTER filter_grade=" . print_r($report->filter_grade,true) );
            }

            //scartLog::logLine("D-BEFORE filter_status=" . print_r($report->filter_status,true) );
            if (!is_array($report->filter_status)) {
                //scartLog::logLine("D-filter_status=" . print_r($report->filter_status,true) );
                if ($report->filter_status != '*') {
                    $report->filter_status = [
                        ['filter_status' => $report->filter_status],
                    ];
                } else {
                    $report->filter_status = [];
                }
                $report->save();
                //scartLog::logLine("D-AFTER filter_status=" . print_r($report->filter_status,true) );
            }

        }

        return $this->asExtension('FormController')->update($recordId, $context=null);
    }

    public function create($context=null) {

        $this->vars['recordId'] = $this->vars['downloadname'] = $this->vars['downloadfile'] = '';

        return $this->asExtension('FormController')->create($context=null);
    }

    public function onRecreate() {

        $id = input('record_id');
        $report = Report::find($id);
        if ($report) {

            $report->status_code = SCART_STATUS_REPORT_CREATED;
            $report->status_at = date('Y-m-d H:i:s');
            $report->number_of_records = 0;
            $report->save();

            // del checksum
            scartExport::delExportJob($report);

            return Redirect::to('/backend/abuseio/scart/reports');
        }
    }

    public function onDownload() {
        // redirect for forcing browser into download
        $id = input('record_id');
        return Redirect::to('/backend/abuseio/scart/reports/download/' . $id);
    }

    public function download() {
        // download file
        $id = isset($this->params[0]) ? $this->params[0] : 0;
        scartLog::logLine("D-Download($id)");
        $report = Report::find($id);
        if ($report) {
            return Response::download($report->downloadfile->getLocalPath(), $report->downloadfile->file_name);
        }
    }


}
