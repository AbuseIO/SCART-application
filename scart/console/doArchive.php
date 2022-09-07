<?php

namespace abuseio\scart\console;

use Config;
use abuseio\scart\classes\online\scartAnalyzeInput;
use abuseio\scart\classes\cleanup\scartArchive;
use abuseio\scart\classes\helpers\scartLog;
use Illuminate\Console\Command;
use abuseio\scart\classes\scheduler\scartScheduler;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use abuseio\scart\models\Systemconfig;

class doArchive extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:doArchive';

    /**
     * @var string The console command description.
     */
    protected $description = 'Archive';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        $createonly = $this->option('createonly','n');
        $createonly = ($createonly != 'n');
        $deleteonly = $this->option('deleteonly','n');
        $deleteonly = ($deleteonly != 'n');

        // log console options
        $this->info("doArchive start; deleteonly=$deleteonly, createonly=$createonly");

        $archive_connection =  Systemconfig::get('abuseio.scart::scheduler.archive.database_connection','');
        $archive_time =  Systemconfig::get('abuseio.scart::scheduler.archive.archive_time',7);

        $memory_min =  scartScheduler::setMinMemory('2G');

        // archive deleted records
        $job_records = scartArchive::archiveDeletedRecords($archive_connection,$archive_time,$deleteonly,$createonly);

        // archive audittrail
        $job_records = array_merge($job_records, scartArchive::archiveAudittrail($archive_connection,$archive_time,$deleteonly,$createonly) );

        // log console work done
        $this->info("doArchive; job_records:" . print_r($job_records,true)  );

        $this->info(scartLog::returnLoglines());

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
            ['createonly', 'c', InputOption::VALUE_OPTIONAL, 'createonly', 'n'],
            ['deleteonly', 'd', InputOption::VALUE_OPTIONAL, 'deleteonly', 'n'],
        ];
    }


}
