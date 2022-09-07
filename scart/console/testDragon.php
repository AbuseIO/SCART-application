<?php

namespace abuseio\scart\console;

use Illuminate\Console\Command;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\browse\scartBrowserDragon;
use abuseio\scart\classes\helpers\scartLog;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class testDragon extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testDragon';

    /**
     * @var string The console command description.
     */
    protected $description = 'testDragon';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $url = $this->option('url', 'http://example.com');

        // log console options
        $this->info('testDragon; url=' . $url );

        scartBrowser::setBrowser('BrowserDragon');

        $response = scartBrowser::getRawContent($url);

        if ($response) {

            $file = 'dragon-response-' . date('YmdHis') . '.txt';
            file_put_contents($file,print_r($response,true));
            $this->info("Datadragon Response in $file");
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
            ['url', 'u', InputOption::VALUE_OPTIONAL, 'url', false]
        ];
    }


}
