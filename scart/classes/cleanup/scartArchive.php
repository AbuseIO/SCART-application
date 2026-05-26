<?php
namespace abuseio\scart\classes\cleanup;

/**
 * ARCHIVE
 *
 * deleted records
 * audittrail
 *
 */

use abuseio\scart\models\Input;
use abuseio\scart\models\Systemconfig;
use Illuminate\Support\Facades\DB;
use League\Flysystem\Exception;
use Schema;
use Log;
use Config;
use abuseio\scart\classes\helpers\scartLog;

class scartArchive {

    private static $_chunkinsert = 1000;     // testing value based on big size tables
    private static $_chunkdelete = 5000;     // proven value
    public static $_chunkmove = 50000;      // max number is depending on max_allowed_packet from database

    private static $_tableprefix = 'abuseio_scart_';

    /**
     * Archive tables
     *
     * Content is moved to archive and deleted from base database
     * Moving is done based on status
     * Related records are also moved
     *
     */
    private static $_archiveTables = [
        'abuseio_scart_input' => [
            'status_code' => [
                SCART_STATUS_CLOSE,SCART_STATUS_CLOSE_DOUBLE,SCART_STATUS_CLOSE_OFFLINE,SCART_STATUS_CLOSE_OFFLINE_MANUAL,
            ],
            'before_field' => 'received_at',
            'related' => [
                ['abuseio_scart_input_extrafield','id','input_id'],
                ['abuseio_scart_input_history','id','input_id'],
                ['abuseio_scart_input_parent','id','parent_id'],
                ['abuseio_scart_log','id','record_id',"abuseio_scart_log.record_type='abuseio_scart_input'"],
                ['abuseio_scart_grade_answer','id','record_id',"abuseio_scart_grade_answer.record_type='input'"],
            ],
            'delete' => [
                ['abuseio_scart_input_selected','id','input_id'],
                ['abuseio_scart_input_lock','id','input_id'],
            ],
        ],
        'abuseio_scart_ntd' => [
            'status_code' => [
                SCART_NTD_STATUS_CLOSE,
                SCART_NTD_STATUS_SENT_SUCCES,SCART_NTD_STATUS_SENT_API_SUCCES,
                SCART_NTD_STATUS_SENT_FAILED,SCART_NTD_STATUS_SENT_API_FAILED,
            ],
            'before_field' => 'status_time',
            'related' => [
                ['abuseio_scart_ntd_url','id','ntd_id'],
                ['abuseio_scart_log','id','record_id',"abuseio_scart_log.record_type='abuseio_scart_ntd'"],
            ]
        ],
    ];

    /**
     * Stam tables
     *
     * Copy to archive for reference/related info
     * Records in archive are never deleted, only added
     *
     */
    private static $_stamTables = [
        'abuseio_scart_abusecontact' => [],
        'abuseio_scart_ntd_template' => [],
        'abuseio_scart_addon' => [],
        'abuseio_scart_addon_type' => [],
        'abuseio_scart_alert_status' => [],
        'abuseio_scart_domainrule' => [],
        'abuseio_scart_grade_question' => [],
        'abuseio_scart_grade_question_option' => [],
        'abuseio_scart_grade_status' => [],
        'abuseio_scart_iccam_api_field' => [],
        'abuseio_scart_iccam_hotline' => [],
        'abuseio_scart_import_webform' => [],
        'abuseio_scart_import_webform_field' => [],
        'abuseio_scart_input_source' => [],
        'abuseio_scart_input_status' => [],
        'abuseio_scart_input_type' => [],
        'abuseio_scart_ntd_status' => [],
        'abuseio_scart_rule_type' => [],
        'abuseio_scart_whitelist' => [],
        'abuseio_scart_user' => [],
        'abuseio_scart_user_options' => [],
        'backend_users' => [
            ['backend_users_groups','id','user_id'],
        ],
        'backend_user_groups' => [],
        'backend_user_preferences' => [],
    ];

    /**
     * Work tables
     * - abuseio_scart_alert
     * - abuseio_scart_importexport_job
     * - abuseio_scart_input_lock
     * - abuseio_scart_input_selected
     * - abuseio_scart_input_lock
     *
     * Obsolute:
     * - abuseio_scart_input_extrafield_option
     *
     */

    private static $_testmode = false;

    public static function setTestmode($testmode) {
        self::$_testmode = $testmode;
    }

    private static $_columnstringsize = 512;
    private static $_connectioname = 'scartArchive';
    private static $_connectionsaved = '';
    private static $_archivevalid = false;
    public static function isActiveValid()
    {

        if (Systemconfig::get('abuseio.scart::scheduler.archive.active', false)) {
            if (!self::$_archivevalid) {
                $archive_connection = Systemconfig::get('abuseio.scart::scheduler.archive.database_connection', '');
                if (is_array($archive_connection) && !empty($archive_connection['username'])) {
                    Config::set('database.connections.' . self::$_connectioname, $archive_connection);
                    try {
                        Db::connection(self::$_connectioname)->select("show tables");
                        self::$_archivevalid = true;
                        self::$_connectionsaved = Db::getDefaultConnection();
                        scartLog::logLine("D-scartArchive.isActiveValid; archive database active");
                    } catch (\Exception $err) {
                        scartLog::logLine("E-scartArchive.isActiveValid; error testing database connection: " . $err->getMessage());
                    }
                } else {
                    scartLog::logLine("W-scartArchive.isActiveValid; archive=true, but no archive database connection details given (eg host and database name)");
                }
            }
        }
        return self::$_archivevalid;
    }

    public static function setArchiveDefault() {
        scartLog::logLine("D-scartArchive; switch default to archive database");
        self::$_connectionsaved = Db::getDefaultConnection();
        Db::setDefaultConnection(self::$_connectioname);
    }

    public static function resetArchiveDefault() {
        if (self::$_connectionsaved) {
            scartLog::logLine("D-scartArchive; switch default back to ".self::$_connectionsaved);
            Db::setDefaultConnection(self::$_connectionsaved);
            self::$_connectionsaved = '';
        }
    }

    public static function closeConnection() {
        Db::disconnect(self::$_connectioname);
        self::$_archivevalid = false;
    }

    /**
     * Create table in ARCHIVE connection
     *
     * Create basic, also with indexes (for finder/report)
     *
     * @param $table
     */

    private static function createTable($table) {

        scartLog::logLine("D-Archive; create table=$table");

        $cols = [];

        // use listTableColumns for detail info
        $schema = DB::getDoctrineSchemaManager();
        $columns = $schema->listTableColumns($table);
        foreach ($columns as $column) {
            $col = (object) [
                'column' => $column->getName(),
                'type' =>  str_replace('Doctrine\\DBAL\\Types\\','',get_class($column->getType())),
                'length' =>  $column->getLength(),
                'notNull' =>  $column->getNotNull(),
                'unsigned' =>  $column->getUnsigned(),
            ];
            $cols[] = $col;
        }
        //scartLog::logDump("D-Archive; cols=",$cols);

        $indexes = Db::select('SHOW INDEXES FROM '.$table);
        $inds = $colnames = [];
        $collast = '';
        foreach ($indexes as $index) {
            if ($index->Key_name != 'PRIMARY') {
                if ($collast=='') $collast = $index->Key_name;
                if ($index->Key_name == $collast) {
                    $colnames[$index->Seq_in_index] = $index->Column_name;
                } else {
                    $inds[] = (object) [
                        'Key_name' => $collast,
                        'Column_names' => $colnames,
                    ];
                    $collast = $index->Key_name;
                    $colnames = [$index->Seq_in_index => $index->Column_name];
                }
            }
        }
        if (!empty($colnames)) {
            $inds[] = (object) [
                'Key_name' => $collast,
                'Column_names' => $colnames,
            ];
        }
        //scartLog::logDump("D-Archive; inds=",$inds);

        Schema::connection(self::$_connectioname)->create($table, function($table) use ($cols,$inds)  {

            $table->engine = 'InnoDB';
            foreach ($cols as $col) {
                $valid = true;
                if ($col->column=='id') {
                    $table->increments($col->column)->unsigned();
                } elseif ($col->type == 'TextType') {
                    $table->longText($col->column)->nullable();
                } elseif ($col->type == 'StringType') {
                    $table->string($col->column,$col->length)->nullable();
                } elseif ($col->type == 'IntegerType') {
                    if ($col->unsigned) {
                        $table->integer($col->column)->unsigned()->nullable();
                    } else {
                        $table->integer($col->column)->nullable();
                    }
                } elseif ($col->type == 'SmallIntType') {
                    $table->smallInteger($col->column)->nullable();
                } elseif ($col->type == 'DateTimeType') {
                    $table->timestamp($col->column)->nullable();
                } elseif ($col->type == 'DateType') {
                    $table->date($col->column)->nullable();
                } elseif ($col->type == 'BooleanType') {
                    $table->char($col->column,1)->nullable();
                } elseif ($col->type == 'FloatType') {
                    $table->double($col->column,10,0)->nullable();
                } else {
                    $valid = false;
                }
                if ($valid) {
                    scartLog::logLine("D-Archive; _add column=$col->column, type=$col->type");
                } else {
                    scartLog::logLine("E-Archive; _column=$col->column, type=$col->type; unsupported column type!?");
                }
            }

            foreach ($inds as $ind) {
                $table->index($ind->Column_names,$ind->Key_name);
                scartLog::logLine("D-Archive; _add index={$ind->Key_name}, columens=".implode(',',$ind->Column_names));
            }

        });

    }

    public static function deleteArrayChunk($table,$deleteIds) {

        $result = false;

        try {

            $delrec = 0;
            $cntrec = count($deleteIds);

            $chunksize = Systemconfig::get('abuseio.scart::scheduler.archive.delete_chunk_size');
            if (empty($chunksize)) $chunksize = self::$_chunkdelete;

            scartLog::logLine("D-deleteArrayChunk; DELETE records table=$table, chunkdeletesize=$chunksize, total delete count=$cntrec");

            while ($delrec < $cntrec) {

                $start_time = microtime(true);

                $delarr = array_slice($deleteIds,$delrec,$chunksize);

                // directe delete, pass softdelete
                $delcnt = Db::table($table)->whereIn('id',$delarr)->delete();
                scartLog::logLine("D-deleteArrayChunk; DELETE records table=$table, deleted count=$delcnt");

                $delarr = null;

                $delrec += $chunksize;
                $time_end = microtime(true);

                $execution_time = round($time_end - $start_time, 2);
                $recleft = round($cntrec - $delrec, 1);
                $time_left = round((($recleft / $chunksize) * $execution_time), 1);
                scartLog::logLine("D-deleteArrayChunk; DELETE records table=$table, estimated time left=$time_left secs, records left=$recleft => ($recleft/$chunksize)");

            }
            $result = true;

        } catch(\Exception $err) {
            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-scartArchive.deleteArrayChunk error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );
        }
        return $result;
    }
    public static function deleteChunk($table,$records,$limit=0) {

        $result = false;

        try {

            $delrec = 0;
            $cntrec = $records->count();
            if ($limit && $cntrec > $limit) $cntrec = $limit;

            if ($cntrec > $delrec) {

                $deleteIds = $records->get()->pluck('id')->toArray();
                $result = self::deleteArrayChunk($table,$deleteIds);

            }  else {
                scartLog::logLine("D-deleteChunk; no records to DELETE");
                $result = true;
            }

        } catch(\Exception $err) {
            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-scartArchive.deleteChunk error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );
        }
        return $result;
    }



    private static function copyChunk($table,$records,$cntrec=0,$connection='') {

        $cntreal = 0;

        try {

            // default is upsert into archive connection
            if (empty($connection)) $connection = self::$_connectioname;

            $columns = Schema::getColumnListing($table);
            array_shift($columns);

            // Use CHUNK -> can be a lot of data

            // take only without skip until 0 records

            $insrec = 0;
            if ($cntrec==0) $cntrec = $records->count();

            if ($cntrec > $insrec) {

                while ($insrec < $cntrec) {

                    $start_time = microtime(true);

                    $insrecs = $records->skip($insrec)->take(self::$_chunkinsert)->get();
                    $inserts = $insrecs->map(function ($item,$key) {
                        return (array)$item;
                    })->toArray();

                    $cntarr = count($inserts);
                    $cntreal += $cntarr;

                    if ($cntarr == 0) {
                        // records->count() ($cntrec) can be confused and counting not the real amount of work
                        break;
                    }

                    // copy by update or insert based on ID (primaire key)
                    scartLog::logLine("D-copyChunk; COPY (UPSERT) records table=$table, count=$cntrec, skip=$insrec, insert/update real count=$cntarr" );
                    Db::connection($connection)
                        ->table($table)
                        ->upsert($inserts,['id'],$columns);

                    // direct insert also possible
                    // Db::connection(self::$_connectioname)->table($table)->insert($inserts);

                    $inserts = $insrecs = null;

                    $insrec += self::$_chunkinsert;

                    $time_end = microtime(true);

                    // dividing with 60 will give the execution time in minutes otherwise seconds
                    $execution_time = ($time_end - $start_time);
                    $recleft = round($cntrec - $insrec, 1);
                    $time_left = round((($recleft / self::$_chunkinsert) * $execution_time), 1);
                    scartLog::logLine("D-copyChunk; COPY records table=$table; estimated time left=$time_left secs, records left=$recleft => ($recleft/" . self::$_chunkinsert . ") *  $execution_time) ");

                }

            } else {
                scartLog::logLine("D-copyChunk; no records to COPY");
            }

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-scartArchive.copyChunk error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );
            $cntreal = -1;

        }

        return $cntreal;
    }

    private static function moveStamTable($table,$related) {

        $job_records = [];

        // create table if not exists in archive
        if (!Schema::connection(self::$_connectioname)->hasTable($table)) {
            self::createTable($table);
            scartLog::logLine("D-Archive; table '$table' created in archive");
            $job_records[] = [
                'tablename' => $table,
                'count' => 0,
                'status' => date('[Y-m-d H:i:s] ') . "table '$table' created in archive",
            ];
        }

        if (Db::table($table)->count() > Db::connection(self::$_connectioname)->table($table)->count()) {

            // Note: every record, also deleted_at
            $records = Db::table($table)->where('id','>',0);
            if (($cntrec = $records->count()) > 0) {

                // copy last status
                Db::connection(self::$_connectioname)->table($table)->truncate();

                if (self::copyChunk($table,$records,$cntrec) > 0) {

                    scartLog::logLine("D-Archive; table '$table'; truncated & inserted $cntrec records");
                    $job_records[] = [
                        'tablename' => $table,
                        'count' => $cntrec,
                        'status' => date('[Y-m-d H:i:s] ') . "table '$table'; truncated & inserted records",
                    ];

                    if (!empty($related)) {

                        foreach ($related as $item) {

                            // config
                            list($subtable,$tabfield,$subfield) = $item;
                            $extrasubwhere = (isset($item[3])) ? $item[3] : '';

                            // moveRelated -> only update or insert

                            if (!Schema::connection(self::$_connectioname)->hasTable($subtable)) {
                                self::createTable($subtable);
                                scartLog::logLine("D-Archive; subtable '$subtable' created in archive");
                                $job_records[] = [
                                    'tablename' => $subtable,
                                    'count' => 0,
                                    'status' => date('[Y-m-d H:i:s] ') . "table '$subtable' created in archive",
                                ];
                            }

                            $subrecords = Db::table($subtable)
                                ->join($table,$table.'.'.$tabfield,'=',$subtable.'.'.$subfield)
                                ->select($subtable.'.*')
                                ->distinct();
                            if ($extrasubwhere) $subrecords = $subrecords->whereRaw($extrasubwhere);

                            if (($cntreal = self::copyChunk($subtable,$subrecords,0)) > 0) {

                                scartLog::logLine("D-Archive; subtable '$subtable'; upsert $cntreal records");
                                $job_records[] = [
                                    'tablename' => $subtable,
                                    'count' => $cntreal,
                                    'status' => date('[Y-m-d H:i:s] ') . "subtable '$subtable'; inserted/updated records",
                                ];

                            }

                        }
                    }

                }


            }
        }

        return $job_records;
    }

    private static function getSubRecords($archiveTable,$status_codes,$beforefield,$before,$subtable,$tabfield,$subfield,$extrasubwhere) {

        scartLog::logLine("D-Archive; getSubRecords in $subtable JOIN $archiveTable...");
        $subrecords = Db::table($subtable)
            ->join($archiveTable,$archiveTable.'.'.$tabfield,'=',$subtable.'.'.$subfield)
            ->whereNull($archiveTable.'.deleted_at')
            ->whereIn($archiveTable.'.status_code',$status_codes)
            ->where($archiveTable.'.'.$beforefield,'<',$before)
            ->whereNull($subtable.'.deleted_at')
            ->select($subtable.'.*');
        if ($extrasubwhere) $subrecords = $subrecords->whereRaw($extrasubwhere);
        //scartLog::logLine("D-Archive; getSubRecords in $subtable JOIN $archiveTable; count=".$subrecords->count());
        return $subrecords;
    }

    private static function getSubRecordIds($archiveTable,$status_codes,$beforefield,$before,$subtable,$tabfield,$subfield,$extrasubwhere,$take) {

        $subrecords = self::getSubRecords($archiveTable,$status_codes,$beforefield,$before,$subtable,$tabfield,$subfield,$extrasubwhere);
        $subrecords = $subrecords->skip(0)->take($take)->get();
        $ids = ($subrecords) ? $subrecords->pluck('id')->toArray() : [];
        scartLog::logLine("D-Archive; getSubRecordids count=".count($ids));
        return $ids;
    }

    /**
     * Note:
     * Special code with chunking because of very large tables (> 1 miljoen records to work on)
     * So do not think you can optimize this code, the code setup is on experience
     *
     *
     *
     * @param $archiveTable
     * @param $archiveSetting
     * @param $before
     * @return array
     *
     */
    private static function moveArchiveTable($archiveTable,$archiveSetting,$before) {

        $job_records = [];

        try {

            // -1- First check if all tables are in place

            // create table if not exists in archive
            if (!Schema::connection(self::$_connectioname)->hasTable($archiveTable)) {
                self::createTable($archiveTable);
                scartLog::logLine("D-Archive; table '$archiveTable' created in archive");
                $job_records[] = [
                    'tablename' => $archiveTable,
                    'count' => 0,
                    'status' => date('[Y-m-d H:i:s] ') . "table '$archiveTable' created in archive",
                ];
            }

            foreach ($archiveSetting['related'] as $item) {
                list($subtable, $tabfield, $subfield) = $item;
                // create table if not exists in archive
                if (!Schema::connection(self::$_connectioname)->hasTable($subtable)) {
                    self::createTable($subtable);
                    scartLog::logLine("D-Archive; subtable '$subtable' created in archive");
                    $job_records[] = [
                        'tablename' => $subtable,
                        'count' => 0,
                        'status' => date('[Y-m-d H:i:s] ') . "table '$subtable' created in archive",
                    ];
                }
            }

            // -2- Secondly check if we have to move something

            $beforefield = $archiveSetting['before_field'];
            $status_codes = $archiveSetting['status_code'];

            $chunksize = Systemconfig::get('abuseio.scart::scheduler.archive.move_chunk_size');
            if (empty($chunksize)) $chunksize = self::$_chunkmove;


            // select main records
            $records = Db::table($archiveTable)
                ->whereNull('deleted_at')
                ->whereIn('status_code',$status_codes)
                ->where($beforefield,'<',$before)
                ->orderBy('id','ASC');
            if ($archiveTable == SCART_INPUT_TABLE) {
                // Special (hardcode...) subselect if SCART_INPUT_TABLE; delete only when not found in input_parent.input_id
                scartLog::logLine("D-Archive; add whereNotExists for $archiveTable");
                $records = $records->whereNotExists(function($query) {
                    $query->select(Db::raw(1))->from('abuseio_scart_input_parent')->whereRaw("abuseio_scart_input.id=abuseio_scart_input_parent.input_id");
                });
            }

            if (($cntaud = $records->count()) > 0) {

                scartLog::logLine("D-Archive; $cntaud records to copy in $archiveTable based on: $beforefield < '$before' ");

                /**
                 * Move records based on status and before_field time
                 * Move related records without futher constrains
                 *
                 * If error then NO futher move/delete
                 *
                 */

                // check if already copied - after restart or test mode
                $last = Db::table($archiveTable)
                    ->whereNull('deleted_at')
                    ->whereIn('status_code',$status_codes)
                    ->where($beforefield,'<',$before)
                    ->orderBy('id','DESC')
                    ->first();
                $lastId = ($last) ? $last->id : 0;
                $isCopied = Db::connection(self::$_connectioname)
                    ->table($archiveTable)
                    ->where('id',$lastId)
                    ->exists();
                if (!$isCopied) {
                    // reset query on ALL records including records which has to stay
                    $records = Db::table($archiveTable)
                        ->whereNull('deleted_at')
                        ->whereIn('status_code',$status_codes)
                        ->where($beforefield,'<',$before)
                        ->orderBy('id','ASC');
                    // copy
                    $isCopied = (self::copyChunk($archiveTable,$records,$cntaud) > 0);
                } else {
                    // skip
                    scartLog::logLine("D-Archive; content $archiveTable already copied");
                }

                // copy
                if ($isCopied) {

                    $status = "archive records moved to archive";

                    // MOVE related
                    foreach ($archiveSetting['related'] as $item) {

                        // config
                        list($subtable,$tabfield,$subfield) = $item;
                        $extrasubwhere = (isset($item[3])) ? $item[3] : '';

                        // select chunk from related table ids
                        $subrecordIds = self::getSubRecordIds($archiveTable,$status_codes,$beforefield,$before,$subtable,$tabfield,$subfield,$extrasubwhere,$chunksize);

                        $cntsubind = 0;
                        while (($cntsub = count($subrecordIds)) > 0) {

                            scartLog::logLine("D-Archive; move related records; offset=$cntsubind, count=$cntsub");

                            $subrecords = Db::table($subtable)->whereIn('id',$subrecordIds);

                            if (self::copyChunk($subtable,$subrecords,$cntsub) != -1) {
                                if (!self::deleteArrayChunk($subtable,$subrecordIds)) {
                                    throw new \Exception("error deleting related archive records from $subtable");
                                }
                            } else {
                                throw new \Exception("error coping related archive records from $subtable");
                            }

                            $cntsubind += $cntsub;

                            // next chunk
                            $subrecordIds = self::getSubRecordIds($archiveTable,$status_codes,$beforefield,$before,$subtable,$tabfield,$subfield,$extrasubwhere,$chunksize);
                        }

                        if ($cntsubind == 0) {
                            $substatus = "subtable '$subtable'; no records to move";
                        } else {
                            $substatus = "subtable '$subtable'; moved $cntsubind records";
                        }

                        scartLog::logLine("D-Archive; $substatus");
                        $job_records[] = [
                            'tablename' => $subtable,
                            'count' => $cntsubind,
                            'status' => date('[Y-m-d H:i:s] ') . $substatus,
                        ];

                    }

                    if (isset($archiveSetting['delete'])) {

                        foreach ($archiveSetting['delete'] as $item) {
                            // config
                            list($subtable,$tabfield,$subfield) = $item;
                            $extrasubwhere = (isset($item[3])) ? $item[3] : '';

                            $subrecords = Db::table($subtable)
                                ->join($archiveTable,$archiveTable.'.'.$tabfield,'=',$subtable.'.'.$subfield)
                                ->whereIn($archiveTable.'.status_code',$status_codes)
                                ->where($archiveTable.'.'.$beforefield,'<',$before)
                                ->select($subtable.'.*');
                            if ($extrasubwhere) $subrecords = $subrecords->whereRaw($extrasubwhere);

                            if (($cntsub = $subrecords->count()) > 0) {

                                if (self::deleteChunk($subtable,$subrecords)) {
                                    $substatus = "subtable '$subtable'; deleted $cntsub records";
                                } else {
                                    $substatus = "error deleting connected records";
                                    throw new \Exception("error deleting connected records from $subtable");
                                }

                            } else {
                                $substatus = "subtable '$subtable'; no connected records";
                            }

                            scartLog::logLine("D-Archive; $substatus");
                            $job_records[] = [
                                'tablename' => $subtable,
                                'count' => $cntsub,
                                'status' => date('[Y-m-d H:i:s] ') . $substatus,
                            ];

                        }
                    }

                    if (!self::$_testmode) {

                        // remove main records with fresh select

                        $records = Db::table($archiveTable)
                            ->whereNull('deleted_at')
                            ->whereIn('status_code',$status_codes)
                            ->where($beforefield,'<',$before);
                        if ($archiveTable == SCART_INPUT_TABLE) {
                            // Special (hardcode...) subselect if SCART_INPUT_TABLE; delete only when not found in input_parent.input_id
                            scartLog::logLine("D-Archive; add whereNotExists for $archiveTable");
                            $records = $records->whereNotExists(function($query) {
                                $query->select(Db::raw(1))->from('abuseio_scart_input_parent')->whereRaw("abuseio_scart_input.id=abuseio_scart_input_parent.input_id");
                            });
                        }
                        // remove
                        self::deleteChunk($archiveTable,$records);
                    } else {
                        scartLog::logLine("D-Archive; TEST mod - skip delete in $archiveTable");
                    }

                } else {
                    $status = "error moving archive records";
                }

            } else {
                $status = "no records to archive before '$before'";
            }

            scartLog::logLine("D-Archive; table=$archiveTable, before=$before, moved count=$cntaud, status=$status") ;

            $job_records[] = [
                'tablename' => $archiveTable,
                'count' => $cntaud,
                'status' => date('[Y-m-d H:i:s] ').$status,
            ];

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-scartArchive.moveArchiveTable error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );
            $job_records[] = [
                'tablename' => '(no table)',
                'count' => 0,
                'status' => date('[Y-m-d H:i:s] ').'error: '.$err->getMessage(),
            ];

        }

        return $job_records;
    }

    public static function archiveRecords($before) {

        $job_records = [];

        // STAM tables

        foreach (self::$_stamTables as $stamtable => $stamRelated) {
            // constrain; only addition to stam table, never delete (else functions can be broken)
            $job_records = array_merge($job_records,self::moveStamTable($stamtable,$stamRelated));
        }

        // second archive tables
        foreach (self::$_archiveTables as $archiveTable => $archiveSetting) {
            $job_records = array_merge($job_records,self::moveArchiveTable($archiveTable,$archiveSetting,$before));
        }

        if (count($job_records) == 0) {
            $job_records[] = [
                'tablename' => '(no table)',
                'count' => 0,
                'status' => date('[Y-m-d H:i:s] ').'no records to archive',
            ];
        }

        return $job_records;
    }


    public static function archiveAudittrail($before) {

        $job_records = [];

        $table = SCART_AUDIT_TABLE;

        try {

            $records = Db::table($table)->where('created_at','<',$before);
            $cntaud = $records->count();

            if ($cntaud > 0) {

                // create table if not exists in archive
                if (!Schema::connection(self::$_connectioname)->hasTable($table)) {
                    self::createTable($table);
                    $status = "table '$table' created in archive";
                } else {
                    $status = "table '$table' exists in archive";
                }

                // move
                if (self::copyChunk($table,$records,$cntaud) != -1) {

                    $status .= ", audittrail records moved to archive";

                    // reset query
                    $records = Db::table($table)->where('created_at','<',$before);

                    // remove
                    self::deleteChunk($table,$records);

                } else {

                    $status .= ", error moving audit records";

                }

            } else {
                $status = "no audittrail records before '$before'";
            }

            scartLog::logLine("D-Archive; audittrail table=$table, before=$before, moved count=$cntaud, status=$status") ;

            $job_records[] = [
                'tablename' => $table,
                'count' => $cntaud,
                'status' => date('[Y-m-d H:i:s] ').$status,
            ];


        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-scartArchive.archiveAudittrail error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );
            $job_records[] = [
                'tablename' => '(no table)',
                'count' => 0,
                'status' => date('[Y-m-d H:i:s] ').'error archiving audittrail records: '.$err->getMessage(),
            ];

        }

        return $job_records;
    }

    public static function getRecord($table,$id) {

        return Db::connection(self::$_connectioname)
            ->table($table)
            ->where('id',$id)
            ->first();
    }

    public static function copyFromArchive($table,$recordIds) {

        $archiveRecords = Db::connection(self::$_connectioname)->table($table)->whereIn('id',$recordIds);
        $cnt = $archiveRecords->count();

        if ($mainconnection = self::$_connectionsaved) {
            $cnt = self::copyChunk($table,$archiveRecords,0,$mainconnection);
        }  else {
            scartLog::logLine("E-No default connection found!?");
        }

        return $cnt;
    }


}
