<?php namespace ReporterTool\EOKM\Updates;

use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Ntd;
use ReporterTool\EOKM\Models\Ntd_url;
use Seeder;
use Db;

class SeederFirstntdAtStatus extends Seeder
{
    public function run() {

        // fill firstntd_at

        $inputs = Input::where('grade_code',ERT_GRADE_ILLEGAL)
            ->whereNull('firstntd_at')
            ->select('id','created_at', 'updated_at', 'deleted_at', 'workuser_id', 'filenumber', 'url', 'url_ip', 'url_base', 'url_referer', 'url_host', 'url_hash',
                    'url_type', 'url_image_width', 'url_image_height', 'reference', 'status_code', 'grade_code', 'type_code',
                    'registrar_abusecontact_id', 'host_abusecontact_id', 'note', 'browse_error_retry', 'firstseen_at',
                    'online_counter', 'lastseen_at', 'firstntd_at');


        $records = Notification::where('grade_code',ERT_GRADE_ILLEGAL)
            ->whereNull('firstntd_at')
            ->select('id','created_at', 'updated_at', 'deleted_at', 'workuser_id', 'filenumber', 'url', 'url_ip', 'url_base', 'url_referer', 'url_host', 'url_hash',
                    'url_type', 'url_image_width', 'url_image_height', 'reference', 'status_code', 'grade_code', 'type_code',
                    'registrar_abusecontact_id', 'host_abusecontact_id', 'note', 'browse_error_retry', 'firstseen_at',
                    'online_counter', 'lastseen_at', 'firstntd_at')
            ->union($inputs)
            ->get();

        ertLog::logLine("D-record; count=" . count($records) );

        foreach ($records AS $record) {

            $record_type = strtolower(class_basename($record));
            //trace_sql();
            $ntd = Ntd_url::where(ERT_NTD_URL_TABLE.'.record_type',$record_type)
                ->where(ERT_NTD_URL_TABLE.'.record_id',$record->id)
                ->join(ERT_NTD_TABLE,ERT_NTD_URL_TABLE.'.ntd_id','=',ERT_NTD_TABLE.'.id')
                ->where(ERT_NTD_TABLE.'.status_code',ERT_NTD_STATUS_SENT_SUCCES)
                ->orderBy(ERT_NTD_URL_TABLE.'.created_at','asc')
                ->first();
                

            if ($ntd) {
                ertLog::logLine("D-record; id=$record->id, record_type=$record_type, found=" . (($ntd)?$ntd->status_time:'' ));
                $record->firstntd_at = $ntd->status_time;
                $record->save();
            }

        }

    }
}
