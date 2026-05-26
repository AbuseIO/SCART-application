<?php
namespace abuseio\scart\console;

/**
 *
 */

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Input_parent;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Db;
use Schema;
use abuseio\scart\classes\cleanup\scartArchive;
use abuseio\scart\models\Input;

class correctArchived extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'abuseio:correctArchived';

    /**
     * @var string The console command description.
     */
    protected $description = 'correctArchived';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle() {

        scartLog::setEcho(true);

        /**
         * 1. select input_parent records with input_id not found in current db
         * 2. copy back from archive
         * 3. check (delete) not valid input_parent records
         *
         */

        $table = 'abuseio_scart_input';

        // SELECT DISTINCT(abuseio_scart_input_parent.input_id) FROM abuseio_scart_input_parent
        // WHERE 0 = (SELECT COUNT(*) FROM abuseio_scart_input WHERE abuseio_scart_input_parent.input_id=abuseio_scart_input.id AND abuseio_scart_input.url_type<>'mainurl')

        //trace_sql();
        $inputIds = Input_parent::whereNotExists(function($query) use ($table) {
            $query->select(Db::raw(1))->from($table)->whereRaw("abuseio_scart_input.id=abuseio_scart_input_parent.input_id");
        });
        $cnt = $inputIds->count();

        scartLog::logLine("D-correctArchived; records to recover from archive count=$cnt");

        if ($cnt > 0) {

            $chunk = scartArchive::$_chunkmove;
            $total = 0;

            scartArchive::isActiveValid();

            scartLog::logLine("D-correctArchived; take($chunk)");

            $recordIds = $inputIds->distinct('input_id')->skip(0)->take($chunk)->get()->pluck('input_id')->toArray();
            while (($cntsub = count($recordIds)) > 0) {

                if ($cnt = scartArchive::copyFromArchive($table,$recordIds)) {

                    scartLog::logLine("D-correctArchived; copied $cnt records from archive");

                    $total += $cnt;

                    // next
                    scartLog::logLine("D-correctArchived; take($chunk)");
                    $recordIds = Input_parent::whereNotExists(function($query) use ($table) {
                        $query->select(Db::raw(1))->from($table)->whereRaw("abuseio_scart_input.id=abuseio_scart_input_parent.input_id");
                    })->distinct('input_id')->skip(0)->take($chunk)->get()->pluck('input_id')->toArray();

                } else {

                    scartLog::logLine("D-correctArchived; no (more) records copied");
                    $recordIds = [];

                }

            }

            scartLog::logLine("D-correctArchived; total $total records copied");

            // check records also not in archive

            $recordIds = Input_parent::whereNotExists(function($query) use ($table) {
                $query->select(Db::raw(1))->from($table)->whereRaw("abuseio_scart_input.id=abuseio_scart_input_parent.input_id");
            })->distinct('input_id')->skip(0)->take($chunk)->get()->pluck('input_id')->toArray();

            if (($cntsub = count($recordIds)) > 0) {

                scartLog::logLine("D-correctArchived; $cntsub records left - delete from input_parent!?");

                //Input_parent::whereIn('id',$recordIds)->delete();


            }

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
        ];
    }

}
