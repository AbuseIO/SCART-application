<?php namespace abuseio\scart\models;

use Backend\Models\ExportModel;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\classes\helpers\scartLog;

class NtdExport extends ExportModel {

    protected $fillable = [
        'startDate',
        'endDate',
        'status_code',
        'abusecontacts',
    ];

    public function exportData($columns, $sessionKey = null) {

        scartLog::logLine("D-onDoExport; start=$this->startDate, end=$this->endDate");

        if ($this->startDate && $this->endDate) {

            $start = substr($this->startDate,0,10) . ' 00:00:00';
            $end = substr($this->endDate,0,10) . ' 23:59:59';

            $ntds = Ntd
                ::where('updated_at','>=',$start)
                ->where('updated_at','<=',$end);
        } else {
            $ntds = Ntd::where('id','>',0);
        }

        if ($this->status_code != '*') {
            $ntds = $ntds->where('status_code',$this->status_code);
        }

        if ($this->abusecontacts != '*') {
            $ntds = $ntds->where('abusecontact_id',$this->abusecontacts);
        }

        //trace_sql();
        $ntds = $ntds->get();

        if ($ntds) {

            $ntds->each(function($ntd) use ($columns) {

                $ntd->addVisible($columns);

                // convert relations
                $ntd->abusecontact_id = Abusecontact::find($ntd->abusecontact_id)->owner;
                $ntd->number_urls = Ntd_url::where('ntd_id',$ntd->id)->count();

            });

        }

        // [ 'db_name1' => 'Some attribute value', 'db_name2' => 'Another attribute value' ], [...]

        return $ntds->toArray();
    }


    public function getStatusCodeOptions($value,$formData) {

        $recs = Ntd_status::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $ret = [
            '*' => '* - all',
        ];
        foreach ($recs AS $rec) {
            $ret[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        return $ret;
    }

    public function getAbusecontactsOptions($value,$formData) {

        $recs = Abusecontact::orderBy('owner')->select('id','owner','abusecustom')->get();
        // convert to [$code] -> $text
        $ret = [
            '*' => '* - all',
        ];
        foreach ($recs AS $rec) {
            $ret[$rec->id] = $rec->owner . ' - ' . $rec->abusecustom;
        }
        return $ret;
    }


}
