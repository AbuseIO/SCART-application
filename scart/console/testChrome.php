<?php

namespace abuseio\scart\console;

use abuseio\scart\models\Scrape_cache;
use Illuminate\Console\Command;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\browse\scartBrowserDragon;
use abuseio\scart\classes\helpers\scartLog;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class testChrome extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testChrome';

    /**
     * @var string The console command description.
     */
    protected $description = 'testChrome';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $url = $this->option('url');
        $debug = $this->option('debug');
        $debug = (!empty($debug));
        $cache = $this->option('cache');
        $cache = (!empty($cache));

        scartLog::setEcho(true);

        // log console options
        $this->info('testChrome; url=' . $url );

        scartBrowser::setBrowser('BrowserChrome');
        scartBrowser::startBrowser($debug);

        $images = scartBrowser::getImages($url);
        if (!empty($images)) {

            array_walk($images, function(&$item,$key) use ($cache) {

                if ($cache) {
                    $this->info('testChrome; add/replace cache hash='. $item['hash']);
                    $data64 = base64_encode($item['data']);
                    Scrape_cache::delCache($item['hash']);
                    $cache = "data:" . $item['mimetype'] . ";base64," . $data64;
                    Scrape_cache::addCache($item['hash'], $cache);
                }

                $item['data'] = '';
            });
            $this->info("testChrome; images=".print_r($images,true) );
        }

        scartBrowser::stopBrowser();
        $this->info("testChrome; end" );

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
            ['url', 'u', InputOption::VALUE_OPTIONAL, 'url', false],
            ['debug', 'd', InputOption::VALUE_OPTIONAL, 'debug', false],
            ['cache', 'c', InputOption::VALUE_OPTIONAL, 'cache', false],
        ];
    }


}
