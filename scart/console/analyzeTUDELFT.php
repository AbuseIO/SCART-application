<?php
namespace abuseio\scart\console;

use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Input;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_url;
use Config;
use abuseio\scart\classes\online\scartHASHcheck;
use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class analyzeTUDELFT extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:analyzeTUDELFT';

    /**
     * @var string The console command description.
     */
    protected $description = 'analyzeTUDELFT worktool';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        //scartLog::setEcho(true);

        $inputfile = $this->option('input', '');
        if (!$inputfile) {
            $this->error("Input file must be specified");
            exit();
        }
        if (!file_exists($inputfile)) {
            $this->error("Cannot read inputfile '$inputfile'");
            exit();
        }

        $outputfile = $this->option('output', '');

        $inputlines = explode("\n", file_get_contents($inputfile));
        $this->info("analyzeTUDELFT; inputfile=$inputfile; lines count=".count($inputlines));

        $test = $this->option('test', '');
        $test = ($test!='');
        if ($test) $this->info("analyzeTUDELFT; TEST MODE");

        if (count($inputlines) > 0) {

            $cnt = $telfound = $telnotfound = $telnotmaileokm = $telnotapieokm = $telempty = $start = $telskip = 0;
            $telntd = $telnotntd = $telntddiff = $telnotinipv = 0;

            try {

                $lasturlapi = $lasturlmail = '';

                $headers = $rows = $ipvlist = $output = [];
                foreach ($inputlines AS $inputline) {

                    if (trim($inputline)) {

                        $inputline = str_replace(["\r","\n"],'',$inputline);

                        $linearr = explode(';',$inputline);
                        //$this->info(print_r($linearr,true) );

                        if ($cnt == 0) {

                            $headers = $linearr;

                        } else {

                            if ($start==0) $start = $linearr[0];

                            /**
                             * [0] = number
                             * [1] = DATUM EOKM
                             * [2] = URL EOKM
                             * [3] = DATUM API IPV
                             * [4] = URL API IPV
                             * [5] = DATUM MAIL IPV
                             * [6] = URL MAIL IPV
                             *
                             */

                            // strip spaces within url

                            $urleokm = $this->stripurl($linearr[2]);
                            $urlapi = $this->stripurl($linearr[4]);
                            $urlmail = $this->stripurl($linearr[6]);

                            if (($urlapi!='' && $lasturlapi!='' && $urlapi == $lasturlapi) || ($urlmail!='' && $lasturlmail!='' && $urlmail == $lasturlmail)) {

                                //$this->info("Skip line[$cnt]: urlapi=$urlapi, lasturlapi=$lasturlapi, urlmail=$urlmail, lasturlmail=$lasturlmail");
                                $telskip += 1;

                            } else {

                                $lasturlapi = $urlapi;
                                $lasturlmail = $urlmail;

                                if ($urleokm!='') {
                                    if ($urleokm==$urlapi || $urleokm==$urlmail) {
                                        $telfound += 1;
                                    } else {
                                        $telnotfound += 1;
                                    }
                                } else {
                                    if ($urlapi!='') {
                                        $telnotapieokm += 1;
                                    } elseif ($urlmail!='') {
                                        $telnotmaileokm += 1;
                                    } else {
                                        $telempty += 1;
                                    }
                                }

                                if ($urlapi!='' || $urlmail!='') {

                                    $url = ($urlmail!='')?$urlmail:(($urlapi!='')?$urlapi:'');
                                    $urltype = ($urlmail!='')?'mail':(($urlapi!='')?'api':'');
                                    if ($url) {

                                        $ipvlist[$url] = $url;

                                        // date
                                        $ipvdate = ($linearr[3]!='')?$linearr[3]:(($linearr[5]!='')?$linearr[5]:'');
                                        $ipvdated = date('Y-m-d',strtotime($ipvdate));

                                        $recs = Ntd_url::where('url',$url)->get();
                                        if ($recs) {

                                            // url can be sent more then 1 time

                                            foreach ($recs AS $rec) {

                                                //$this->info('Url found in SCART NTD');
                                                $status_code = ($urltype=='api') ? 'sent_api_succes' : 'sent_succes';
                                                $ntd = Ntd::where('id',$rec->ntd_id)->where('status_code',$status_code)->first();

                                                // check status
                                                if ($ntd) {

                                                    $outputline = array_fill(0,8,'');
                                                    $outputline[0] = $start;
                                                    $outputline[1] = $url;
                                                    $outputline[2] = $urltype;
                                                    $outputline[3] = $ipvdated;
                                                    $outputline[4] = $ntd->filenumber;
                                                    $outputline[5] = $rec->url;
                                                    $outputline[6] = ($ntd->status_code=='sent_api_succes')?'api':'mail';

                                                    // check sent date
                                                    $ntdated = date('Y-m-d',strtotime($ntd->status_time));
                                                    if ($ipvdated != $ntdated) {
                                                        $telntddiff += 1;
                                                        //$this->info("Url[cnt=$cnt] found in SCART NTD; IPV date=$ipvdated is different from ntd status time=$ntdated");
                                                    }
                                                    $outputline[7] = $ntdated;

                                                    $output[] = ($outputfile) ? implode(';',$outputline) : $outputline;

                                                }

                                            }

                                            $telntd += 1;

                                        } else {
                                            //$this->info('Url not found in SCART NTD');

                                            $telnotntd += 1;

                                        }

                                    }

                                }

                            }

                            if ($start != $linearr[0]) {
                                $this->info("Line number different; start=$start, linearr[0]=".$linearr[0]);
                                $start = $linearr[0];
                            }
                            $start += 1;

                        }

                    }

                    $cnt += 1;
                    if ($cnt == 25 && $test) break;
                }

                // take NTD for periode 2021-04-26 t/m 2021-09-30 and check if in IPV list

                if (!$test) {

                    $this->info('Check if sent more then IPV list...');
                    // IP Volumne = A0000004269
                    $ipvid = '4269';
                    $ntds = Ntd::whereIn('status_code',['sent_api_succes','sent_succes'])
                        ->where('abusecontact_id',$ipvid)
                        ->where('status_time','>=','2021-04-26 00:00:00')
                        ->where('status_time','<=','2021-09-30 23:59:59')->get();
                    foreach ($ntds AS $ntd) {
                        $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
                        foreach ($ntdurls AS $ntdurl) {
                            if (!isset($ipvlist[$ntdurl->url])) {
                                $this->info("Url '$ntdurl->url' not in IPV list; sent at ".$ntd->status_time);

                                $outputline = array_fill(0,8,'');
                                $outputline[4] = $ntd->filenumber;
                                $outputline[5] = $ntdurl->url;
                                $outputline[6] = ($ntd->status_code=='sent_api_succes')?'api':'mail';
                                $ntdated = date('Y-m-d',strtotime($ntd->status_time));
                                $outputline[7] = $ntdated;
                                $output[] = ($outputfile) ? implode(';',$outputline) : $outputline;

                                $telnotinipv += 1;
                            }
                        }
                    }

                }

                // output

                $outputheader = [
                    'Ref IPV','IPV url','IPV mail/api','IPV date','NTD filenumber','NTD url','NTD mail/api','NTD sent',
                ];

                if ($outputfile) {

                    $lines = [implode(';',$outputheader)];
                    $lines = array_merge($lines,$output);
                    file_put_contents($outputfile, implode("\n", $lines) );

                } else {

                    $this->table($outputheader,$output);

                }

                $this->info("Totaal aantal records: ".($cnt - 1));
                $this->info('');
                /*
                $this->info("URLS uit EOKM sheet die gevonden zijn in API of MAIL (1413): ".$telfound.'  diff='.(1413 - $telfound) );
                $this->info("URLS STAAT IN LIJST EOKM MAAR NIET API of MAIL (367): ".$telnotfound.'  diff='.(367-$telnotfound));
                $this->info("URLS STAAT IN LIJST API MAAR NIET BIJ EOKM (453): ".$telnotapieokm.'  diff='.(453 - $telnotapieokm));
                $this->info("URLS STAAT IN MAIL LIJST MAAR NIET BIJ EOKM (594): ".$telnotmaileokm.'  diff='.(594 - $telnotmaileokm));
                $this->info("Check som: $telfound + $telnotfound + $telnotapieokm + $telnotmaileokm = ".($telfound + $telnotfound + $telnotapieokm + $telnotmaileokm));
                $this->info('');
                */

                $this->info("Aantal gevonden in SCART NTD sent: ".$telntd);
                $this->info("Aantal NIET gevonden in SCART NTD sent: ".$telnotntd);
                $this->info("Aantal met verschil in NTD datum: ".$telntddiff);
                $this->info("Aantal NIET in IPV list: ".$telnotinipv);
                $this->info('');

                $this->info("Skip double url lines: $telskip");


            } catch (\Exception $err) {

                $this->error("[cnt=$cnt]; Error on line: ".$err->getLine().", error: " . $err->getMessage());

            }




        } else {

            $this->warn("analyzeTUDELFT; no inputlines");

        }

        $this->info("");
    }

    function stripurl($url) {

        $ret = trim(str_replace([' ',"\t"],'',$url));
        return $ret;
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
            ['output', 'o', InputOption::VALUE_OPTIONAL, 'output', ''],
            ['test', 't', InputOption::VALUE_OPTIONAL, 'test mode', 0],
        ];
    }


}
