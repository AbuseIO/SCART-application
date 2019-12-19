<?php

namespace reportertool\eokm\console;

use Illuminate\Console\Command;
use reportertool\eokm\classes\ertWhois;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class whoisTest extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'reportertool:whoisTest';

    /**
     * @var string The console command description.
     */
    protected $description = 'Testen whois functie';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        // option paramater
        $domainip = $this->argument('domainip', '');

        if ($domainip) {

            // log console options
            $this->info('whoisTest; domain/ip=' . $domainip );

            $whois = ertWhois::lookupLink($domainip,true);

            if ($whois && $whois['status_success']) {

                // log console work done
                $this->info("whoisTest; " . print_r($whois, true) );


            } else {

                $this->error('whoisTest; error: ' . $whois['status_text'] );

            }

        } else {

            $this->error('whoisTest; input domain/ip is needed' );

        }


    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments() {
        return [
            ['domainip', InputArgument::REQUIRED, 'Domain or IP to check for WhoIs'],
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions() {
        return [
        ];
    }


}
