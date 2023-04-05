<?php namespace abuseio\scart\models;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\base\scartModel;
use abuseio\scart\models\Grade_status;
use abuseio\scart\models\Input_status;


/**
 * Model
 */
class Report extends scartModel {
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_report';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'title' => 'required',
        'filter_start' => 'required',
        'filter_end' => 'required',
    ];

    public $attachOne = [
        'downloadfile' => ['System\Models\File',
            'public' => false],
    ];

    protected $jsonable = [
        'filter_grade','filter_status','export_columns',
    ];

    public function getFilterGradeOptions($value,$formData) {

        $recs = Grade_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        //$ret = array('*' => '* - all classifications');
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getFilterStatusOptions($value,$formData) {

        $recs = Input_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        //$ret = array('*' => '* - every status');
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getFilterCountryOptions($value,$formData) {
        $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
        return [
            '*' => '* - all countries',
            $hotlinecountry => $hotlinecountry.' - only in local country',
            'not'.$hotlinecountry => "not $hotlinecountry - outside (not in) local country",
        ];
    }

    public function getStatusCodeOptions($value,$formData) {

        return [
            SCART_STATUS_REPORT_CREATED => 'Create',
            SCART_STATUS_REPORT_WORKING=> 'Working',
            SCART_STATUS_REPORT_DONE => 'Done',
            SCART_STATUS_REPORT_FAILED => 'Failed',
        ];
    }

    public function getColumnOptions($value,$formData) {

        return [
            'filenumber' => 'filenumber',
            'reference' => 'reference',
            'url' => 'url',
            'url_host' => 'url_host',
            'url_ip' => 'url_ip',
            'url_type' => 'url_type',
            'url_referer' => 'url_referer',
            'received_at' => 'received_at',
            'hashcheck_at' => 'hashcheck_at',
            'hashcheck_return' => 'hashcheck_return',
            'firstseen_at' => 'firstseen_at',
            'lastseen_at' => 'lastseen_at',
            'type_code' => 'type_code',
            'police' => 'police',
            'source_code' => 'source_code',
            'status_code' => 'status_code',
            'note' => 'note',
        ];
    }

    public function getColumnDefaultOptions() {

        $columns = $this->getColumnOptions('','');
        // note not default
        unset($columns['note']);
        return $columns;
    }

    public function filterFields ($fields, $context = null) {
    }

    public function beforeCreate() {

        parent::beforeCreate();

        scartLog::logLine("D-beforeCreate");
        $this->status_code = SCART_STATUS_REPORT_CREATED;
        $this->status_at = date('Y-m-d H:i:s');
        $this->number_of_records = 0;
    }

    public function beforeSave() {

        parent::beforeSave();

        if ($this->filter_type == SCART_REPORT_TYPE_ATTRIBUTE) {

            // force correct filters grade and status

            $this->filter_grade = [];
            $this->filter_status = [
                ['filter_status' => SCART_STATUS_FIRST_POLICE],
                ['filter_status' => SCART_STATUS_ABUSECONTACT_CHANGED],
                ['filter_status' => SCART_STATUS_SCHEDULER_CHECKONLINE],
                ['filter_status' => SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL],
                ['filter_status' => SCART_STATUS_CLOSE_OFFLINE],
                ['filter_status' => SCART_STATUS_CLOSE_OFFLINE_MANUAL],
                ['filter_status' => SCART_STATUS_CLOSE],
                ['filter_status' => SCART_STATUS_CLOSE_DOUBLE],
            ];

        }
        //scartLog::logLine("filter_grade=" . print_r($this->filter_status,true));

    }

}
