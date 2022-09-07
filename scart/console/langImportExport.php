<?php

namespace abuseio\scart\console;

use Db;
use Schema;
use Illuminate\Console\Command;
use abuseio\scart\models\Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

if (!defined('CRLF')) define('CRLF',"\n");

class langImportExport extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:langImportExport';

    /**
     * @var string The console command description.
     */
    protected $description = 'Import/export language';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // log console options
        $this->info('langImportExport');

        $lang = $this->option('lang','en');
        $import = $this->option('import','');
        $export = $this->option('export','');

        if ($export) {

            $langfile = 'plugins/abuseio/scart/lang/'.$lang.'/lang.php';
            if (file_exists($langfile)) {

                $this->info("Read lang file '$langfile'...");

                $langcontent = str_replace('<?php','',file_get_contents($langfile));

                $lang = eval($langcontent);

                $exportcsv = 'main;key;subkey;subname;text'.CRLF;

                foreach ($lang AS $mainkey => $maincontext) {

                    foreach ($maincontext AS $key => $context) {

                        if (!is_array($context)) {
                            $context = ['' => $context];
                        }
                        foreach ($context AS $subkey => $subcontext) {

                            if (!is_array($subcontext)) {
                                $subcontext = ['' => $subcontext];
                            }

                            foreach ($subcontext AS $name => $text) {
                                $exportcsv .= "$mainkey;$key;$subkey;$name;$text".CRLF;
                            }

                        }

                    }

                }

                file_put_contents($export,$exportcsv);
                $this->info("Export in file '$export'");

            } else {
                $this->error("language file '$langfile' not found");
            }

        } elseif ($import) {

            if (file_exists($import)) {

                $this->info("Read lang csvfile '$import'...");

                $lines = explode("\n",file_get_contents($import));

                $returncontent = "return [\n";

                $return = [];

                foreach ($lines AS $key => $line) {

                    // skip first (header) line
                    if ($key > 0) {

                        $linearr = explode(';',$line);

                        if (count($linearr) >= 5) {

                            $mainkey = $linearr[0];
                            $key = $linearr[1];
                            $subkey = $linearr[2];
                            $name = $linearr[3];
                            $text = $linearr[4];
                            if (count($linearr) > 5) {
                                for ($i=5;$i<count($linearr);$i++) {
                                    $text .= $linearr[$i];
                                }
                            }
                            $text = str_replace(["\n","\r"],'',$text);

                            if ($key) {
                                if ($subkey) {
                                    if ($name) {
                                        $return[$mainkey][$key][$subkey][$name] = $text;
                                    } else {
                                        $return[$mainkey][$key][$subkey] = $text;
                                    }
                                } else {
                                    $return[$mainkey][$key] = $text;
                                }
                            } else {
                                $return[$mainkey] = $text;
                            }



                        } else {
                            $this->warn("Skip dataline $key; no valid number of columns");
                        }

                    }

                }

                $returneval = '<?php return '.var_export($return,true).";\n\n";

                $this->info('$return ='.$returneval);

                $langdir = 'plugins/abuseio/scart/lang/'.$lang;
                if (!file_exists($langdir)) {
                    mkdir($langdir);
                }
                $langfile = 'plugins/abuseio/scart/lang/'.$lang.'/lang.php';
                file_put_contents($langfile,$returneval);

                $this->info("Export in file '$langfile'");

            } else {
                $this->error("language file '$import' not found");
            }

        } else {

            $this->info("Use: abuseio:langImportExport [-l languagecode] [-i importfile] [-e exportfile]" );

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
            ['import', 'i', InputOption::VALUE_OPTIONAL, 'import', ''],
            ['export', 'e', InputOption::VALUE_OPTIONAL, 'export', ''],
            ['lang', 'l', InputOption::VALUE_REQUIRED, 'lang', ''],
        ];
    }


}
