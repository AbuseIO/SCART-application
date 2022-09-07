<?php namespace abuseio\scart\models;

use Db;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\scheduler\scartScheduler;
use abuseio\scart\models\Input;
use abuseio\scart\models\Notification;
use Backend\Models\ExportModel;
use abuseio\scart\classes\helpers\scartLog;

class ReportExport extends ExportModel {

    protected $fillable = [
        'status_code',
        'source_code',
        'grade_code',
        'hosting_country',
        'startDate',
        'endDate',
        'firstntd_at',
    ];

    public function getGradeCodeOptions($value,$formData) {

        $recs = Grade_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array('*' => '* - all classifications');
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getStatusCodeOptions($value,$formData) {

        $recs = Notification_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = array('*' => '* - every status');
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getHostingCountryOptions($value,$formData) {
        $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
        return [
            '*' => '* - all countries',
            $hotlinecountry => $hotlinecountry.' - only in local country',
            'not'.$hotlinecountry => "not $hotlinecountry - outside (not in) local country",
        ];
    }

    public function export($columns, $options) {

        // add grade labels

        if ($this->grade_code == SCART_GRADE_ILLEGAL || $this->grade_code == SCART_GRADE_NOT_ILLEGAL ) {

            // 'DB-name' => 'label'
            $grademeta = Grade_question::getGradeHeaders($this->grade_code);
            if (count($grademeta['labels']) > 0) {
                foreach ($grademeta['labels'] AS $id => $label) {
                    $columns[$id] = $label;
                }
            }

        }
        //trace_log($columns);

        return parent::export($columns, $options);
    }

    function exportRecords($record_type,$filter_grade_code,$filter_status_code,$filter_host_country,$columns,$grademeta) {

        scartLog::logLine("ReportExport; ($record_type,$filter_grade_code,$filter_status_code,$filter_host_country,columns,grademeta)");

        $record_table = ($record_type == SCART_INPUT_TYPE) ? SCART_INPUT_TABLE : SCART_NOTIFICATION_TABLE;
        // 2020/5/14/gs: always Input -> use model for addVisible()
        $queryrecords = Input::where($record_table.'.id','>',0);
        //$queryrecords = Db::table(SCART_INPUT_TABLE)->where('deleted_at',null);

        if ($this->startDate && $this->endDate) {
            $start = substr($this->startDate,0,10) . ' 00:00:00';
            $end = substr($this->endDate,0,10) . ' 23:59:59';
            $queryrecords->where($record_table.'.received_at','>=',$start)
                ->where($record_table.'.received_at','<=',$end);
        }

        if ($filter_grade_code != '*') {
            $queryrecords->where('grade_code', $filter_grade_code);
        }

        if ($filter_status_code != '*') {
            $queryrecords->where('status_code', $filter_status_code);
        }

        if ($filter_host_country != '*') {
            $country = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
            $detect = Systemconfig::get('abuseio.scart::classify.detect_country', '');
            $detect = explode(',',$detect);
            if ($filter_host_country == $country) {
                $queryrecords->join('abuseio_scart_abusecontact', 'abuseio_scart_abusecontact.id', '=', $record_table.'.host_abusecontact_id')
                    ->whereIn(Db::raw('LOWER(abuseio_scart_abusecontact.abusecountry)'), $detect)
                    ->select($record_table.'.*');
            } else {
                $queryrecords->join('abuseio_scart_abusecontact', 'abuseio_scart_abusecontact.id', '=', $record_table.'.host_abusecontact_id')
                    ->whereNotIn(Db::raw('LOWER(abuseio_scart_abusecontact.abusecountry)'), $detect)
                    ->select($record_table.'.*');
            }
        }

        // get data (collection)
        //trace_sql();
        $records = $queryrecords->get();
        $queryrecords = null;

        // Loop trough data
        $records->each(function($record) use ($columns,$record_type,$filter_grade_code,$grademeta) {

            // standard columnds
            $record->addVisible($columns);

            // fill related
            $abusecontact = Abusecontact::find($record->host_abusecontact_id);
            if ($abusecontact) {
                $record->abusecontact = $abusecontact->abusecustom;
                $record->abusecountry = $abusecontact->abusecountry;
                $record->host = $abusecontact->owner;
            }
            if (!empty($record->registrar_abusecontact_id)) $record->registrar = Abusecontact::find($record->registrar_abusecontact_id)->owner;

            // if graded then fill grading answers
            if ($filter_grade_code == SCART_GRADE_ILLEGAL || $filter_grade_code == SCART_GRADE_NOT_ILLEGAL ) {

                if (count($grademeta['labels']) > 0) {
                    foreach ($grademeta['types'] AS $id => $type) {
                        //$value = Grade_answer::where('record_type', $record_type)->where('record_id', $record->id)->where('grade_question_id', $id)->first();
                        $value = Grade_answer::where('record_id', $record->id)->where('grade_question_id', $id)->first();
                        $values = ($value) ? unserialize($value->answer) : '';
                        // $showvvalues = (is_array($values)) ? implode('-', $values) : $values; scartLog::logLine("D-id=$id, type=" . $type  . ", values=" . $showvvalues);
                        if ($type == 'select' || $type == 'checkbox') {
                            if ($values == '') $values = [];
                            foreach ($grademeta['values'][$id] AS $optval => $optlab) {
                                $fld = 'grade_'.$id.'_'.$optval;
                                $record->$fld = (in_array($optval, $values) ? 'y' : 'n');
                            }
                        } elseif ($type == 'radio') {
                            $fld = 'grade_'.$id;
                            if ($values != '') $values = implode('', $values);
                            $record->$fld = (isset($grademeta['values'][$id][$values])) ? $grademeta['values'][$id][$values] : '';
                        } elseif ($type == 'text') {
                            $fld = 'grade_'.$id;
                            if ($values != '') $values = implode('', $values);
                            $record->$fld = $values;
                        }
                        //scartLog::logLine("D-record->$fld=" . $record->$fld);
                    }
                }

            }

        });

        return $records;
    }

    public function exportData($columns, $sessionKey = null) {

        // give ourself memory and time!
        $memory_min = scartScheduler::setMinMemory('2G');
        set_time_limit(0);

        // input filters
        $filter_status_code = $this->status_code;
        $filter_grade_code = $this->grade_code;
        $filter_host_country = $this->hosting_country;

        scartLog::logLine("D-ReportExport; classification=$filter_grade_code, hosting_country=$filter_host_country, start=$this->startDate, end=$this->endDate, memory_min=$memory_min");

        // meta data grading questions
        $grademeta = Grade_question::getGradeHeaders($this->grade_code);

        // SCART_INPUT_TYPE
        $records = $this->exportRecords(SCART_INPUT_TYPE,$filter_grade_code,$filter_status_code,$filter_host_country,$columns,$grademeta);
        $arrrecords = $records->toArray();
        $records = null;

        // return: [ 'db_name1' => 'Some attribute value', 'db_name2' => 'Another attribute value' ], [...]
        return $arrrecords;
    }


}
