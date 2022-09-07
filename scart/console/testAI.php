<?php

namespace abuseio\scart\console;

use abuseio\scart\classes\browse\scartCURLcalls;
use Illuminate\Console\Command;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Input;
use abuseio\scart\models\Scrape_cache;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\classes\helpers\scartLog;

class testAI extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:testAI';

    /**
     * @var string The console command description.
     */
    protected $description = 'Test PWC AI';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option parameters
        $action = $this->option('action', '');
        $filenumber  = $this->option('filenumber', '');

        // log console options
        $this->info("testAI; action=$action, filenumber=$filenumber " );

        scartLog::setEcho(true);

        $addon = Addon::where('type',SCART_ADDON_TYPE_AI_IMAGE_ANALYZER)->where('codename','pwcAIanalyzer')->first();

        if ($action && $filenumber && $addon) {

            $result = '';

            switch ($action) {

                case 'push':

                    $input = Input::where('filenumber',$filenumber)->first();

                    if ($input) {

                        $image = Scrape_cache::where('code',$input->url_hash)->first();

                        if ($image) {

                            $this->info("testAI; image cached=" . substr($image->cached,0,100));

                            $data = explode(',', $image->cached);
                            $mime = str_replace('data:','',$data[0]);
                            $mime = str_replace(';base64','',$mime);

                            $base64 = $data[1];
                            $this->info("testAI; mime=$mime, base64=" . substr($base64,0,100) .', length='. strlen($base64) );

                            //the following is working - save into real image
                            //$imagedata = base64_decode( $data[1]);
                            //$tmpfile = temp_path().'/'. $filenumber .'.png';
                            //file_put_contents($tmpfile ,$imagedata);
                            //$this->info('Test image tmpfile='.$tmpfile);
                            //$utf8 = mb_convert_encoding($base64,'UTF-8','auto');
                            //$this->info("testAI; mime=$mime, utf8=" . substr($utf8,0,100) .', length='. strlen($utf8) );
                            //$imagedata = "ldkfjkjhokhgkjghjfhgofgjgfkhjgfgj67345687354tuoiuy7psdehfkhgfkegflegbflsdbflsdkhjbf";
                            //$base64 = base64_decode($imagedata);
                            //$utf8 = mb_convert_encoding($base64,'UTF-8',"auto");
                            //$this->info("testAI; base64 encoding=" . mb_detect_encoding($base64) );
                            //$this->info("testAI; utf8 encoding=" . mb_detect_encoding($utf8) );
                            //$this->info("testAI; mime=$mime, utf8=" . substr($utf8,0,100));

                            $record = [
                                'action' => 'push',
                                'post' =>
                                [
                                    'SCART_ID' => $filenumber,
                                    'image' => $base64,
                                ],
                            ];

                            //scartCURLcalls::setDebug(true);

                            $result = Addon::run($addon,$record);

                        } else {

                            $this->warn("testAI; cannot find imagedata (cache) of hash=$input->url_hash");

                        }

                    } else {
                        $this->warn('testAI; Cannot find filenumber ');
                    }

                    break;

                case 'poll':
                    $record = [
                        'action' => 'poll',
                        'post' => $filenumber,
                    ];
                    $result = Addon::run($addon,$record);
                    break;

            }

            if ($result) {
                $this->info('testAI; result=' . print_r($result,true) );
            } else {
                $this->error('testAI; error=' . Addon::getLastError($addon) );
            }

        } else {
            $this->warn("testAI; NO ACTION and/or FILENUMBER and/oR ADDON (pwcAIservice) activated!?" );
        }

        //$this->info(scartLog::returnLoglines());

        // log console work done
        $this->info("testAI; processed" );

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
            ['action', 'a', InputOption::VALUE_OPTIONAL, 'action', false],
            ['filenumber', 'f', InputOption::VALUE_OPTIONAL, 'filenumber ', false],
        ];
    }


}
