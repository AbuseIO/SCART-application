<?php namespace abuseio\scart\classes\cleanup;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Whois_cache;
use Db;
use Config;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_parent;
use abuseio\scart\models\Scrape_cache;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\scheduler\scartScheduler;

class scartCleanup {

    public static function cleanupOutOfDate($cleanup_grade_timeout) {

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
        scartLog::logLine("D-schedulerCleanup: classify inputs older then '$timeout' count=" . count($inputs) );

        foreach ($inputs AS $input) {

            // in Input::beforeDelete we handle the delete of the foreign data
            $status = "Longer then $cleanup_grade_timeout hours waiting for classify" ;

            try {

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
                scartLog::logLine("E-cleanupOutOfDate error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

            }

        }

        return $job_records;
    }

    public static function cleanupScrapeCache() {

        // Remove scrape-cache from input/notificatie status_code <> CLASSIFY & SCRAPING

        Try {

            //trace_sql();

            // withTrashed -> softDelete not needed on this function

            $scraped = Scrape_cache::join(SCART_INPUT_TABLE,SCART_INPUT_TABLE.'.url_hash','=',SCART_SCRAPE_CACHE_TABLE.'.code')
                ->whereNotIn(SCART_INPUT_TABLE.'.status_code',[SCART_STATUS_GRADE,SCART_STATUS_SCHEDULER_SCRAPE])
                ->withTrashed()
                ->select(SCART_SCRAPE_CACHE_TABLE.'.code',
                    SCART_INPUT_TABLE.'.status_code AS input_status',
                    SCART_INPUT_TABLE.'.id AS input_id')
                ->get();
            $scrapecleaned = count($scraped);
            scartLog::logLine("D-cleanupScrapeCache; found scrape_cache to clear: count=$scrapecleaned ");

            if ($scrapecleaned > 0) {
                foreach ($scraped AS $scrape) {
                    // force delete (no undelete or audit trail)
                    scartLog::logLine("D-cleanupScrapeCache; delete scrape_cache from input_id=$scrape->input_id (status=$scrape->input_status)");
                    // use delCache function
                    //Db::table(SCART_SCRAPE_CACHE_TABLE)->where('code',$scrape->code)->delete();
                    Scrape_cache::delCache($scrape->code);
                }
            }

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-cleanupScrapeCache error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

        }

        return $scrapecleaned;
    }

    public static function cleanupWhoisCache() {

        $count = 0;
        Try {

            $nowstamp = date('Y-m-d H:i:s');
            $count = Whois_cache::where('max_age','>',$nowstamp)->count();
            if ($count > 0) {
                scartLog::logLine("D-cleanupScrapeCache; delete whoiscache $count records ");
                Whois_cache::where('max_age','>',$nowstamp)->forceDelete();
            }

        } catch(\Exception $err) {

            // NB: \Expection is important, else not in this catch when error in Mail
            scartLog::logLine("E-cleanupWhoisCache error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

        }

        return $count;
    }

    public static function cleanupOrphan() {

        $cnt = 0;

        Try {

            // 1: Orphan = (image/video url input without a (active) connection in input_parent)

            $orphans = Db::select("SELECT id FROM ".SCART_INPUT_TABLE."
                 WHERE ".SCART_INPUT_TABLE.".url_type <> 'mainurl'
                 AND ".SCART_INPUT_TABLE.".deleted_at IS NULL
                 AND ".SCART_INPUT_TABLE.".status_code = '".SCART_STATUS_GRADE."'
                 AND NOT EXISTS (SELECT 1 FROM ".SCART_INPUT_PARENT_TABLE." WHERE ".SCART_INPUT_PARENT_TABLE.".deleted_at IS NULL
         		 	 AND ".SCART_INPUT_PARENT_TABLE.".input_id=".SCART_INPUT_TABLE.".id)") ;
            $orphanscnt = count($orphans);

            if ($orphanscnt > 0) {

                scartLog::logLine("D-cleanupOrphan; found inputs with status classify and without mainurl-input (orphans); count=$orphanscnt");

                $startTime = microtime(true);

                foreach ($orphans AS $orphan) {
                    $rec = Input::find($orphan->id);
                    if ($rec) {
                        //scartLog::logLine("D-cleanupOrphan remove; id=$rec->id, url_type=$rec->url_type, status=$rec->status_code, grade=$rec->grade_code, url=$rec->url, ");
                        $rec->delete();

                        $cnt += 1;

                        if ($cnt % 1000 == 0) {
                            $time_end = microtime(true);
                            $execution_time = ($time_end - $startTime);
                            $endtime = round(($orphanscnt - $cnt) * ($execution_time / $cnt) / 3600, 1);
                            scartLog::logLine("D-cleanupOrphan; removed=$cnt ; execution_time=$execution_time secs, till end=$endtime hours");
                        }

                    }
                }

                $time_end = microtime(true);
                $execution_time = round($time_end - $startTime,1);
                scartLog::logLine("D-cleanupOrphan; removed=$cnt ; execution_time=$execution_time secs ");

            }

        } catch(\Exception $err) {

            scartLog::logLine("E-cleanupOrphan error: line=".$err->getLine()." in ".$err->getFile().", message: ".$err->getMessage() );

        }

        return $cnt;
    }

    // OBSOLUTE -> done by cycle log system option
    public static function cleanupSystemlogs() {

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

                scartLog::logLine("E-schedulerCleanup; cleanupSystemlogs; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

            }

        } else {

            scartLog::logLine("D-schedulerCleanup; cleanupSystemlogs; cannot find '$systemlog' ");

        }

        return $cleanlog;
    }



}
