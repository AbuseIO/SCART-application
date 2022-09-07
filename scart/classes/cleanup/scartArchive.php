<?php
namespace abuseio\scart\classes\cleanup;

/**
 * ARCHIVE
 *
 * deleted records
 * audittrail
 *
 */

use Illuminate\Support\Facades\DB;
use League\Flysystem\Exception;
use Schema;
use Log;
use abuseio\scart\classes\helpers\scartLog;

class scartArchive {

    private static $_tableprefix = 'abuseio_scart_';
    private static $_skiptables = [
        'abuseio_scart_scrape_cache',
    ];
    private static $_columnstringsize = 512;
    private static $_chunkinsert = 500;     // testing value based on big size tables
    private static $_chunkdelete = 2000;    // advise for mariadb innodb

    /**
     * Create table in ARCHIVE connection
     *
     * Simple create with big size:
     * - text -> longText
     * - integer -> singed
     * - boolean/char -> char(1)
     * - string -> varchar($_columnstringsize)
     *
     * ARCHIVE table with no indexes
     *
     * @param $archive_connection
     * @param $table
     *
     */

    private static function createTable($archive_connection,$table,$columns) {

        scartLog::logLine("D-Archive; create table=$table");

        /*
        $columns = Db::select(Db::raw("show columns from $table ") );
        scartLog::logLine("D-Columns=" . print_r($columns,true) );
        */

        $cols = [];
        foreach ($columns as $column) {
            $col = new \stdClass();
            $col->column = $column;
            $col->type =  Schema::getColumnType($table,$column);
            $cols[] = $col;
        }

        Schema::connection($archive_connection)->create($table, function($table) use ($cols)  {

            $table->engine = 'InnoDB';
            foreach ($cols as $col) {
                if ($col->column=='id') {
                    $table->increments($col->column)->unsigned();
                } elseif ($col->type == 'text') {
                    $table->longText($col->column)->nullable();
                } elseif ($col->type == 'string') {
                    $table->string($col->column,self::$_columnstringsize)->nullable();
                } elseif ($col->type == 'integer') {
                    $table->integer($col->column)->nullable();
                } elseif ($col->type == 'smallint') {
                    $table->smallInteger($col->column)->nullable();
                } elseif ($col->type == 'datetime') {
                    $table->timestamp($col->column)->nullable();
                } elseif ($col->type == 'date') {
                    $table->date($col->column)->nullable();
                } elseif ($col->type == 'boolean' || $col->type == 'char') {
                    $table->char($col->column,1)->nullable();
                } else {
                    scartLog::logLine("E-Archive; columns=$col->column, type=$col->type; unknown column type!?");
                }
            }

        });

    }

    private static function moveToArchive($archive_connection,$table,$columns,$records) {

        // Use CHUNK -> can be a lot of data

        $insrec = 0; $cntrec = $records->count();

        while ($insrec < $cntrec) {

            $start_time = microtime(true);

            $insrecs = $records->take(self::$_chunkinsert)->get();

            $inserts = [];
            foreach ($insrecs AS $record) {
                $insert = [];
                foreach ($columns AS $column) {
                    $insert[$column] = $record->$column;
                }
                $inserts[] = $insert;
            }

            scartLog::logLine("D-Archive; MOVE table=$table, cntrec=$cntrec, chunk=" . self::$_chunkinsert);
            Db::connection($archive_connection)->table($table)->insert($inserts);

            $inserts = $insrecs = null;
            $insrec += self::$_chunkinsert;

            $time_end = microtime(true);

            // dividing with 60 will give the execution time in minutes otherwise seconds
            $execution_time = ($time_end - $start_time);
            $recleft = round($cntrec - $insrec, 1);
            $time_left = round((($recleft / self::$_chunkdelete) * $execution_time), 1);
            scartLog::logLine("D-Archive; MOVED records table=$table; estimated time left=$time_left secs, records left=$recleft => ($recleft/" . self::$_chunkdelete . ") *  $execution_time) ");

        }

    }

    private static function deleteInTrunc($table,$records) {

        // no use of CHUNK -> stops within loop possible because of disappearing (delete) of records when looping

        $delrec = $time_end = $start_time = 0; $cntrec = $records->count();

        while ($delrec < $cntrec) {

            if ($delrec > 0) {

                $execution_time = round($time_end - $start_time, 2);
                $recleft = round($cntrec - $delrec, 1);
                $time_left = round((($recleft / self::$_chunkdelete) * $execution_time), 1);
                scartLog::logLine("D-Archive; DELETE table=$table, estimated time left=$time_left secs, records left=$recleft => ($recleft/" . self::$_chunkdelete . ") *  $execution_time) ");

            }

            $start_time = microtime(true);

            $delrecs = $records->take(self::$_chunkdelete)->get();
            $deletes = [];
            foreach ($delrecs AS $record) {
                $deletes[] = $record->id;
            }
            // delete
            Db::table($table)->whereIn('id',$deletes)->delete();

            $delrec += self::$_chunkdelete;
            $time_end = microtime(true);

        }

    }

    public static function archiveDeletedRecords($archive_connection,$archive_time,$only_delete=false,$createonly=false) {

        $job_records = [];

        $tables = DB::select("show tables");

        foreach ($tables AS $key => $tab) {

            $table = (array) $tab;
            $table = implode('',$table);

            $doaction = (!in_array($table,self::$_skiptables));

            if (strpos($table,self::$_tableprefix) !== false && $doaction) {

                // only DELETED_AT tables

                if (Schema::hasColumn($table,'deleted_at')) {

                    $status = '';
                    $status_timestamp = '['.date('Y-m-d H:i:s').'] ';

                    $before = date('Y-m-d 00:00:00',strtotime("-$archive_time days"));

                    Try {

                        scartLog::logLine("D-Archive; production table=$table, before=$before; get records... ");

                        $records = Db::table($table)
                            ->whereNotNull('deleted_at')
                            ->where('deleted_at','<',$before);
                        $cntdel = $records->count();

                        if (!$only_delete) {

                            $columns = Schema::getColumnListing($table);

                            // create table if not exists in archive
                            if (!Schema::connection($archive_connection)->hasTable($table)) {
                                self::createTable($archive_connection,$table,$columns);
                                $status = "table '$table' created in archive";
                            } else {
                                if ($createonly) {
                                    $status = "table '$table' exists in archive";
                                }
                            }

                        }

                        if (!$createonly && $cntdel > 0) {

                            if (!$only_delete) {
                                // move & remove
                                self::moveToArchive($archive_connection,$table,$columns,$records);
                                $status = "deleted table records moved to archive";

                                // reset query
                                $records = Db::table($table)
                                    ->whereNotNull('deleted_at')
                                    ->where('deleted_at','<',$before);

                            } else {
                                scartLog::logLine("D-Archive; only delete records") ;
                                $status = "only remove deleted records";
                            }

                            // remove
                            self::deleteInTrunc($table,$records);

                        }

                    } catch(\Exception $err) {

                        scartLog::logLine("E-scartArchive.archiveDeletedRecords error: line=".$err->getLine()." in ".$err->getFile().", message: <error>");

                    }


                    scartLog::logLine("D-Archive; production table=$table, before=$before, deleted count=$cntdel, status=$status ");

                    if ($status || $cntdel > 0) {
                        $job_record = [
                            'tablename' => $table,
                            'count' => $cntdel,
                            'status' => $status_timestamp . $status,
                        ];
                        $job_records[] = $job_record;
                    }

                }

            }

        }

        if (count($job_records) == 0) {
            $status_timestamp = '['.date('Y-m-d H:i:s').'] ';
            $job_records[] = [
                'tablename' => '(no table)',
                'count' => 0,
                'status' => $status_timestamp.'no deleted records to archive',
            ];
        }

        return $job_records;
    }

    public static function archiveAudittrail($archive_connection,$archive_time,$only_delete=false,$createonly=false) {

        $job_records = [];

        $before = date('Y-m-d 00:00:00',strtotime("-$archive_time days"));
        $table = SCART_AUDIT_TABLE;

        $records = Db::table($table)
            ->where('created_at','<',$before);
        $cntaud = $records->count();

        try {

            $status = '';
            $status_timestamp = '['.date('Y-m-d H:i:s').'] ';

            if (!$only_delete) {

                $columns = Schema::getColumnListing($table);

                // create table if not exists in archive
                if (!Schema::connection($archive_connection)->hasTable($table)) {
                    self::createTable($archive_connection,$table,$columns);
                    $status = "table '$table' created in archive";
                } else {
                    if ($createonly) {
                        $status = "table '$table' exists in archive";
                    }
                }

            }

            if (!$createonly && $cntaud > 0) {

                if (!$only_delete) {
                    // move
                    self::moveToArchive($archive_connection,$table,$columns,$records);
                    $status = "audittrail records records moved to archive";

                    // reset query
                    $records = Db::table($table)
                        ->where('created_at','<',$before);

                } else {
                    scartLog::logLine("D-Archive; only delete records") ;
                    $status = "only remove deleted records";
                }

                // remove
                self::deleteInTrunc($table,$records);
                //$records->delete();

            }

            scartLog::logLine("D-Archive; audittrail table=$table, before=$before, deleted count=$cntaud, status=$status") ;

            $job_records[] = [
                'tablename' => $table,
                'count' => $cntaud,
                'status' => $status_timestamp.$status,
            ];


        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-scartArchive.archiveAudittrail error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

        }

        if (count($job_records) == 0) {
            $status_timestamp = '['.date('Y-m-d H:i:s').'] ';
            $job_records[] = [
                'tablename' => '(no table)',
                'count' => 0,
                'status' => $status_timestamp.'no audittrail records to archive',
            ];
        }

        // @To-Do; system_event_logs records (debug)

        return $job_records;
    }


}
