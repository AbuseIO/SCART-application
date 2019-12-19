<?php namespace ReporterTool\EOKM\Models;

use Db;
use reportertool\eokm\classes\ertGrade;
use ReporterTool\EOKM\Models\Notification;
use Backend\Models\ExportModel;
use reportertool\eokm\classes\ertLog;

class ReportExport extends ExportModel {

    protected $fillable = [
        'status_code',
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

        return [
            '*' => '* - all countries',
            'NL' => 'NL - only in Netherlands',
            'notNL' => 'not NL - outside (not in) Netherlands',
        ];
    }

    public function export($columns, $options) {

        // add grade labels

        if ($this->grade_code == ERT_GRADE_ILLEGAL || $this->grade_code == ERT_GRADE_NOT_ILLEGAL ) {

            // 'DB-name' => 'label'
            $grademeta = ertGrade::getGradeHeaders($this->grade_code);
            if (count($grademeta['labels']) > 0) {
                foreach ($grademeta['labels'] AS $id => $label) {
                    $columns[$id] = $label;
                }
            }

        }
        //trace_log($columns);

        return parent::export($columns, $options);
    }

    public function exportData($columns, $sessionKey = null) {

        $filter_status_code = $this->status_code;
        $filter_grade_code = $this->grade_code;
        $filter_host_country = $this->hosting_country;

        ertLog::logLine("D-ReportExport; classification=$filter_grade_code, hosting_country=$filter_host_country, start=$this->startDate, end=$this->endDate");

        // Check filters

        if ($this->startDate && $this->endDate) {
            $start = substr($this->startDate,0,10) . ' 00:00:00';
            $end = substr($this->endDate,0,10) . ' 23:59:59';
            $notifications = Notification
                ::where('reportertool_eokm_notification.firstseen_at','>=',$start)
                ->where('reportertool_eokm_notification.lastseen_at','<=',$end);
        } else {
            $notifications = Notification::where('reportertool_eokm_notification.id','>',0);
        }

        if ($filter_grade_code != '*') {
            $notifications = $notifications->where('grade_code', $filter_grade_code);
        }

        if ($filter_status_code != '*') {
            $notifications = $notifications->where('status_code', $filter_status_code);
        }

        if ($filter_host_country != '*') {
            if ($filter_host_country == 'NL') {
                $notifications = $notifications->join('reportertool_eokm_abusecontact', 'reportertool_eokm_abusecontact.id', '=', 'reportertool_eokm_notification.host_abusecontact_id')
                    ->whereIn(Db::raw('LOWER(reportertool_eokm_abusecontact.abusecountry)'), ['nl','netherlands'])
                    ->select('reportertool_eokm_notification.*');
            } else {
                $notifications = $notifications->join('reportertool_eokm_abusecontact', 'reportertool_eokm_abusecontact.id', '=', 'reportertool_eokm_notification.host_abusecontact_id')
                    ->whereNotIn(Db::raw('LOWER(reportertool_eokm_abusecontact.abusecountry)'), ['nl','netherlands'])
                    ->select('reportertool_eokm_notification.*');
            }
        }

        // get data
        $notifications = $notifications->get();

        // meta data grading questions
        $grademeta = ertGrade::getGradeHeaders($this->grade_code);

        // Loop trough data
        $notifications->each(function($notification) use ($columns,$filter_grade_code,$grademeta) {

            // standard columnds
            $notification->addVisible($columns);

            // first NTD
            /* TO-DO
            $ntd = Ntd::where(ERT_NTD_URL_TABLE.'.record_type',ERT_NOTIFICATION_TYPE)
                    ->where(ERT_NTD_URL_TABLE.'.record_id',$notification->id)
                    ->join(ERT_NTD_TABLE, function($join) {
                        $join->on(ERT_NTD_TABLE.'.id','=',ERT_NTD_URL_TABLE.'.ntd_id')
                            ->where(ERT_NTD_TABLE.'.status_code',ERT_NTD_STATUS_SENT_SUCCES);
                    })
                    ->select(ERT_NTD_TABLE.'.updated_at')
                    ->orderBy(ERT_NTD_TABLE.'.updated_at','desc')
                    ->first();
            $notification->firstntd_at = $ntd->updated_at;
            */

            // fill related
            $abusecontact = Abusecontact::find($notification->host_abusecontact_id);
            if ($abusecontact) {
                $notification->abusecontact = $abusecontact->abusecustom;
                $notification->abusecountry = $abusecontact->abusecountry;
                $notification->host = $abusecontact->owner;
            }
            if (!empty($notification->registrar_abusecontact_id)) $notification->registrar = Abusecontact::find($notification->registrar_abusecontact_id)->owner;

            // if graded then fill grading answers
            if ($filter_grade_code == ERT_GRADE_ILLEGAL || $filter_grade_code == ERT_GRADE_NOT_ILLEGAL ) {

                if (count($grademeta['labels']) > 0) {
                    foreach ($grademeta['types'] AS $id => $type) {
                        $value = Grade_answer::where('record_type', ERT_NOTIFICATION_TYPE)->where('record_id', $notification->id)->where('grade_question_id', $id)->first();
                        $values = ($value) ? unserialize($value->answer) : '';
                        // $showvvalues = (is_array($values)) ? implode('-', $values) : $values; ertLog::logLine("D-id=$id, type=" . $type  . ", values=" . $showvvalues);
                        if ($type == 'select' || $type == 'checkbox') {
                            if ($values == '') $values = [];
                            foreach ($grademeta['values'][$id] AS $optval => $optlab) {
                                $fld = 'grade_'.$id.'_'.$optval;
                                $notification->$fld = (in_array($optval, $values) ? 'y' : 'n');
                            }
                        } elseif ($type == 'radio') {
                            $fld = 'grade_'.$id;
                            if ($values != '') $values = implode('', $values);
                            $notification->$fld = (isset($grademeta['values'][$id][$values])) ? $grademeta['values'][$id][$values] : '';
                        } else {
                            $fld = 'grade_'.$id;
                            if ($values != '') $values = implode('', $values);
                            $notification->$fld = $values;
                        }
                        //ertLog::logLine("D-notification->$fld=" . $notification->$fld);
                    }
                }

            }

        });

        // return: [ 'db_name1' => 'Some attribute value', 'db_name2' => 'Another attribute value' ], [...]

        return $notifications->toArray();
    }


}
