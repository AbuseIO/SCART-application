<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;

use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\classes\whois\scartWhoisphpWhois;
use Symfony\Component\Console\Input\InputOption;

class GetwhoisICCAM extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:GetwhoisICCAM';

    /**
     * @var string The console command description.
     */
    protected $description = 'GetwhoisICCAM';

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

        $outputfile = $this->option('outputfile');
        if (empty($outputfile)) $outputfile = basename($inputfile,'.csv') . '-resolved.csv';

        $inputindexfile = basename($inputfile,'.csv') . '-lastindex.csv';

        if (file_exists($inputindexfile)) {
            $startindex = file_get_contents($inputindexfile);
            $this->info("Getwhois; skip $startindex lines (already done) ");
        } else {
            $startindex = 0;
            $line = 'url/ip;ICCAM country;ICCAM provider;SAM IP;SAM PROVIDER;SAM COUNTRY;SAM E-MAIL';
            file_put_contents($outputfile, $line . "\n" );
            $this->info("Getwhois; $outputfile created ");
        }

        $this->info("Getwhois; analyze inputfile '$inputfile'... ");

        $inputlines = explode("\n", file_get_contents($inputfile));

        // records;URL domain/IP;hosting coutry;hosting provider

        $cnt = 1;
        foreach ($inputlines AS $fileindex => $inputline) {

            if ($fileindex > $startindex){

                $line = trim($inputline);
                if (!empty($line)) {

                    $linearr = explode(';',$line);
                    if ($linearr && (count($linearr) > 3) && !empty($linearr[1])) {

                        $url = $this->getLine($linearr,1);

                        try {

                            // check if domain or IP

                            if (!filter_var($url,FILTER_VALIDATE_IP)) {
                                $ips = $this->parse($url);
                            } else {
                                $ips = [$url];
                            }

                            if (!empty($ips)) {

                                foreach ($ips as $ip) {

                                    $result = scartWhoisphpWhois::lookupIp($ip);
                                    if ($result) {

                                        // Check $result['host_owner']=CLOUDFLARENET

                                        $orgcountry = $this->getLine($linearr,2);
                                        $orgprovider  = $this->getLine($linearr,3);

                                        $line = "$url;$orgcountry;$orgprovider;$ip;".$result['host_country'].';'.$result['host_owner'].';'.$result['host_abusecontact'];
                                        $this->info("Getwhois; [fileindex=$fileindex] write line: $line");
                                        file_put_contents($outputfile, $line . "\n", FILE_APPEND );
                                        file_put_contents($inputindexfile, $fileindex);

                                        $cnt += 1;

                                    } else {
                                        $this->warn("Getwhois; error lookUpIP: ".$result['status_text']);
                                    }

                                }


                            }

                        } catch (\Exception $err) {
                            $this->info("Getwhois; input='$url'; error: " . $err->getMessage() . "; SKIP");
                        }

                    }

                }

            }



        }

        $this->info("Getwhois; $cnt lines in file '$outputfile' ");


    }

    function getLine($line,$index) {

       return ((isset($line[$index])?trim($line[$index]): ''));
    }

    function parse($url) {

        $ip = [];
        try {
            $ips = dns_get_record($url,DNS_A);
            if ($ips) {
                //$this->info("Getwhois; ips=".print_r($ips,true));
                foreach ($ips as $iptype) {
                    $ip[] = $iptype['ip'];
                }
            }
        } catch (\Exception $err) {
            $this->info("Getwhois; url='$url'; error: " . $err->getMessage() . "; try gethostbyname");
            $ip = gethostbyname($url);
            $this->info("Getwhois; gethostbyname(); ip=$ip");
            $ip = ($ip!=$url) ? [$ip] : [];
        }
        return $ip;


        $ip = gethostbyname($url);
        $ip = ($ip!=$url) ? $ip : '';
        return $ip;


        $prefixes = ['https://','https://www.','http://','http://www.'];
        foreach ($prefixes as $prefix) {
            //$this->info("Getwhois; parse_url('{$prefix}{$url}') ");
            $urlparse = parse_url($prefix.$url);
            if ($urlparse !== false) {
                $host = (isset($urlparse['host']) ? $urlparse['host'] : '');
                if ($host) {
                    $ip = gethostbyname($host);
                    $ip = ($ip!=$host) ? $ip : '';
                    if ($ip) break;
                }
            }
        }
        if ($ip) $this->info("Getwhois; found ip=$ip based on '{$prefix}{$url}' ");
        return $ip;


        $urlparse = parse_url($url);
        if ($urlparse !== false) {
            $host = (isset($urlparse['host']) ? $urlparse['host'] : '');
            if ($host) {
                $ip = gethostbyname($host);



            } else {




                $this->warn("Getwhois; cannot get host info from URL '$url'");
            }
        } else {
            $this->warn("Getwhois; cannot parse URL '$url'");
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
            ['inputfile', 'i', InputOption::VALUE_OPTIONAL, 'Inputfile', ''],
            ['outputfile', 'o', InputOption::VALUE_OPTIONAL, 'Outputfile', ''],
        ];
    }


}
