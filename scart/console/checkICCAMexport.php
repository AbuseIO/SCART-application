<?php
namespace abuseio\scart\console;

/**
 * OFFLIMITS recover ICCAM API errors
 *
 * Specifieke reperatie script voor OFFLIMITS september 2023
 * Bij de overgang naar de nieuwe ICCAM V3 zijn fouten ontstaan.
 * Met deze handler wordt op basis van een (specifiek) importexport tabel backup
 *
 */

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\ImportExport_job;
use abuseio\scart\models\Input_parent;
use Db;
use abuseio\scart\models\Log;
use Config;
use abuseio\scart\classes\online\scartHASHcheck;
use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

use abuseio\scart\models\Input;
use abuseio\scart\models\Input_history;

class checkICCAMexport extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:checkICCAMexport';

    /**
     * @var string The console command description.
     */
    protected $description = 'Check if exported to ICCAM';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        scartLog::setEcho(true);

        /**
         *
         * Zoek error iccam action exports in saved importexport table
         *
         * Vind hierbij het scart-record en bepaal of deze een iccam reference heeft
         * Zo niet, dan zoek transacties (report + actions) op in actuele importexport en zet deze weer op to-do
         * Wanneer geen exportreport, dan voeg deze toe
         *
         */

        //            ->groupBy(Db::raw('SUBSTRING(checksum,1,14)'))


        $updates = [];

        $alreadydone = [];

        $knownerrors = ['Trace failed','Report not in correct state','no Content provided','Cannot get (ICCAM) contentId of this','incoming request has too many parameters'];

        $info = false;

        $maxcnt = 10;
        $offset = $showcnt = $cnt = 1;

        $filelast = 'lastcnt.txt';
        if (file_exists($filelast)) {
            $last = file_get_contents($filelast);
            $offset = intval($last);
        }

        $transactions = Db::table('abuseio_scart_importexport_job_20230914')
            ->where('interface','iccam')
            ->where('action',SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION)
            ->whereNotNull('deleted_at')
            ->where('status','error')
            ->orderBy('deleted_at')
            ->get();

        $transcnt = count($transactions);

        scartLog::logLine("D-Found iccam action error transactions: $transcnt; offset=$offset" );

        foreach ($transactions as $transaction) {

            if ($cnt >= $offset) {

                $data = (object) unserialize($transaction->data);
                //scartLog::logDump('D-data=',$data);

                $updated = false;

                // het gaat om errors op basis van het niet in ICCAM hebben gezet van (parent) report

                if ($data->object_id == '' && !in_array($data->record_id,$alreadydone)) {

                    if ($cnt % 100 == 0) scartLog::logLine("D-[$transcnt/$cnt/$showcnt] heartbeat; investigate record_id=$data->record_id");

                    $record = Input::find($data->record_id);
                    if ($record) {

                        if ($record->reference == '') {

                            if ($info) scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Record_id=$record->id, transaction=$transaction->deleted_at, no ICCAM reference");

                            // first check if exportreport done for parent -> add if not or check if error then reset

                            if ($record->url_type != SCART_URL_TYPE_MAINURL) {

                                // try to get from mainurl the reportId
                                $parent = Input_parent::where('input_id',$record->id)->first();
                                $parent = Input::find($parent->parent_id);
                                if ($parent) $parent_id = $parent->id;

                            } else {

                                $parent_id = $record->id;

                            }

                            $parentchecksum = 'Input-'.$parent_id;
                            if ($info) scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Record_id=$record->id, check parent transaction; $parentchecksum");
                            $reporttrans = ImportExport_job::where('interface','iccam')
                                ->where('action',SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT)
                                ->where('checksum',$parentchecksum)
                                ->withTrashed()
                                ->first();

                            if ($reporttrans) {

                                if ($reporttrans->status == 'error') {

                                    $unknownerror = true;
                                    foreach ($knownerrors as $knownerror) {
                                        $pos = strpos($reporttrans->status_text,$knownerror);
                                        if ($pos !== false) {
                                            $unknownerror = false;
                                            break;
                                        }
                                    }

                                    if ($unknownerror) {

                                        scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Reset exportreport transaction; checksum=$reporttrans->checksum, action=$reporttrans->action, status=$reporttrans->status");

                                        $reporttrans->status = 'export';
                                        $reporttrans->status_text = '';
                                        $reporttrans->deleted_at = null;
                                        $reporttrans->save();

                                        $updated = true;
                                        $updatedtxt = "Reset exportreport transaction; checksum=$reporttrans->checksum, action=$reporttrans->action, status=$reporttrans->status";

                                    } else {

                                        if ($info) scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Skip reset exportreport transaction; checksum=$reporttrans->checksum, action=$reporttrans->action, status=$reporttrans->status; knownerror=$knownerror");

                                    }

                                } else {
                                    if ($info) scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Already exportreport transaction; checksum=$reporttrans->checksum, action=$reporttrans->action, status=$reporttrans->status");
                                }

                                $reporttrans = ImportExport_job::where('interface','iccam')
                                    ->whereNotNull('deleted_at')
                                    ->where('status','error')
                                    ->where('checksum','LIKE','Input-'.$record->id.'-%')
                                    ->get();

                                if (count($reporttrans) > 0) {
                                    foreach ($reporttrans as $trans) {
                                        scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Reset exportaction transaction; checksum=$trans->checksum, action=$trans->action");

                                        $reporttrans->status = 'export';
                                        $reporttrans->status_text = '';
                                        $reporttrans->deleted_at = null;
                                        $reporttrans->save();

                                        $updated = true;
                                        $updatedtxt = "Reset exportaction transaction; checksum=$trans->checksum, action=$trans->action";

                                    }
                                } else {
                                    if ($info) scartLog::logLine("D-[$transcnt/$cnt/$showcnt] No reset exportaction transaction; no action error(s)");
                                }

                            } else {

                                if ($parent_id) {

                                    $parent = Input::find($parent_id);
                                    if ($parent && !empty($parent->host_abusecontact_id)) {

                                        scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Add exportreport parent transaction; id=$parent_id");

                                        scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT, [
                                            'record_type' => class_basename($record),
                                            'record_id' => $parent_id,
                                        ]);

                                        $updatedtxt = "Add exportreport parent transaction; id=$parent_id";
                                        $updated = true;

                                    } else {
                                        scartLog::logLine("W-[$transcnt/$cnt/$showcnt] no hoster for parent_id=$parent_id !?");
                                    }

                                } else {

                                    scartLog::logLine("W-[$transcnt/$cnt/$showcnt] No parent record for record_id=$record->id !?");

                                }

                            }

                            $alreadydone[] = $data->record_id;

                        } else {

                            if ($info) scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Record_id=$record->id, transaction=$transaction->deleted_at, ICCAM reference=$record->reference");

                            // do reset error exportactions
                            $reporttrans = ImportExport_job::where('interface','iccam')
                                ->whereNotNull('deleted_at')
                                ->where('status','error')
                                ->where('checksum','LIKE','Input-'.$record->id.'-%')
                                ->get();

                            if (count($reporttrans) > 0) {

                                foreach ($reporttrans as $trans) {
                                    scartLog::logLine("D-[$transcnt/$cnt/$showcnt] Reset (retry) exportaction transaction; checksum=$trans->checksum, action=$trans->action");
                                    $reporttrans->status = 'export';
                                    $reporttrans->status_text = '';
                                    $reporttrans->deleted_at = null;
                                    $reporttrans->save();

                                    $updatedtxt = "Reset exportaction transaction; checksum=$trans->checksum, action=$trans->action";
                                    $updated = true;
                                }

                            } else {
                                if ($info) scartLog::logLine("D-[$transcnt/$cnt/$showcnt] No error exportactions found");
                            }

                        }

                    }



                }

                if ($updated) {
                    $updates[] = $updatedtxt;
                    $showcnt += 1;
                    if ($showcnt > $maxcnt) {
                        break;
                        scartLog::logLine("D-Maxcnt=$maxcnt reached");
                    }
                }

            }

            $cnt += 1;

        }

        scartLog::logDump("D-Updates=",$updates);

        file_put_contents( $filelast,"$cnt");

        exit();

        // option paramater
        $file = $this->option('file', '');

        if ($file) {

            $filein = file_get_contents($file);

            $lines = explode("\n",$filein);

            $maxshow = 20;

            scartLog::logLine("D-Number of lines=".count($lines));

            $cnt = $cntshow = 1;
            foreach ($lines as $line) {

                $prms = explode(";",$line);

                if (count($prms) > 1) {

                    $record_time = $prms[0];
                    $record_time = substr($record_time,1);
                    $record_time = substr($record_time,0,strlen($record_time) - 1);

                    if ($record_time > "2023-05-19") {

                        $record_id = $prms[1];
                        $record = Input::find($record_id);

                        if ($record) {

                            if ($record->reference != '') {
                                //scartLog::logLine("D-Record is exported to ICCAM; reference=$record->reference");
                            } else {

                                if ($record->grade_code != SCART_GRADE_ILLEGAL) {
                                    //scartLog::logLine("D-Record is NOT ILLEGAL ");
                                } else {

                                    scartLog::logLine("D-Record; filenumber=$record->filenumber; record_time=$record_time; is illegal with no ICCAM reference; status=$record->status_code");

                                    $logs = Log::where('record_type','abuseio_scart_input')
                                        ->where('record_id',$record_id)
                                        ->orderBy('id','ASC')
                                        ->get();

                                    foreach ($logs as $log) {
                                        scartLog::logLine("D-Record; filenumber=$record->filenumber; log_id=$log->id, time=$log->updated_at, logtext=$log->logtext");
                                    }

                                    $logs = Input_history::where('input_id',$record_id)
                                        ->orderBy('id','ASC')
                                        ->get();

                                    foreach ($logs as $log) {
                                        scartLog::logLine("D-Record; filenumber=$record->filenumber; history_id=$log->id, time=$log->updated_at, tag=$log->tag, comment=$log->comment");
                                    }

                                    $cntshow += 1;
                                    if ($cntshow > $maxshow) {

                                        //break;

                                    }

                                }

                            }


                        } else {
                            scartLog::logLine("W-Not found!? record_id=$record_id");
                        }

                    }

                } else {
                    scartLog::logLine("W-Not valid line $line");
                }


                $cnt += 1;

            }






        } else {

            scartLog::logLine("W-No input FILE -f given");

        }

    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['file', 'f', InputOption::VALUE_OPTIONAL, 'file', ''],
        ];
    }


}
