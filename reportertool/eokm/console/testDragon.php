<?php

namespace reportertool\eokm\console;

use Illuminate\Console\Command;
use reportertool\eokm\classes\ertBrowser;
use reportertool\eokm\classes\ertBrowserDragon;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class testDragon extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'reportertool:testDragon';

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

        ertBrowser::setBrowser('BrowserDragon');

        $images = ertBrowser::getImages($url);

        foreach ($images AS $image) {
            if (isset($image['data'])) $image['data'] = '(deleted)';
            $this->info("testDragon; images: \n" . print_r($image, true) );
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
