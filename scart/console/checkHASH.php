<?php
namespace abuseio\scart\console;

use Config;
use abuseio\scart\classes\online\scartHASHcheck;
use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class checkHASH extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:checkHASH';

    /**
     * @var string The console command description.
     */
    protected $description = 'Check HASH';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        scartLog::setEcho(true);

        // option paramater
        $image = $this->option('image', '');
        $hash = $this->option('hash', '');

        $test = $this->option('test', '');
        $test = ($test!='');

        if ($image) {

            $data = file_get_contents($image);

            //$imagedata['data'] = '(hidden)';
            //scartLog::logLine("D-images=" . print_r($imagedata, true) );


            //$imagedata = scartBrowser::getImageCache($image, $imagehash);
            //$arrdata = explode(',',$imagedata);
            //$base64 = $arrdata[1];
            //$data = base64_decode($base64);

        } else {

            $data = '';

        }

        if ($data) {

            $indb = ertHASHcheck::inDatabase($data,'',$test);
            $this->info("checkHASH; test=$test; ertHASHcheck::inDatabase: " . (($indb)?'FOUND':'NOT FOUND') );

        } else {
            // log console options
            $this->info('checkHASH; Can not get data from image: $image' );

        }

        //$this->info(scartLog::returnLoglines());

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
            ['image', 'i', InputOption::VALUE_OPTIONAL, 'image', ''],
            ['hash', 'd', InputOption::VALUE_OPTIONAL, 'hash from image', ''],
            ['test', 't', InputOption::VALUE_OPTIONAL, 'test mode', 0],
        ];
    }


}
