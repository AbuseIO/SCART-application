<?php namespace abuseio\scart\classes\cleanup;

use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\classes\scheduler\scartSchedulerCreateReports;
use abuseio\scart\Models\ImportWebform;
use abuseio\scart\models\Input_history;
use abuseio\scart\models\Log;
use abuseio\scart\models\Ntd_url;
use abuseio\scart\models\Whois_cache;
use Db;
use Config;
use Schema;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_parent;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\cleanup\scartArchive;

class scartCleanup {

    private static $_tableprefix = 'abuseio_scart_';
    private static $_skiptables = [
    //    'abuseio_scart_scrape_cache',
        'abuseio_scart_importexport_job',       // 2023-09-21; SPECIAL EXCLUDE FOR NEW ICCAM API ERRORS SOLVING
    ];

    public static function cleanupOutOfDate($cleanup_grade_timeout,$logname) {

        $job_records = [];

        $timeout = date('Y-m-d H:i:s', strtotime("-$cleanup_grade_timeout hours"));

        // check ICCAM V3 -> no cleanup, keep assessment from ICCAM
        $iccamversion = (scartICCAMinterface::isActive()) ? scartICCAMinterface::getVersion() : '';

            // no limit -> each night one time

        // last time updated good indication of last-time worked at
        $inputs = Input::where('status_code',SCART_STATUS_GRADE)
            ->where('url_type',SCART_URL_TYPE_MAINURL)
            ->where('updated_at', '<', $timeout)
            ->get();
        scartLog::logLine("D-$logname.cleanupOutOfDate; classify inputs older then '$timeout' count=" . count($inputs) );

        foreach ($inputs AS $input) {

            if (!ImportWebform::isGeneratedUrl($input->url)) {

                try {

                    // in Input::beforeDelete we handle the delete of the foreign data
                    $status = "Longer then $cleanup_grade_timeout hours waiting for classify" ;

                    if (scartGrade::isLocked(0,[$input->id])) {

                        $lock = scartGrade::getLockFullnames(0, [$input->id]);
                        $status .= "; SKIP - input locked by=$lock";
                        $notcnt = 0;

                    } elseif ($iccamversion == 'v3' && $input->reference != '') {

                        $status .= "; SKIP - imported from ICCAM (API v3) with reference=$input->reference ";
                        $notcnt = 0;

                    } else {

                        // count connected items
                        $notcnt = Input_parent::where('parent_id', $input->id)->count();

                        // Note: within AnalyzeInput -> mainurl scrape -> count connected items

                        $status .= "; reset input with $notcnt imageurls; set for new scrape";
                        $input->logText($status);

                        // log old/new for history
                        $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_SCHEDULER_SCRAPE,"Rescrape because older then '$timeout' and not locked");

                        // mark for scrape
                        $input->status_code = SCART_STATUS_SCHEDULER_SCRAPE;
                        $input->save();

                    }

                    //scartLog::logLine("D-schedulerCleanup; $status");
                    $job_records[] = [
                        'filenumber' => $input->filenumber,
                        'notcnt' => $notcnt,
                        'url' => $input->url,
                        'status' => $status,
                    ];

                } catch(\Exception $err) {

                    // NB: \Expection is important, else not in this catch when error in Mail
                    scartLog::logLine("E-$logname.cleanupOutOfDate error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

                }

            }

        }

        return $job_records;
    }

    public static function cleanupScrapeCache($logname) {

        // Remove scrape-cache from input/notificatie status_code <> CLASSIFY & SCRAPING

        Try {

            // find all cache records not needed anymore
            $scraped = Scrape_cache::join(SCART_INPUT_TABLE,SCART_INPUT_TABLE.'.url_hash','=',SCART_SCRAPE_CACHE_TABLE.'.code')
                ->whereNotIn(SCART_INPUT_TABLE.'.status_code',[SCART_STATUS_GRADE,SCART_STATUS_WORKING,SCART_STATUS_SCHEDULER_SCRAPE])
                ->withTrashed()
                ->select(SCART_SCRAPE_CACHE_TABLE.'.code',
                    SCART_INPUT_TABLE.'.status_code AS input_status',
                    SCART_INPUT_TABLE.'.id AS input_id')
                ->get();
            $scrapecleaned = count($scraped);
            scartLog::logLine("D-$logname.cleanupScrapeCache; found scrape_cache to clear: count=$scrapecleaned ");

            if ($scrapecleaned > 0) {

                $deletes = $scraped->pluck('id')->toArray();

                $offset = 0; $take = 1000;
                $deleteIds = array_slice($deletes,$offset,$take);
                while (!empty($deleteIds)) {

                    // direct delete without softdelete
                    Db::table(SCART_SCRAPE_CACHE_TABLE)->whereIn('id',$deleteIds)->delete();

                    $offset += $take;
                    $deleteIds = array_slice($deletes,$offset,$take);

                }

            }

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-$logname.cleanupScrapeCache error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

        }

        return $scrapecleaned;
    }

    public static function cleanupWhoisCache($logname,$nowstamp='') {

        $count = 0;
        Try {

            if ($nowstamp == '') $nowstamp = date('Y-m-d H:i:s');
            $count = Whois_cache::where('max_age','>',$nowstamp)->count();
            if ($count > 0) {
                scartLog::logLine("D-$logname.cleanupWhoisCache; delete whoiscache $count records ");
                Whois_cache::where('max_age','>',$nowstamp)->forceDelete();
            }

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-$logname.cleanupWhoisCache error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

        }

        return $count;
    }

    public static function cleanupOrphan($logname) {

        $cnt = 0;

        Try {

            // @TODO; optimize this code

            // 1: Orphan = (image/video url input without a (active) connection in input_parent)

            $orphans = Db::select("SELECT id FROM ".SCART_INPUT_TABLE."
                 WHERE ".SCART_INPUT_TABLE.".url_type <> 'mainurl'
                 AND ".SCART_INPUT_TABLE.".deleted_at IS NULL
                 AND ".SCART_INPUT_TABLE.".status_code = '".SCART_STATUS_GRADE."'
                 AND NOT EXISTS (SELECT 1 FROM ".SCART_INPUT_PARENT_TABLE." WHERE ".SCART_INPUT_PARENT_TABLE.".deleted_at IS NULL
         		 	 AND ".SCART_INPUT_PARENT_TABLE.".input_id=".SCART_INPUT_TABLE.".id)") ;
            $orphanscnt = count($orphans);

            if ($orphanscnt > 0) {

                scartLog::logLine("D-$logname.cleanupOrphan; found inputs with status classify and without mainurl-input (orphans); count=$orphanscnt");

                $startTime = microtime(true);

                foreach ($orphans AS $orphan) {
                    $rec = Input::find($orphan->id);
                    if ($rec) {
                        //scartLog::logLine("D-$logname remove; id=$rec->id, url_type=$rec->url_type, status=$rec->status_code, grade=$rec->grade_code, url=$rec->url, ");
                        $rec->delete();

                        $cnt += 1;

                        if ($cnt % 1000 == 0) {
                            $time_end = microtime(true);
                            $execution_time = ($time_end - $startTime);
                            $endtime = round(($orphanscnt - $cnt) * ($execution_time / $cnt) / 3600, 1);
                            scartLog::logLine("D-$logname.cleanupOrphan; removed=$cnt ; execution_time=$execution_time secs, till end=$endtime hours");
                        }

                    }
                }

                $time_end = microtime(true);
                $execution_time = round($time_end - $startTime,1);
                scartLog::logLine("D-$logname.cleanupOrphan; removed=$cnt ; execution_time=$execution_time secs ");

            }

        } catch(\Exception $err) {

            scartLog::logLine("E-$logname.cleanupOrphan error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

        }

        return $cnt;
    }

    // OBSOLUTE -> done by cycle log system option
    public static function cleanupSystemlogs($logname) {

        $systemlog = base_path() . SCART_SYSTEM_LOG_FILE . '.log';

        $cleanlog = false;

        if (file_exists($systemlog)) {

            try {

                // log tabel
                $timeout = date('Y-m-d H:i:s', strtotime("-1 days"));
                Db::table(SCART_SYSTEM_EVENT_LOGS)->where('created_at','<',$timeout)->delete();

                // each week day one file
                $systemlogday = base_path() . SCART_SYSTEM_LOG_FILE . '.' .date('N') . '.log';
                copy ($systemlog, $systemlogday);

                // clear file (with same chown and chmod)
                file_put_contents($systemlog, "" );

                $cleanlog = true;

            } catch (\Exception $err) {

                scartLog::logLine("E-$logname.cleanupSystemlogs; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

        } else {

            scartLog::logLine("D-$logname.cleanupSystemlogs; cannot find '$systemlog' ");

        }

        return $cleanlog;
    }

    public static function cleanupRetention($closedRetention,$logname,&$job_records) {

        // status_code IN ['close','close_offline','close_double']
        // updated_at = status_code set = retention offset
        $closed = ['close','close_offline','close_double'];
        $anonymousFields = scartSchedulerCreateReports::getAnonymousColumns();
        $anonymousNTDfields = ['url','ip'];
        $retentionDate = @date('Y-m-d H:i:s',strtotime(date('Y-m-d H:i:s')." $closedRetention"));

        if ($retentionDate) {

            // use received_at
            $inputs = Input::whereIn('status_code',$closed)
                ->where('url','NOT LIKE','anonymous%')
                ->where('received_at','<=',$retentionDate)
                ->get();

            $cnt = $inputs->count();

            if ($cnt > 0) {

                scartLog::logLine("D-$logname; found $cnt records which are older then '$retentionDate' and not already anonymous");
                $report_line = "Retention ($closedRetention) cleanup count: $cnt";

                foreach ($inputs AS $input) {

                    // fill with unique dummy
                    $resetvalue = 'anonymous-'.$input->id;
                    $update = array_fill_keys($anonymousFields,$resetvalue);
                    scartLog::logLine("D-$logname; clear anonymous; filenumber={$input->filenumber}, status={$input->status_code}, updated_at={$input->updated_at}, reset='$resetvalue'");

                    // Note: use forceDelete; records must be totally be removed (GDPR)
                    Log::where('record_type',SCART_INPUT_TABLE)->where('record_id', $input->id)->forceDelete();
                    Input_history::where('input_id',$input->id)->forceDelete();

                    // Note: also remove extra fields importwebform
                    Input_history::where('input_id',$input->id)->where('type',SCART_INPUT_EXTRAFIELD_WEBFORM)->forceDelete();

                    // INPUT Overwrite values from GDPR fields
                    Db::table(SCART_INPUT_TABLE)->where('id',$input->id)->update($update);

                    // NTD_URL Overwrite values from GDPR fields
                    $update = array_fill_keys($anonymousNTDfields,$resetvalue);
                    Db::table(SCART_NTD_URL_TABLE)->where('record_type',SCART_INPUT_TYPE)->where('record_id',$input->id)->update($update);
                    //Ntd_url::where('record_type',strtolower(class_basename($input)))->where('record_id',$input->id)->forceDelete();

                    // @ToDo; what about possible priviacy  info in note or ntd_note ?

                    $job_records[] = [
                        'filenumber' => $input->filenumber,
                        'url' => $resetvalue,
                        'status' => $input->status_code,
                    ];

                }

            } else {
                scartLog::logLine("D-$logname; no old records found to make anonymous");
                $report_line = "Retention ($closedRetention) - no old reports to make anonymous";
            }


        } else {
            scartLog::logLine("W-$logname; wrong retention value '$closedRetention'!?! ");
            $report_line = "Wrong retention value '$closedRetention'!?! ";
        }

        return $report_line;
    }

    public static function cleanupDeleted($deleted_records,$logname,&$job_records) {

        $totcnt = 0;
        $before = date('Y-m-d 00:00:00',strtotime($deleted_records));

        $tables = Db::select("show tables");
        foreach ($tables AS $tab) {

            $table = (array)$tab;
            $table = implode('', $table);

            if (strpos($table, self::$_tableprefix) !== false) {

                if (!in_array($table, self::$_skiptables)) {

                    if (Schema::hasColumn($table,'deleted_at')) {

                        $status_timestamp = '['.date('Y-m-d H:i:s').'] ';

                        Try {

                            $records = Db::table($table)
                                ->whereNotNull('deleted_at')
                                ->where('deleted_at', '<', $before);
                            $cntdel = $records->count();

                            if ($cntdel > 0) {

                                scartLog::logLine("D-$logname; table=$table, before=$before, remove $cntdel deleted records... ");

                                $totcnt += $cntdel;

                                scartArchive::deleteChunk($table,$records);

                                $job_record = [
                                    'tablename' => $table,
                                    'count' => $cntdel,
                                    'status' => $status_timestamp . "removed deleted records",
                                ];
                                $job_records[] = $job_record;

                            }

                        } catch (\Exception $err) {
                            scartLog::logLine("E-$logname; cleanupDeleted error line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage());
                        }

                    }
                }
            }
        }

        if ($totcnt > 0) {
            $report_line = "Removed (total) $totcnt deleted records (< $before)";
        } else {
            $report_line = "No (old) deleted records before '$before' found";
        }

        return $report_line;
    }

}
