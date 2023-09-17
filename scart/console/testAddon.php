<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\whois\scartRIPEdb;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\models\Addon;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class testAddon extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testAddon';

    /**
     * @var string The console command description.
     */
    protected $description = 'Test addon classes';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $type = $this->option('type', '');
        $codename = $this->option('codename', '');
        $url = $this->option('url', '');
        $filter = $this->option('filter', '');

        // log console options
        $this->info("testAddon; type=$type, codename=$codename, url=$url, filter=$filter" );

        $cnt = 0;
        if ($type) {

            $addons = Addon::where('type',$type);

            if ($addons->count() > 0) {

                if ($codename) {

                    $addon = $addons->where('codename',$codename)->first();

                    if ($addon) {

                        if ($addon->enabled) {

                            $arr = parse_url($url);
                            if ($arr!==false) {
                                $host = (isset($arr['host']) ? $arr['host'] : '');
                                if ($host) {

                                    // set used parameters
                                    $record = new \stdClass();
                                    $record->url = $url;

                                    switch ($type) {

                                        case SCART_ADDON_TYPE_LINK_CHECKER:

                                            $result = Addon::run($addon,$record);
                                            $this->info("testAddon; active addon; run($codename), url=$record->url; result=" . print_r($result,true) );
                                            break;

                                        case SCART_ADDON_TYPE_PROXY_SERVICE_API:

                                            $record->url_ip = scartWhois::getIP($host);
                                            $record->filenumber = 'N' . sprintf('%010d', 1);

                                            $result = Addon::run($addon,$record,true);
                                            $cnt += 1;

                                            $this->info("testAddon; addon:run($codename); ip=$record->url_ip; result=" . print_r($result,true) );

                                            $info = scartRIPEdb::getIPinfo($result);
                                            $this->info("testAddon; addon:run($codename); proxyservice real IP hoster info=" . print_r($info,true) );
                                            break;

                                        case SCART_ADDON_TYPE_NTDAPI:

                                            // TEST NTD API IPvolume

                                            $record->url_ip = scartWhois::getIP($host);
                                            $record->url = $url;

                                            // direct IPV TEST data
                                            //$record->url_ip = "89.248.172.154";
                                            //$record->url = "https://imx.to/i/21eyp4\nhttps://imx.to/u/i/2019/11/14/26bflo.gif\nhttps://imx.to/i/1yhmqd";


                                            $result = Addon::run($addon,$record);
                                            $cnt += 1;

                                            $this->info("testAddon; addon:run($codename); result=" . print_r($result,true) );

                                            break;

                                    }

                                } else {
                                    $this->info("testAddon; cannot find host with url='$url' " );
                                }

                            } else {
                                $this->info("testAddon; cannot parse url='$url'" );
                            }

                        } else {
                            $this->info("testAddon; addons with type='$type' and codename='$codename' is NOT enabled" );
                        }

                    } else {
                        $this->info("testAddon; cannot find addons with type='$type' and codename='$codename' " );
                    }

                } else {

                    $this->info("testAddon; list addons type$type ");
                    foreach ($addons->get() AS $addon) {
                        $this->info("testAddon; codename=$addon->codename, enabled=$addon->enabled, filter=$addon->filter ");
                        $cnt += 1;
                    }

                }

            } else {
                $this->info("testAddon; cannot find addons with type=$type " );
            }

        } else {
            $this->info("testAddon; NO TYPE!?" );
        }

        $this->info(scartLog::returnLoglines());

        // log console work done
        $this->info("testAddon; $cnt processed" );

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
            ['type', 't', InputOption::VALUE_OPTIONAL, 'Type addon', false],
            ['codename', 'c', InputOption::VALUE_OPTIONAL, 'Codename', false],
            ['url', 'u', InputOption::VALUE_OPTIONAL, 'URL ', false],
            ['filter', 'f', InputOption::VALUE_OPTIONAL, 'Filter (parameter)', false],
        ];
    }


}
