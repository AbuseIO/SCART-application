<?php namespace ReporterTool\EOKM\Controllers;

use BackendMenu;
use reportertool\eokm\classes\ertBrowser;
use reportertool\eokm\classes\ertController;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertUsers;
use ReporterTool\EOKM\Models\Input;
use Flash;

class Inputs_import extends ertController
{
    public $implement = [
        'Backend\Behaviors\FormController'
    ];
    
    public $formConfig = 'config_form.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'Import');
    }

    /**
     * After save from model, we have workuser and uploaded file (in system_files)
     *
     * Read importfile and create new Input record if:
     * - format is valid
     * - not already in database
     *
     * <url>;[<referer>];[<workuser>];[<reference>];[<source>];[<type>]
     *
     * Log action on screen and in input_import
     *
     * @param $model
     */
    public function formAfterSave($model) {

        $file = $model->import_file;
        if ($file) {
            ertLog::logLine("D-formAfterCreate: Field.File.path=" . $file->getPath() . ", name=" . $file->getFilename() );

            $data = $file->getContents();

            $rows = preg_split('/\r\n|\r|\n/', $data );

            ertLog::logLine("D-formAfterCreate: aantal regels=" . count($rows) );

            $results = '';
            $impcnt = $linecnt = 0;
            foreach ($rows AS $row) {

                // @TO-DO: line with: <url>;<sourcetype>

                $row = strip_tags($row);
                $row = str_replace("\n",'', $row);

                if (trim($row)!='') {

                    $linecnt += 1;

                    $linearr = explode(';', $row);
                    $url = $linearr[0];
                    $referer = (isset($linearr[1])) ? $linearr[1] : '';
                    $workuser = (isset($linearr[2])) ? $linearr[2] : '';
                    $reference = (isset($linearr[3])) ? $linearr[3] : '';
                    $source = (isset($linearr[4])) ? $linearr[4] : '';
                    $type = (isset($linearr[5])) ? $linearr[5] : '';

                    if (ertBrowser::validateURL($url)) {

                        $input = Input::where('url',$url)->where('deleted_at',null)->first();
                        if ($input=='') {

                            $input = new Input();
                            $input->url = $url;
                            $input->url_referer = $referer;
                            $input->reference = $reference;
                            if ($workuser) {
                                $input->workuser_id = ertUsers::getWorkuserId($workuser);
                                if ($input->workuser_id == 0) {
                                    $results .= "$linecnt: W-Workuser with email '$workuser' NOT found" . PHP_EOL;
                                } else {

                                }
                            }
                            $input->url = $url;
                            $input->status_code = ERT_STATUS_SCHEDULER_SCRAPE;
                            $input->source_code = $source;
                            $input->type_code = $type;
                            $input->workuser_id = $model->workuser_id;
                            $input->save();

                            $input->logText('Imported');

                            $results .= "$linecnt: I-Imported '$row'; status=" . ERT_STATUS_SCHEDULER_SCRAPE . ", source='$source', type=$type" . PHP_EOL;
                            $impcnt += 1;

                        } else {
                            $results .= "$linecnt: W-url '$row' already in database" . PHP_EOL;

                        }

                    } else {
                        $results .= "$linecnt: E-Error; not a valid url: '$row' " . " (format: http[s]://<domain>/<page>)" . PHP_EOL;
                    }

                }

            }

            $results = "Number of rows: " . $linecnt . PHP_EOL .
                "Number imported: $impcnt" . PHP_EOL  . PHP_EOL .
                "Result for each row:" .  PHP_EOL .
                $results .
                'End of file' . PHP_EOL;

            $model->import_result = $results;
            $model->save();


        } else {
            ertLog::logLine("D-formAfterCreate: NO record file");

        }

    }


}
