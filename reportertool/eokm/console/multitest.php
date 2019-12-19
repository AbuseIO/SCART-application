<?php

namespace reportertool\eokm\console;

use Illuminate\Console\Command;
use reportertool\eokm\classes\ertBrowser;
use reportertool\eokm\classes\ertBrowserDragon;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class multitest extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'reportertool:multitest';

    /**
     * @var string The console command description.
     */
    protected $description = 'multiTest';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $url = $this->option('url', 'http://example.com');

        // log console options
        $this->info('multiTest; url=' . $url );

        $urls = array(
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
            "https://www.welzijn-net.org:444/online-9.0a/",
        );

        $mh = curl_multi_init();

        foreach ($urls as $i => $url) {
            $conn[$i] = curl_init($url);
            curl_setopt($conn[$i], CURLOPT_RETURNTRANSFER, 1);
            curl_multi_add_handle($mh, $conn[$i]);
        }

        do {
            $status = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh);
            }
            $info = curl_multi_info_read($mh);
            if (false !== $info) {
                $this->info(print_r($info,true));
            }
        } while ($active && $status == CURLM_OK);

        foreach ($urls as $i => $url) {
            $res[$i] = curl_multi_getcontent($conn[$i]);
            //$this->info(print_r($res[$i],true));
            curl_close($conn[$i]);
        }

        $this->info(print_r(curl_multi_info_read($mh), true));


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
