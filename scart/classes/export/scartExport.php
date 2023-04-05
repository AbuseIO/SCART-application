<?php

namespace abuseio\scart\classes\export;

/**
 * Export data
 *
 * whois
 *   url;url_host;url_ip;whois_at;host_owner;host_country;host_abusecontact;host_customcontact;registrar_owner;registrar_country;registrar_abusecontact;registrar_customcontact
 *
 * classified
 *   url;question-1-option-1..
 *
 * new 17-02-2022 ntd
 *
 *
 *
 */

use abuseio\scart\models\Systemconfig;
use Db;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\models\ImportExport_job;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_url;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Input;
use abuseio\scart\classes\rules\scartRules;

class scartExport {

    public static $version = '2.0.1';

    private static $_cached = [];

    private static $_basefields  = [
        'filenumber' => 'filenumber',
        'url' => 'url',
        'url_host' => 'host',
        'url_ip' => 'IP',
        'url_type' => 'url type',
        'status_code' => 'status',
        'grade_code' => 'classification',
        'source_code' => 'source',
        'received_at' => 'received',
        'firstseen_at' => 'first seen',
        'firstntd_at' => 'first NTD',
        'lastseen_at' => 'last seen',
    ];

    private static $_basefields_all = [
        'filenumber' => 'filenumber',
        'received_at' => 'received',
        'url_type' => 'url type',
        'status_code' => 'status',
        'grade_code' => 'classification',
        'type_code' => 'type',
        'source_code' => 'source',
        'reference' => 'reference',
        'firstseen_at' => 'first seen',
        'firstntd_at' => 'first NTD',
        'lastseen_at' => 'last seen',
    ];

    private static $_whoisheaders_default = [
        'host_owner' => '(unknown)',
        'host_country' => '',
        'host_abusecustom' => '',
        'host_ntd_count' => '0',
        'site_owner' => '(not set))',
        'site_country' => '',
        'site_abusecustom' => '',
        'site_ntd_count' => '0',
        'registrar_owner' => '(unknown)',
        'registrar_country' => '',
        'registrar_abusecustom' => '',
        'registrar_ntd_count' => '0',
    ];

    private static $_whoisheaders = [
        'host_owner' => 'hoster owner',
        'host_country' => 'hoster country',
        'host_abusecustom' => 'hoster abuse email',
        'host_ntd_count' => 'hoster NTD',
        'site_owner' => 'site owner',
        'site_country' => 'site owner country',
        'site_abusecustom' => 'site owner abuse email',
        'site_ntd_count' => 'site NTD',
        'registrar_owner' => 'registrar owner',
        'registrar_country' => 'registrar country',
        'registrar_abusecustom' => 'registrar abuse email',
        'registrar_ntd_count' => 'registrar NTD',
    ];

    static function addLineValue($line,$value) {
        $line .= '"'.addslashes($value).'"'.SCART_EXPORT_CSV_DELIMIT;
        return $line;
    }

    public static function exportClassifiedRecords($grademeta,$class, $from, $to) {

        $lines = [];

        // NB: always input type
        $record_type = SCART_INPUT_TYPE;

        //trace_sql();;
        $records = Input::whereNotIn('status_code', [SCART_STATUS_OPEN,SCART_STATUS_CANNOT_SCRAPE,SCART_STATUS_WORKING]);

        // 2020/4/6/gs: received_at better indication of received time
        $records = $records->where('grade_code', $class)
            ->where('received_at','>',$from)
            ->where('received_at','<',$to)
            ->get();
        foreach ($records AS $record) {

            $line = '';
            foreach (SELF::$_basefields AS $field => $label) {
                $line = self::addLineValue($line,$record->$field );
            }

            if (count($grademeta['labels']) > 0) {
                foreach ($grademeta['types'] AS $id => $type) {
                    $value = Grade_answer::where('record_type', $record_type)->where('record_id', $record->id)->where('grade_question_id', $id)->first();
                    $values = ($value) ? unserialize($value->answer) : '';
                    // $showvvalues = (is_array($values)) ? implode('-', $values) : $values; scartLog::logLine("D-id=$id, type=" . $type  . ", values=" . $showvvalues);
                    if ($type == 'select' || $type == 'checkbox') {
                        if ($values == '') $values = [];
                        foreach ($grademeta['values'][$id] AS $optval => $optlab) {
                            $line = self::addLineValue($line,(in_array($optval, $values) ? 'y' : 'n') );
                        }
                    } elseif ($type == 'radio') {
                        if ($values != '') $values = implode('', $values);
                        $line = self::addLineValue($line,((isset($grademeta['values'][$id][$values])) ? $grademeta['values'][$id][$values] : '') );
                    } elseif ($type == 'text') {
                        if ($values != '') $values = implode('', $values);
                        $line = self::addLineValue($line,$values );
                    }
                }
            }

            //scartLog::logLine("Row line: $line");
            $lines[] = $line;
        }

        return $lines;
    }

    public static function exportClassified($class, $from, $to)
    {

        $cnt = 0;
        $lines = [];

        scartLog::logLine("D-exportClassified($class, $from, $to)");

        /**
         * vragen verzamelen; afhankelijk van type header;
         * - radio + text = 1 header
         * - selectie/checkbox = per mogelijk antwoord header kolome
         *
         */

        $line = '';
        foreach (SELF::$_basefields AS $field => $label) {
            $line = self::addLineValue($line,$label );
        }
        $grademeta = Grade_question::getGradeHeaders($class);
        if (count($grademeta['labels']) > 0) {
            foreach ($grademeta['labels'] AS $id => $label) {
                $line = self::addLineValue($line,$label );
            }
        }
        //scartLog::logLine("D-Header line: $line");
        $lines[] = $line;

        $exportlines = self::exportClassifiedRecords($grademeta,$class, $from, $to);
        $lines = array_merge($lines , $exportlines);

        return $lines;
    }

    static function count_ntds($record, $record_type, $abusecontact_id) {

        if ($abusecontact_id) {
            $cnt = Ntd_url::join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
                ->where(SCART_NTD_TABLE.'.abusecontact_id',$abusecontact_id)
                ->whereIn(SCART_NTD_TABLE.'.status_code',[SCART_NTD_STATUS_SENT_SUCCES,SCART_NTD_STATUS_QUEUED])
                ->where(SCART_NTD_URL_TABLE.'.record_type',$record_type)
                ->where(SCART_NTD_URL_TABLE.'.record_id',$record->id)
                ->select(SCART_NTD_URL_TABLE.'.ntd_id')
                ->distinct()
                ->count();
        } else {
            $cnt = 0;
        }
        return $cnt;
    }

    public static function exportWhoisRecords($record_type, $class, $from, $to) {

        $lines = [];

        //trace_sql();
        $records = ($record_type == SCART_INPUT_TYPE) ?
            Input::whereNotIn('status_code', [SCART_STATUS_OPEN,SCART_STATUS_CANNOT_SCRAPE,SCART_STATUS_WORKING]) :
            Notification::whereNotIn('status_code', [SCART_STATUS_OPEN,SCART_STATUS_CANNOT_SCRAPE,SCART_STATUS_WORKING]);

        $records = $records->where('grade_code', $class)
            ->where('created_at','>',$from)
            ->where('created_at','<',$to)
            ->get();

        $policecontact = Abusecontact::where('police_contact','<>',0)->first();

        foreach ($records AS $record) {

            $line = '';
            foreach (SELF::$_basefields AS $field => $label) {
                $line = self::addLineValue($line,$record->$field);
            }

            // send to police (NTD)
            if ($policecontact) {
                $cnt = Ntd_url::where('record_type',$record_type)
                    ->where('record_id',$record->id)
                    ->join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
                    ->where(SCART_NTD_TABLE.'.abusecontact_id',$policecontact->id)
                    ->count();
                $police = ($cnt > 0) ? 'y' : 'n';
            } else {
                $police = 'n';
            }
            $line = self::addLineValue($line,"$police");

            $whois = Abusecontact::getWhois($record);

            if (count($whois) > 0) {

                $whois = array_merge(self::$_whoisheaders_default, $whois);

                if ($record->host_abusecontact_id) {
                    $whois['host_ntd_count'] = self::count_ntds($record,$record_type,$record->host_abusecontact_id);
                }

                if ($siteownerac_id = scartRules::checkSiteOwnerRule($record->url) ) {
                    $siteownerac = Abusecontact::find($siteownerac_id);
                    if ($siteownerac) {
                        $whois['site_owner'] = $siteownerac->owner;
                        $whois['site_country'] = $siteownerac->abusecountry;
                        $whois['site_abusecustom'] = $siteownerac->abusecustom;
                        $whois['site_ntd_count'] = self::count_ntds($record,$record_type,$siteownerac_id);
                    }
                }

                if ($record->registrar_abusecontact_id) {
                    $whois['registrar_ntd_count'] = self::count_ntds($record,$record_type,$record->registrar_abusecontact_id);
                }

                foreach (SELF::$_whoisheaders AS $field => $label) {
                    $line = self::addLineValue($line,$whois[$field]);
                }

                //scartLog::logLine("Row line: $line");
                $lines[] = $line;

                //exit();
            }

        }

        return $lines;
    }

    public static function exportWhois($class, $from, $to) {

        $lines = [];

        $line = '';
        foreach (SELF::$_basefields AS $field => $label) {
            $line = self::addLineValue($line,$label);
        }
        $line = self::addLineValue($line,"police");
        foreach (SELF::$_whoisheaders AS $field => $label) {
            $line = self::addLineValue($line,$label);
        }
        //scartLog::logLine("D-Header line: $line");
        scartLog::logLine("D-exportWhois($class, $from, $to) ");

        $lines[] = $line;

        $exportlines = self::exportWhoisRecords(SCART_INPUT_TYPE,$class, $from, $to);
        $lines = array_merge($lines , $exportlines);

        return $lines;
    }

    public static function exportAllRecords($record_type,$gradeillegal,$gradenotillegal, $from, $to, $outputfile='') {

        $lines = [];

        //trace_sql();;
        $records = ($record_type == SCART_INPUT_TYPE) ?
            Input::whereNotIn('status_code', [SCART_STATUS_OPEN,SCART_STATUS_WORKING]) :
            Notification::whereNotIn('status_code', [SCART_STATUS_OPEN,SCART_STATUS_WORKING]);

        // POLICE contact
        $policecontact = Abusecontact::where('police_contact','<>',0)->first();

        // 2020/4/6/gs: received_at better indication of received time
        $records = $records->where('received_at','>',$from)
            ->where('received_at','<',$to)
            ->get();

        $linecnt = 0; $lines_count = 10000;
        //$delimitencode = urlencode(SCART_EXPORT_CSV_DELIMIT);

        foreach ($records AS $record) {

            $line = '';
            foreach (SELF::$_basefields_all AS $field => $label) {
                //$val = str_replace(SCART_EXPORT_CSV_DELIMIT,$delimitencode,$record->$field);
                $line = self::addLineValue($line,$record->$field);
            }

            // send to police (NTD)
            if ($policecontact) {
                $cnt = Ntd_url::where('record_type',$record_type)
                    ->where('record_id',$record->id)
                    ->join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
                    ->where(SCART_NTD_TABLE.'.abusecontact_id',$policecontact->id)
                    ->count();
                $police = ($cnt > 0) ? 'y' : 'n';
            } else {
                $police = 'n';
            }
            $line = self::addLineValue($line,"$police");

            $whois = Abusecontact::getWhois($record);
            if (count($whois) > 0) {

                $whois = array_merge(self::$_whoisheaders_default, $whois);

                // caching
                $chkkey = 'chk'.$record->host_abusecontact_id.$record->url_host.$record->registrar_abusecontact_id;
                if (!isset(SELF::$_cached[$chkkey])) {

                    $whoisextra = [];

                    if ($record->host_abusecontact_id) {
                        $whoisextra['host_ntd_count'] = self::count_ntds($record,$record_type,$record->host_abusecontact_id);
                    }

                    if ($siteownerac_id = scartRules::checkSiteOwnerRule($record->url) ) {
                        $siteownerac = Abusecontact::find($siteownerac_id);
                        if ($siteownerac) {
                            $whoisextra['site_owner'] = $siteownerac->owner;
                            $whoisextra['site_country'] = $siteownerac->abusecountry;
                            $whoisextra['site_abusecustom'] = $siteownerac->abusecustom;
                            $whoisextra['site_ntd_count'] = self::count_ntds($record,$record_type,$siteownerac_id);
                        }
                    }

                    if ($record->registrar_abusecontact_id) {
                        $whoisextra['registrar_ntd_count'] = self::count_ntds($record,$record_type,$record->registrar_abusecontact_id);
                    }

                    SELF::$_cached[$chkkey] = $whoisextra;
                } else {
                    $whoisextra = SELF::$_cached[$chkkey];
                }

                $whois = array_merge($whois,$whoisextra);

                foreach (SELF::$_whoisheaders AS $field => $label) {
                    $line = self::addLineValue($line,$whois[$field]);
                }

            } else {

                $line .= str_repeat(SCART_EXPORT_CSV_DELIMIT, count(SELF::$_whoisheaders) );

            }

            if ($record->grade_code == SCART_GRADE_ILLEGAL) {
                if (count($gradeillegal['labels']) > 0) {
                    foreach ($gradeillegal['types'] AS $id => $type) {
                        $value = Grade_answer::where('record_type', $record_type)->where('record_id', $record->id)->where('grade_question_id', $id)->first();
                        $values = ($value) ? unserialize($value->answer) : '';
                        // $showvvalues = (is_array($values)) ? implode('-', $values) : $values; scartLog::logLine("D-id=$id, type=" . $type  . ", values=" . $showvvalues);
                        if ($type == 'select' || $type == 'checkbox') {
                            if ($values == '') $values = [];
                            foreach ($gradeillegal['values'][$id] AS $optval => $optlab) {
                                $line = self::addLineValue($line,(in_array($optval, $values) ? 'y' : 'n'));
                            }
                        } elseif ($type == 'radio') {
                            if ($values != '') $values = implode('', $values);
                            $line = self::addLineValue($line,((isset($gradeillegal['values'][$id][$values])) ? $gradeillegal['values'][$id][$values] : '') );
                        } elseif ($type == 'text') {
                            if ($values != '') $values = implode('', $values);
                            $line = self::addLineValue($line, $values );
                        }
                    }
                }
            } elseif ($record->grade_code == SCART_GRADE_NOT_ILLEGAL) {
                if (count($gradenotillegal['labels']) > 0) {

                    $line .= str_repeat(SCART_EXPORT_CSV_DELIMIT, count($gradeillegal['labels']) );

                    foreach ($gradenotillegal['types'] AS $id => $type) {
                        $value = Grade_answer::where('record_type', $record_type)->where('record_id', $record->id)->where('grade_question_id', $id)->first();
                        $values = ($value) ? unserialize($value->answer) : '';
                        // $showvvalues = (is_array($values)) ? implode('-', $values) : $values; scartLog::logLine("D-id=$id, type=" . $type  . ", values=" . $showvvalues);
                        if ($type == 'select' || $type == 'checkbox') {
                            if ($values == '') $values = [];
                            foreach ($gradenotillegal['values'][$id] AS $optval => $optlab) {
                                $line = self::addLineValue($line,(in_array($optval, $values) ? 'y' : 'n') );
                            }
                        } elseif ($type == 'radio') {
                            if ($values != '') $values = implode('', $values);
                            $line = self::addLineValue($line,((isset($gradenotillegal['values'][$id][$values])) ? $gradenotillegal['values'][$id][$values] : '') );
                        } elseif ($type == 'text') {
                            if ($values != '') $values = implode('', $values);
                            $line = self::addLineValue($line, $values );
                        }
                    }
                }
            }

            //scartLog::logLine("Row line: $line");
            $lines[] = $line;

            $linecnt += 1;
            if ((count($lines) % $lines_count == 0) && $outputfile) {
                scartLog::logLine("type=$record_type, cnt=$linecnt; append to file $outputfile");
                file_put_contents($outputfile, implode("\n", $lines), FILE_APPEND );
                $lines = [];
            }

        }

        if (count($lines) > 0 && $outputfile) {
            scartLog::logLine("type=$record_type, cnt=$linecnt; append to file $outputfile");
            file_put_contents($outputfile, implode("\n", $lines), FILE_APPEND );
        }

        return $lines;
    }

    public static function exportAll($from, $to,$outputfile='') {

        $lines = [];

        $line = '';
        foreach (SELF::$_basefields_all AS $field => $label) {
            $line = self::addLineValue($line,$label );
        }

        // POLICE
        $line = self::addLineValue($line,"police");

        // WHOSI
        foreach (SELF::$_whoisheaders AS $field => $label) {
            $line = self::addLineValue($line,$label );
        }

        // ILLEGAL
        $gradeillegal = Grade_question::getGradeHeaders(SCART_GRADE_ILLEGAL);
        if (count($gradeillegal['labels']) > 0) {
            foreach ($gradeillegal['labels'] AS $id => $label) {
                $line = self::addLineValue($line,$label );
            }
        }

        // NOT_ILLEGAL
        $gradenotillegal  = Grade_question::getGradeHeaders(SCART_GRADE_NOT_ILLEGAL);
        if (count($gradenotillegal['labels']) > 0) {
            foreach ($gradenotillegal['labels'] AS $id => $label) {
                $line = self::addLineValue($line,$label );
            }
        }

        //scartLog::logLine("D-Header line: $line");
        $lines[] = $line;

        if ($outputfile) {
            scartLog::logLine("Open file $outputfile and write header line");
            file_put_contents($outputfile, "$line \n" );
        }

        //scartLog::logLine("D-Header line: $line");
        scartLog::logLine("D-exportAll($from, $to) ");

        $exportlines = self::exportAllRecords(SCART_INPUT_TYPE,$gradeillegal,$gradenotillegal, $from, $to, $outputfile);
        $lines = array_merge($lines , $exportlines);

        //$exportlines = self::exportAllRecords(SCART_NOTIFICATION_TYPE,$gradeillegal,$gradenotillegal, $from, $to, $outputfile);
        //$lines = array_merge($lines , $exportlines );

        return $lines;
    }

    private static $_ntdfields  = [
        'filenumber' => 'NTD filenumber',
        'type' => 'NTD type',
        'status_code' => 'NTD status',
        'status_time' => 'NTD status time',
        'abuse_owner' => 'abuse owner',
        'abuse_type' => 'abuse type',
        'abuse_country' => 'abuse country',
        'abuse_email' => 'abuse email',
        'url' => 'url',
        'ip' => 'IP',
        'firstseen_at' => 'first seen',
        'lastseen_at' => 'last seen',
        'online_counter' => 'online counter',
    ];

    public static function exportNTD($from, $to) {

        scartLog::logLine("D-exportNTD($from, $to)");

        $line = '';
        foreach (SELF::$_ntdfields AS $field => $label) {
            $line = self::addLineValue($line,$label);
        }
        $lines = [$line];

        $ntds = Ntd::where([
            ['status_code',SCART_NTD_STATUS_SENT_SUCCES],
            ['status_time','>=',$from],
            ['status_time','<=',$to],
        ])->get();
        foreach ($ntds AS $ntd) {
            $abusecontact = Abusecontact::where('id',$ntd->abusecontact_id)->first();
            if (!$abusecontact) {
                $abusecontact = new \stdClass();
                $abusecontact->owner = 'unknown';
                $abusecontact->abusecustom = 'unknown';
                $abusecontact->abusecountry = 'unknown';
            }
            $ntdurls = Ntd_url::where('ntd_id',$ntd->id)->get();
            foreach ($ntdurls AS $ntdurl) {
                $line = '';
                $line = self::addLineValue($line,$ntd->filenumber);
                $line = self::addLineValue($line,$ntd->type);
                $line = self::addLineValue($line,$ntd->status_code);
                $line = self::addLineValue($line,$ntd->status_time);
                $line = self::addLineValue($line,$abusecontact->owner);
                $line = self::addLineValue($line,$ntd->abusecontact_type);
                $line = self::addLineValue($line,$abusecontact->abusecountry);
                $line = self::addLineValue($line,$abusecontact->abusecustom);
                $line = self::addLineValue($line,$ntdurl->url);
                $line = self::addLineValue($line,$ntdurl->ip);
                $line = self::addLineValue($line,$ntdurl->firstseen_at);
                $line = self::addLineValue($line,$ntdurl->lastseen_at);
                $line = self::addLineValue($line,$ntdurl->online_counter);
                $lines[] = $line;
            }
        }

        return $lines;
    }


    /**
     * day X; exportNTD (see above)
     * day X-1; exportNTDclosed -> closed urls from NTD's sent day before
     *
     */

    private static $_closedfields  = [
        'filenumber' => 'filenumber',
        'url' => 'url',
        'status_code' => 'status',
        'abuse_owner' => 'abuse owner',
        'abuse_country' => 'abuse country',
        'abuse_email' => 'abuse email',
        'ip' => 'IP',
        'firstseen_at' => 'first seen',
        'lastseen_at' => 'last seen',
        'online_counter' => 'online counter',
    ];

    /**
     * export where NTD's are sent for urls closed in the period from > status_time (NTD sent time) <= to
     *
     * @param $from
     * @return string[]
     */

    public function exportNTDclosed($to) {

        $from = date('Y-m-d H:i:s',strtotime("$to -1 day"));
        scartLog::logLine("D-exportNTDclosed; period is: '$from' > status_time <= '$to' ");

        $line = '';
        foreach (SELF::$_closedfields AS $field => $label) {
            $line = self::addLineValue($line,$label);
        }
        $lines = [$line];

        // get urls in NTD send the day BEFORE

        $ntdurls = Ntd_url::join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
            ->whereIn(SCART_NTD_TABLE.'.status_code',[SCART_NTD_STATUS_SENT_SUCCES,SCART_NTD_STATUS_SENT_API_SUCCES])
            ->where(SCART_NTD_TABLE.'.status_time','>',$from)
            ->where(SCART_NTD_TABLE.'.status_time','<=',$to)
            ->select(SCART_NTD_URL_TABLE.'.*')
            ->distinct();
        scartLog::logLine("D-SQL=".$ntdurls->toSql());

        if ($ntdurls->exists()) {

            $ntdurls = $ntdurls->get();

            $doneurls = []; $cnt = $cntclosed = 0;
            foreach ($ntdurls AS $ntdurl) {

                /**
                 * Check if not url already done
                 *
                 * Note: cannot use distinct in query because we need other fields of ntl_url
                 *
                 */

                if (!in_array($ntdurl->url,$doneurls)) {

                    // detect if urls has NOT CHECKONLINE or CHANGED status anymore

                    /*
                    $input = Input::where('url',$ntdurl->url)
                        ->whereNotIn('status_code',[
                            SCART_STATUS_ABUSECONTACT_CHANGED,
                            SCART_STATUS_FIRST_POLICE,
                            SCART_STATUS_SCHEDULER_CHECKONLINE,
                            SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL])
                        ->first();
                    */
                    $input = Input::where('url',$ntdurl->url)
                        ->whereIn('status_code',[
                            SCART_STATUS_CLOSE_OFFLINE,
                            SCART_STATUS_CLOSE_OFFLINE_MANUAL])
                        ->first();
                    if ($input) {

                        $abusecontact = Abusecontact::where('id',$input->host_abusecontact_id)->first();
                        if (!$abusecontact) {
                            $abusecontact = new \stdClass();
                            $abusecontact->owner = 'unknown';
                            $abusecontact->abusecustom = 'unknown';
                            $abusecontact->abusecountry = 'unknown';
                        }

                        $line = '';
                        $line = self::addLineValue($line,$input->filenumber);
                        $line = self::addLineValue($line,$ntdurl->url);
                        $line = self::addLineValue($line,$input->status_code);      // CLOSE_OFFLINE or CLOSE_OFFLINE_MANUAL
                        $line = self::addLineValue($line,$abusecontact->owner);
                        $line = self::addLineValue($line,$abusecontact->abusecountry);
                        $line = self::addLineValue($line,$abusecontact->abusecustom);
                        $line = self::addLineValue($line,$ntdurl->ip);
                        $line = self::addLineValue($line,$ntdurl->firstseen_at);
                        $line = self::addLineValue($line,$ntdurl->lastseen_at);
                        $line = self::addLineValue($line,$ntdurl->online_counter);
                        $lines[] = $line;

                        $cntclosed += 1;

                    }

                    $cnt += 1;

                }

                $doneurls[] = $ntdurl->url;

            }

            scartLog::logLine("D-Found $cnt NTD urls sent in this period; number of urls closed: $cntclosed");

        } else {

            scartLog::logLine("D-No NTD urls sent in this period");

        }


        return $lines;
    }



    //** SCART REPORTS FUNCTION **/

    /**
     * Get Query Filtered for SCART Report functions
     *
     * @param $filter_grade_code
     * @param $filter_status_code
     * @param $filter_host_country
     * @param $filter_start
     * @param $filter_end
     * @return mixed
     *
     */

    public static function exportFiltered($filter_grade_code,$filter_status_code,$filter_host_country,$filter_start,$filter_end) {

        // select all
        $queryrecords = Input::where(SCART_INPUT_TABLE.'.id','>',0);

        // report start / end
        if ($filter_start && $filter_end) {
            $start = substr($filter_start,0,10) . ' 00:00:00';
            $end = substr($filter_end,0,10) . ' 23:59:59';
            $queryrecords->where(SCART_INPUT_TABLE.'.received_at','>=',$start)
                ->where(SCART_INPUT_TABLE.'.received_at','<=',$end);
        }

        if (!empty($filter_grade_code) && $filter_grade_code != '*') {

            if (is_array($filter_grade_code)) {
                //scartLog::logLine("D-filter_grade_code=" . print_r($filter_grade_code,true));
                $values = [];
                foreach ($filter_grade_code AS $item) {
                    foreach ($item AS $key => $value) {
                        $values[] = $value;
                    }
                }
                if (count($values) > 0) {
                    //scartLog::logLine("D-Filter (OR) grade_code=" . print_r($values,true));
                    $queryrecords->where(function ($query) use ($values) {
                        foreach ($values as $value) {
                            $query->orWhere('grade_code', $value);
                        }
                    });
                }
            } else {
                $queryrecords->where('grade_code', $filter_grade_code);
            }

        }

        if (!empty($filter_status_code) && $filter_status_code != '*') {
            if (is_array($filter_status_code)) {
                //scartLog::logLine("D-filter_status_code=" . print_r($filter_status_code,true));
                $values = [];
                foreach ($filter_status_code AS $item) {
                    foreach ($item AS $key => $value) {
                        $values[] = $value;
                    }
                }
                if (count($values) > 0) {
                    //scartLog::logLine("D-Filter (OR) status_code=" . print_r($values,true));
                    $queryrecords->where(function($query) use ($values) {
                        foreach ($values AS $value) {
                            $query->orWhere('status_code',$value);
                        }
                    });
                }
            } else {
                $queryrecords->where('status_code', $filter_status_code);
            }
        }

        if ($filter_host_country != '*') {
            $country = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
            $detect = Systemconfig::get('abuseio.scart::classify.detect_country', '');
            $detect = explode(',',$detect);
            if ($filter_host_country == $country) {
                $queryrecords->join('abuseio_scart_abusecontact', 'abuseio_scart_abusecontact.id', '=', SCART_INPUT_TABLE.'.host_abusecontact_id')
                    ->whereIn(Db::raw('LOWER(abuseio_scart_abusecontact.abusecountry)'), $detect)
                    ->select(SCART_INPUT_TABLE.'.*');
            } else {
                $queryrecords->join('abuseio_scart_abusecontact', 'abuseio_scart_abusecontact.id', '=', SCART_INPUT_TABLE.'.host_abusecontact_id')
                    ->whereNotIn(Db::raw('LOWER(abuseio_scart_abusecontact.abusecountry)'), $detect)
                    ->select(SCART_INPUT_TABLE.'.*');
            }
        }

        // get data (collection)
        scartLog::logLine("D-scartExport; report query: " . $queryrecords->toSql() );
        $records = $queryrecords->get();

        return $records;
    }


    public static function inFilter($filter,$value) {

        $found = false;
        foreach ($filter AS $item) {
            if (in_array($value,$item)) {
                $found = true;
                break;
            }
        }
        return $found;
    }

    /**
     * Checksum functions to be sure one report at once
     */

    static function getExportChecksum($report) {
        return md5($report->title);
    }

    public static function addExportJob($report) {

        // unique checksum each import mail
        $checksum = SELF::getExportChecksum($report);

        // data for check
        $data = [
            'title' => $report->title,
            'status_code' => $report->status_code,
            'status_at' => $report->status_at,
        ];

        // check if not already, including trash
        $cnt = ImportExport_job::where('interface',SCART_INTERFACE_EXPORTREPORT)
            ->where('action',SCART_INTERFACE_EXPORTREPORT_ACTION)
            ->where('checksum',$checksum)
            ->count();

        if ($cnt == 0) {
            // create if new
            $export = new ImportExport_job();
            $export->interface = SCART_INTERFACE_EXPORTREPORT;
            $export->action = SCART_INTERFACE_EXPORTREPORT_ACTION;
            $export->checksum = $checksum;
            $export->data = serialize($data);
            $export->status = SCART_IMPORTEXPORT_STATUS_EXPORT;
            $export->save();
        }

        // return true when not found
        return ($cnt==0);
    }

    static function delExportJob($report) {

        $checksum = SELF::getExportChecksum($report);
        ImportExport_job::where('interface',SCART_INTERFACE_EXPORTREPORT)
            ->where('action',SCART_INTERFACE_EXPORTREPORT_ACTION)
            ->where('checksum',$checksum)
            ->delete();
    }



}
