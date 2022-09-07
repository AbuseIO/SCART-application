<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;

use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\whois\scartWhoisphpWhois;
use Symfony\Component\Console\Input\InputOption;

class Getwhois extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:Getwhois';

    /**
     * @var string The console command description.
     */
    protected $description = 'Getwhois';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $direct = $this->option('direct');
        if ($direct) {

            $ipurl = trim($direct);
            $result = scartWhoisphpWhois::lookupIp($ipurl);
            $this->info("Getwhois; direct=$direct, owner=". $result['host_owner'] . ", country=" . $result['host_country'] . ", abuse email=" . $result['host_abusecontact'] );

        } else {

            $inputfile = $this->option('inputfile');

            if (!file_exists($inputfile)) {
                $this->error("Cannot find inputfile '$inputfile'");
                return;
            }

            $outputfile = $this->option('outputfile');
            if (empty($outputfile)) $outputfile = basename($inputfile) . '-output.csv';

            // log console options
            $this->info("Getwhois; analyze inputfile '$inputfile'... ");

            $lines = [];
            $inputlines = explode("\n", file_get_contents($inputfile));

            $cnt = 0;
            foreach ($inputlines AS $inputline) {

                if (!empty($inputline)) {

                    try {

                        $cnt += 1;

                        $ipurl = trim($inputline);
                        $result = scartWhoisphpWhois::lookupIp($ipurl);

                        if ($result) {
                            $line = "$ipurl;".$result['host_owner'].';'.$result['host_country'].';'.$result['host_abusecontact'];
                            $lines[] = $line;
                            $this->info("Getwhois; line[$cnt]=$line");

                            file_put_contents($outputfile, $line . "\n", FILE_APPEND );

                        }

                    } catch (\Exception $err) {
                        $this->info("Getwhois; line=$line[$cnt]; error: " . $err->getMessage() . "; SKIP");
                    }


                }

            }

            if (count($lines) > 0) {
                $this->info("Getwhois; output in file '$outputfile' ");
            }

            if (scartLog::hasError()) $this->error(scartLog::returnLoglines());

            // log console work done
            $this->info("Getwhois; ". (count($lines) - 1) ." exported" );

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
    protected function getOptions() {
        return [
            ['direct', 'd', InputOption::VALUE_OPTIONAL, 'direct', ''],
            ['inputfile', 'i', InputOption::VALUE_OPTIONAL, 'Inputfile', ''],
            ['outputfile', 'o', InputOption::VALUE_OPTIONAL, 'Outputfile', ''],
        ];
    }


}
