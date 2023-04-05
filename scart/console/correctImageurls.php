<?php
namespace abuseio\scart\console;

/**
 * Tool for cleanup from old records which because of crash or other things
 *
 * working status
 *
 */

use abuseio\scart\classes\iccam\api2\scartExportICCAM;
use abuseio\scart\classes\iccam\api2\scartICCAMfields;
use abuseio\scart\classes\iccam\api2\scartICCAMmapping;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Input_parent;
use Illuminate\Console\Command;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\models\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class correctImageurls extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:correctImageurls';

    /**
     * @var string The console command description.
     */
    protected $description = 'correctImageurls';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // log console options
        $inputfile = $this->option('input', '');
        if (!$inputfile) {
            $this->error("Input file must be specified");
            exit();
        }
        if (!file_exists($inputfile)) {
            $this->error("Cannot read inputfile '$inputfile'");
            exit();
        }

        $inputlines = explode("\n", file_get_contents($inputfile));
        $this->info("correctImageurls; inputfile=$inputfile; lines count=".count($inputlines));

        $cnt = 0;
        //$outputlines = ['filenumber;status;grade;parent filenumber;parent status; parent grade;'];
        foreach ($inputlines AS $inputline) {

            if ($cnt > 30) {

                $filenumber = str_replace(["\r","\n"],'',$inputline);
                if (trim($filenumber)) {

                    $input = Input::where('filenumber',$filenumber)
                        ->where('url_type','<>','mainurl')
                        ->whereIn('status_code',['scheduler_scrape','grade'])
                        ->withTrashed()
                        ->first();

                    // 2022/2/16/Gs: sent CU

                    if ($input) {

                        if (($reportid = scartICCAMinterface::getICCAMreportID($input->reference))) {

                            $this->info("[cnt=$cnt] Sent ICCAM action=CU for: filenumber=$filenumber, grade_code=$input->grade_code, status_code=$input->status_code, iccam reportID=$reportid");

                            scartICCAMmapping::insertERTaction2ICCAM($reportid,SCART_ICCAM_ACTION_CU,$input,'NL','SCART check (Eko) - close');

                        }

                    }


                    /*

                    // check and if parent found then delete

                    if ($input) {

                        // check if parent(s) not with status_code 'scheduler_scrape','grade'
                        $parents = Input::join(SCART_INPUT_PARENT_TABLE, SCART_INPUT_PARENT_TABLE.'.parent_id', '=', SCART_INPUT_TABLE.'.id')
                            ->where(SCART_INPUT_PARENT_TABLE.'.input_id', $input->id)
                            ->get();

                        foreach ($parents AS $parent) {
                            //$this->info("Input filenumber=$filenumber; found parent filenumber=$parent->filenumber (status_code=$parent->status_code)");
                            if ($parent->status_code != 'scheduler_scrape' && $parent->status_code != 'grade' ) {
                                $this->info("Delete input with filenumber=$filenumber (status_code=$input->status_code, received=$input->received_at)");
                                $input->delete();
                                $this->info("Delete input_parent($input->id,$parent->parent_id) ");
                                Input_parent::where('input_id',$input->id)->where('parent_id',$parent->parent_id)->delete();
                            } else {
                                $this->info("Skip delete input with filenumber=$filenumber (parent status_code=$parent->status_code)");
                            }
                        }

                    } else {
                        $this->error("Cannot find imageurl/videourl input with filenumber=$filenumber");
                    }
                    */
                }

            }
            $cnt += 1;
            //if ($cnt > 30) break;

        }

        //$this->info(print_r($outputlines,true));

        // log console work done
        $this->info("correctImageurls done" );

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
            ['input', 'i', InputOption::VALUE_OPTIONAL, 'input', ''],
        ];
    }


}
