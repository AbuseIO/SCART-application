<?php

namespace abuseio\scart\console;

use Config;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use abuseio\scart\classes\mail\scartEXIM;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartMail;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\whois\scartWhoisphpWhois;
use abuseio\scart\models\Ntd_template;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class sendCustomNTD extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:sendCustomNTD';

    /**
     * @var string The console command description.
     */
    protected $description = 'Testen NTD MAIL on server';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        $inputfile = $this->option('inputfile');
        if (!file_exists($inputfile)) {
            $this->error("Cannot find inputfile '$inputfile'");
            return;
        }

        $bodyfile = $this->option('bodyfile');
        if (!file_exists($bodyfile)) {
            $this->error("Cannot find bodyfile '$bodyfile'");
            return;
        }

        $debug = $this->option('debug');
        $debug = ($debug=='');
        $debugtxt = ($debug) ? 'ON' : 'OFF';

        // log console options
        $this->info("sendCustomNTD; [debug=$debugtxt]; analyze inputfile '$inputfile'... ");

        $sendarr= [];
        $inputlines = explode("\n", file_get_contents($inputfile));
        $first = true;
        $subject = '';

        foreach ($inputlines AS $inputline) {

            if (!empty($inputline) && !$first) {

                // ip;owner;country;abuse
                $inputarr = explode(';',$inputline);
                if (count($inputarr) > 3) {

                    $ip = trim($inputarr[0]);
                    $name = trim($inputarr[1]);
                    $country = trim($inputarr[2]);
                    $abuseto = trim($inputarr[3]);

                    if ($abuseto) {
                        if (!isset($sendarr[$abuseto])) $sendarr[$abuseto] = [];
                        if (!in_array($ip,$sendarr[$abuseto])) {
                            $sendarr[$abuseto][] = $ip;
                        }
                    }

                }

            } else {
                if ($first) {
                    $subject = str_replace("\n",'',$inputline);
                }

            }
            $first = false;

        }

        $from = 'noreply@nbip.nl';
        $to = 'support@brug-it.nl';
        //$to = 'bureau@nbip.nl';
        $body = file_get_contents($bodyfile);

        // @TO-DO; read subject from bodyfile
        if (!$subject) $subject = 'Kwetsbare services';
        $subject = '[NBIP-NL #'.date('Ymd')."] $subject";

        $this->info("sendCustomNTD; sending NTd's; count(sendarr)=" . count($sendarr) );

        $cnt = 0;
        $first = true;
        if ($debug) $this->info("sendCustomNTD; DEBUG MODE");

        foreach ($sendarr AS $abuseto => $ips) {

            //if ($debug) $ips = ['123.456.789.001','123.456.789.002','123.456.789.003'];
            $attachmentdata = implode("\n",$ips);
            $attachmentname = "CVE-2019-19781-".$cnt.".csv";
            Storage::put($attachmentname,$attachmentdata);
            $attachmentfile = storage_path() . '/app/' . $attachmentname;
            if (!$debug) $to = $abuseto;
            if ((!$debug || $first) && $to) {
                $this->info("SendCustomNTD[$cnt]; to=$to, subject='[abuseto=$abuseto] $subject', count ips=".count($ips) );
                $id = scartMail::sendMailRaw($to,(($debug)?"[abuseto=$abuseto] ":'').$subject,$body,$from,$attachmentfile);
            } else {
                $this->info("SendCustomNTD[$cnt]; SKIP to=$to, subject='[abuseto=$abuseto] $subject', count ips=".count($ips) );
            }

            Storage::delete($attachmentname);

            //$first = false;
            $cnt += 1;

            if ($debug && $cnt > 0) break;

        }


        $this->info("sendCustomNTD; sended $cnt messages ");

        $this->info(str_replace("<br />\n",'',scartLog::returnLoglines()));

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
            ['inputfile', 'i', InputOption::VALUE_OPTIONAL, 'inputfile', ''],
            ['bodyfile', 'b', InputOption::VALUE_OPTIONAL, 'bodyfile', ''],
            ['debug', 'd', InputOption::VALUE_OPTIONAL, 'debug', ''],
        ];
    }


}
