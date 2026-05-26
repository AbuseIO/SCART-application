<?php
namespace abuseio\scart\console;

/**
 * Temporary job for check/correct ICCAM
 *
 */

use abuseio\scart\classes\iccam\api2\scartICCAMmapping;
use abuseio\scart\classes\iccam\api2\scartImportICCAM;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\ImportExport_job;
use abuseio\scart\models\Input_parent;
use Illuminate\Console\Command;
use League\Flysystem\Exception;
use abuseio\scart\classes\helpers\scartExportICCAM;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\iccam\scartIccam;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Input;
use Config;
use Symfony\Component\Console\Input\InputOption;

class iccamReadBack extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:iccamReadBack';

    /**
     * @var string The console command description.
     */
    protected $description = 'ICCAM check reports';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {

        $exports = ImportExport_job::withTrashed()->where('status_text','No mainurl')->get();

        $this->info("D-Found ".count($exports)." 'No mailurl' exports ");

        $maininputs = [];
        foreach ($exports as $export) {
            $input_id = trim(substr($export->checksum,strlen('Input-')));
            foreach (Input_parent::where('input_id',$input_id)->get() as $parent) {
                $maininputs[$parent->parent_id] = $input_id;
            }
        }

        $this->info("D-Found ".count($maininputs)." mainurls to check");

        foreach ($maininputs as $parent_id => $input_id) {
            if (ImportExport_job::withTrashed()->where('checksum','Input-'.$parent_id)->count() > 0) {
                $this->info("D-Main input $parent_id already ICCAM exported");
            } else {

                $record = Input::find($parent_id);

                if ($record->url_type==SCART_URL_TYPE_MAINURL) {

                    // add report to export ICCAM
                    $this->info("D-ExportReport [$record->filenumber] is MAINURL; add action exportReport"  );
//                    scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT, [
//                        'record_type' => class_basename($record),
//                        'record_id' => $record->id,
//                    ]);

                } else {

                    $this->warn("W-Input_id=$record->id (url_type=$record->url_type) is NO mainurl?");

                }
            }
        }

        $this->info('D-iccamReadBack; end');
    }


    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments() {
        return [
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions() {
        return [
        ];
    }

}
