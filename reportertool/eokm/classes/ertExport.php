<?php

namespace reportertool\eokm\classes;

/**
 * 2019/7/31/Gs:
 *
 * Een vrij recht-toe-recht-aan export van data in de database.
 * Zodat externe snel en relatief eenvoudig voorzien kunnen worden van data (TU Delft / PWC)
 *
 * whois
 *   url;url_host;url_ip;whois_at;host_owner;host_country;host_abusecontact;host_customcontact;registrar_owner;registrar_country;registrar_abusecontact;registrar_customcontact
 *
 * classified
 *   url;question-1-option-1..
 *
 * not yet:
 * - ntds; per url hoeveel ntd's, wanneer gecontroleerd, wanneer offline
 * - ...
 *
 *
 */

use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Grade_answer;
use ReporterTool\EOKM\Models\Grade_question;
use ReporterTool\EOKM\Models\Grade_question_option;
use ReporterTool\EOKM\Models\Notification;
use reportertool\eokm\classes\ertGrade;

class ertExport {

    public static $version = '1.0.1';

    private static $_basefields  = [
         'url' => 'url',
         'url_host' => 'host',
         'url_ip' => 'IP',
         'status_code' => 'status',
         'grade_code' => 'classification',
         'firstseen_at' => 'first seen',
         'online_counter' => 'online counter',
    ];

    private static $_whoisheaders = [
        'registrar_owner' => 'registrar_owner',
        'registrar_country' => 'registrar_country',
        'registrar_abusecontact' => 'registrar_abusecontact',
        'registrar_abusecustom' => 'registrar_abusecustom',
        'host_owner' => 'host_owner',
        'host_country' => 'host_country',
        'host_abusecontact' => 'host_abusecontact',
        'host_abusecustom' => 'host_abusecustom',
    ];

    public static function exportClassified($class, $from, $to)
    {

        $cnt = 0;
        $lines = [];

        ertLog::logLine("D-exportClassified($class, $from, $to)");

        /**
         * vragen verzamelen; afhankelijk van type header;
         * - radio + text = 1 header
         * - selectie/checkbox = per mogelijk antwoord header kolome
         *
         */

        $line = '';
        $grademeta = ertGrade::getGradeHeaders($class);
        if (count($grademeta['labels']) > 0) {
            foreach ($grademeta['labels'] AS $id => $label) {
                $line .= $label . ERT_EXPORT_CSV_DELIMIT;
            }
        }
        //ertLog::logLine("D-Header line: $line");
        $lines[] = $line;

        /*
        $nots = Notification::whereIn('status_code', [ERT_STATUS_CLOSE, ERT_STATUS_SCHEDULER_CHECKONLINE])
            ->where('grade_code', $class)
            ->whereBetween('created_at', [$from, $to])
            ->get();
        */
        //trace_sql();
        $nots = Notification::whereIn('status_code', [ERT_STATUS_CLOSE, ERT_STATUS_SCHEDULER_CHECKONLINE])
            ->where('grade_code', $class)
            ->where('created_at','>',$from)
            ->where('created_at','<',$to)
            ->get();
        foreach ($nots AS $notification) {

            $line = '';
            foreach (SELF::$_basefields AS $field => $label) {
                $line .= $notification->$field . ERT_EXPORT_CSV_DELIMIT;
            }

            if (count($grademeta['labels']) > 0) {
                foreach ($grademeta['types'] AS $id => $type) {
                    $value = Grade_answer::where('record_type', ERT_NOTIFICATION_TYPE)->where('record_id', $notification->id)->where('grade_question_id', $id)->first();
                    $values = ($value) ? unserialize($value->answer) : '';
                    // $showvvalues = (is_array($values)) ? implode('-', $values) : $values; ertLog::logLine("D-id=$id, type=" . $type  . ", values=" . $showvvalues);
                    if ($type == 'select' || $type == 'checkbox') {
                        if ($values == '') $values = [];
                        foreach ($grademeta['values'][$id] AS $optval => $optlab) {
                            $line .= (in_array($optval, $values) ? 'y' : 'n') . ERT_EXPORT_CSV_DELIMIT;
                        }
                    } elseif ($type == 'radio') {
                        if ($values != '') $values = implode('', $values);
                        $line .= ((isset($grademeta['values'][$id][$values])) ? $grademeta['values'][$id][$values] : '') . ERT_EXPORT_CSV_DELIMIT;
                    } else {
                        if ($values != '') $values = implode('', $values);
                        $line .=  $values . ERT_EXPORT_CSV_DELIMIT;
                    }
                }
            }
            //ertLog::logLine("D-row line: $line");
            $lines[] = $line;

            $cnt += 1;
        }

        return $lines;
    }

    public static function exportWhois($class, $from, $to) {

        $cnt = 0;
        $lines = [];

        $line = '';
        foreach (SELF::$_basefields AS $field => $label) {
            $line .= $label . ERT_EXPORT_CSV_DELIMIT;
        }
        foreach (SELF::$_whoisheaders AS $field => $label) {
            $line .= $label . ERT_EXPORT_CSV_DELIMIT;
        }
        //ertLog::logLine("D-Header line: $line");
        $lines[] = $line;

        ertLog::logLine("D-exportWhois($class, $from, $to) ");
        //trace_sql();;
        $nots = Notification::whereIn('status_code', [ERT_STATUS_CLOSE, ERT_STATUS_SCHEDULER_CHECKONLINE])
            ->where('grade_code', $class)
            ->where('created_at','>',$from)
            ->where('created_at','<',$to)
            ->get();
        foreach ($nots AS $not) {

            $line = '';
            foreach (SELF::$_basefields AS $field => $label) {
                $line .= $not->$field . ERT_EXPORT_CSV_DELIMIT;
            }

            $whois = Abusecontact::getWhois($not);

            foreach (SELF::$_whoisheaders AS $field => $label) {
                $line .= $whois[$field] . ERT_EXPORT_CSV_DELIMIT;
            }

            //ertLog::logLine("Row line: $line");
            $lines[] = $line;

            $cnt += 1;
        }

        return $lines;
    }



}
