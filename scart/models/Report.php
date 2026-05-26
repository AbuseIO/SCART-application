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
        $ret = [];
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

    public function getSentToEmailPoliceOptions($value,$formData) {

        $recs = Abusecontact::where('police_contact',true)->orderBy('owner')->get();
        $ret = [];
        foreach ($recs AS $rec) {
            $ret[$rec->abusecustom] = $rec->abusecustom;
        }
        return $ret;
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

        $columns = [
            'filenumber' => 'filenumber',
            'reference' => 'reference',
            'url' => 'url',
            'url_host' => 'host',
            'url_ip' => 'ip',
            'url_type' => 'url type',
            'url_referer' => 'referer',
            'received_at' => 'received time',
            'hashcheck_at' => 'hashcheck done at',
            'hashcheck_return' => 'on hashcheck server',
            'firstseen_at' => 'firstseen',
            'lastseen_at' => 'lastseen',
            'type_code' => 'type',
            'source_code' => 'source',
            'status_code' => 'status',
            'note' => 'note',
            'ntd_note' => 'NTD note',
            // special dynamic added fields
            'police' => 'first to police',
            'lea' => 'send to lea',
            'ntd' => 'NTD is send',
            'ntd_at' => 'Last time NTD send',
        ];
        if (Systemconfig::get('abuseio.scart::scheduler.createreports.extrafields',false)) {
            // add extra fields
            $extras = Input_extrafield::all()->unique('label')->values()->all();
            foreach ($extras as $extra) {
                if (!empty($extra->secondvalue) && ($webformfield = ImportWebformField::where('id',$extra->secondvalue)->first())) {
                    if (!$webformfield->not_report) {
                        $columns['extra_'.$extra->label] = 'Extra: '.$extra->label;
                    }
                } else {
                    $columns['extra_'.$extra->label] = 'Extra: '.$extra->label;
                }
            }
        }
        //scartLog::logDump("D-getColumnDefaultOptions; ",$columns);
        return $columns;
    }

    public function getColumnDefaultOptions() {

//        $columns = $this->getColumnOptions('','');
//        // reset not defaults
//        unset($columns['note']);
//        unset($columns['lea']);
//        unset($columns['ntd']);
//        unset($columns['ntd_note']);
//        unset($columns['ntd_at']);
//        return $columns;
//
        return [
            'filenumber' => 'filenumber',
            'reference' => 'reference',
            'url' => 'url',
            'url_host' => 'host',
            'url_ip' => 'ip',
            'url_type' => 'url type',
            'url_referer' => 'referer',
            'received_at' => 'received time',
            'hashcheck_at' => 'hashcheck done at',
            'hashcheck_return' => 'on hashcheck server',
            'firstseen_at' => 'firstseen',
            'lastseen_at' => 'lastseen',
            'type_code' => 'type',
            'source_code' => 'source',
            'status_code' => 'status',
            'note' => 'note',
            'ntd_note' => 'NTD note',
        ];
    }

    public function filterFields ($fields, $context = null) {
    }

    public function beforeCreate() {

        parent::beforeCreate();

        $this->status_code = SCART_STATUS_REPORT_CREATED;
        $this->status_at = date('Y-m-d H:i:s');
        $this->number_of_records = 0;
    }

    public function beforeSave() {

        parent::beforeSave();

        if ($this->filter_type == SCART_REPORT_TYPE_ATTRIBUTE) {

            // force all grade and status
            $this->filter_grade = $this->filter_status = [];

        }
        //scartLog::logLine("filter_grade=" . print_r($this->filter_status,true));

    }

}
