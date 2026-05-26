<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\cleanup\scartArchive;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\Controllers\Startpage;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Iccam_hotline;
use abuseio\scart\Models\ImportWebform;
use abuseio\scart\models\Input_extrafield;
use Config;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Ntd_url;
use abuseio\scart\models\Report;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Input;
use System\Models\File;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\export\scartExport;
use abuseio\scart\classes\mail\scartMail;

class scartSchedulerCreateReports extends scartScheduler {

    public static function doJob() {

        if (SELF::startScheduler('CreateReports', 'createreports')) {

            /** RESET CACHE DASHBOARD COUNTERS - ALSO REPORTING **/

            if ( (intval(date('i')) % 15) == 0) {
                $startpage = new Startpage();
                $startpage->resetLoadCache();
            }

            /** CHECK IF REPORTS TO PROCESS **/

            $take =  Systemconfig::get('abuseio.scart::scheduler.createreports.take','1');

            $reports = Report::where('status_code',SCART_STATUS_REPORT_CREATED)->take($take)->get();

            if (count($reports)) {

                $logname = SELF::$logname;

                $adminreport = [];

                // give ourself time!
                set_time_limit(0);

                foreach ($reports AS $report) {

                    try {

                        // check checksum (if not already active) (is possible with long running reports)
                        if (scartExport::addExportJob($report)) {

                            scartLog::logLine("D-{$logname}; create report '$report->title'; start=$report->filter_start, end=$report->filter_end");

                            $report->status_code = SCART_STATUS_REPORT_WORKING;
                            $report->status_at = date('Y-m-d H:i:s');
                            $report->save();

                            // tmpfile name
                            $tmpfile = temp_path() . '/export-' . date('YmdHis') . '.csv';

                            // export url or attributes?
                            if ($report->filter_type != SCART_REPORT_TYPE_ATTRIBUTE) {

                                $data = self::exportUrls($logname,$tmpfile,$report);

                            } else {

                                $data = self::exportAttributes($logname,$tmpfile,$report);

                            }

                            scartLog::logLine("D-{$logname}; status_code=DONE" );
                            $report->status_code = SCART_STATUS_REPORT_DONE;
                            $report->status_at = date('Y-m-d H:i:s');
                            $report->number_of_records = (count ($data) > 0) ? count ($data) - 1 : 0;
                            $report->save();

                            // remove checksum
                            scartExport::delExportJob($report);

                            // check if anoymous and sent email

                            if (Systemconfig::get('abuseio.scart::scheduler.createreports.anonymous',false)) {

                                if ($report->anonymous && $report->sent_to_email) {

                                    $params = [
                                        'reportname' => $report->title,
                                        'status' => $report->status_code,
                                        'status_at' => $report->status_at,
                                        'number' => $report->number_of_records,
                                    ];
                                    scartLog::logLine("D-{$logname}; send report to email '$report->sent_to_email'");
                                    scartMail::sendMail($report->sent_to_email,'abuseio.scart::mail.scheduler_sendreport',$params,'',$tmpfile);

                                }

                            }

                            if (Systemconfig::get('abuseio.scart::scheduler.createreports.sendpolice',false)) {

                                if ($report->sendpolice && $report->sent_to_email_police) {

                                    $params = [
                                        'reportname' => $report->title,
                                        'status' => $report->status_code,
                                        'status_at' => $report->status_at,
                                        'number' => $report->number_of_records,
                                    ];
                                    scartLog::logLine("D-{$logname}; send report to POLICE email '$report->sent_to_email_police'");
                                    scartMail::sendMail($report->sent_to_email_police,'abuseio.scart::mail.scheduler_sendreport',$params,'',$tmpfile);

                                }

                            }

                            // mail
                            $recepient =  Systemconfig::get('abuseio.scart::scheduler.createreports.recipient','');
                            if ($recepient) {

                                // email
                                $params = [
                                    'reportname' => $report->title,
                                    'status' => $report->status_code,
                                    'status_at' => $report->status_at,
                                    'number' => $report->number_of_records,
                                    'reportlink' => url('backend/abuseio/scart/reports/update/'.$report->id)
                                ];
                                scartLog::logLine("D-{$logname}; send report to $recepient");
                                scartMail::sendMail($recepient,'abuseio.scart::mail.scheduler_createreport',$params);
                            } else {
                                scartLog::logLine("D-{$logname}; no recipient set, no report send");
                            }

                            if (count($data) > 0) {
                                // cleanup
                                scartLog::logLine("D-{$logname}; remove tmpfile; downloadfile getLocalPath is:" .$report->downloadfile->getLocalPath() );
                                if (file_exists($tmpfile)) unlink($tmpfile);
                            }

                        } else {

                            $adminreport[] = "Already processing report (title); $report->title (checksum=" . scartExport::getExportChecksum($report).') ';
                            scartLog::logLine("W-{$logname}; Already processing report (title) '$report->title' ");

                        }

                    }  catch (\Exception $err) {

                        scartLog::logLine("E-{$logname}; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

                        // remove checksum
                        scartExport::delExportJob($report);

                        // set FAILED
                        $report->status_code = SCART_STATUS_REPORT_FAILED;
                        $report->save();

                    }

                }

                if (count($adminreport) > 0) {

                    // inform admin
                    $params = [
                        'reportname' => $logname.'; found already processed report(s)',
                        'report_lines' => $adminreport,
                    ];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

                }

            }

        }

        SELF::endScheduler();

    }


    static private $_anonymousColumns = [
        'url','url_host','url_base','url_referer','url_ip','url_hash',
    ];
    static private $_defaultColumns = [
        'hoster_contact',
        'hoster_country',
        'hoster_owner',
        'hoster_first_ntd_at',
        'registrar_contact',
        'grade_code',
        'number_of_ntd',
        'delivered_items',
    ];

    public static function getAnonymousColumns() {
        return self::$_anonymousColumns;
    }

    /*
     * Export url (report) data
     *
     *
     */

    static function exportUrls($logname,$tmpfile,$report) {

        $data = [];

        if ($report->archivedatabase && scartArchive::isActiveValid()) {
            scartArchive::setArchiveDefault();
        } else {
            scartArchive::resetArchiveDefault();
        }

        $exportrecords = scartExport::exportFiltered($report->filter_grade,$report->filter_status,$report->filter_country,$report->filter_start,$report->filter_end,$logname);

        if ($exportrecords) {

            scartLog::logLine("D-{$logname}; recordcount=" . count($exportrecords) );

            $userdefinedColumns = ($report->export_columns);
            if ($userdefinedColumns) {
                //scartLog::logLine(print_r($report->export_columns,true));
                $columns = [];
                foreach ($report->export_columns AS $export_column) {
                    $columns[] = $export_column['column'];
                }
            } else {
                $columns = array_values((new Report())->getColumnDefaultOptions());

            }
            //scartLog::logDump("D-Report columns",$columns);

            if (scartICCAMinterface::isActive()) {
                // add source hotline country
                $columns = array_merge($columns, [
                        'iccam_hotline_country',]
                );
            }

            // if send to police email then NO defaults
            $sendToPolice = ($report->sendpolice && $report->sent_to_email_police);
            if (!$sendToPolice) {
                // defaults
                $columns = array_merge($columns, self::$_defaultColumns);
            }

            // Check if anonymous
            if ($report->anonymous) {
                // remove anonymous columns
                $columns = array_diff($columns,self::$_anonymousColumns);
                // reindex
                $columns = array_values($columns);
            }

            $headerrow = implode(SCART_EXPORT_CSV_DELIMIT,$columns);

            $grademeta = ['labels' => []];
            if (!$sendToPolice && (scartExport::inFilter($report->filter_grade,SCART_GRADE_ILLEGAL) || scartExport::inFilter($report->filter_grade,SCART_GRADE_NOT_ILLEGAL) )) {
                $grademeta = Grade_question::getGradeHeaders($report->filter_grade);
            }

            if (count($grademeta['labels']) > 0) {
                $columns = array_merge($columns, array_keys($grademeta['labels']));
                $headerrow .= ';' . implode(SCART_EXPORT_CSV_DELIMIT,array_values($grademeta['labels']));;
            }

            $data[] = $headerrow;

            scartLog::logLine("D-{$logname}; Start foreach ;headerrow=$headerrow " );

            // POLICE contact
            $policecontact = Abusecontact::where('police_contact',true)->first();
            // LEA contact(s)
            $leacontacts = Abusecontact::where('lea_contact',true)->get()->pluck('id')->toArray();

            foreach ($exportrecords AS $record) {

                $record->addVisible($columns);

                // police
                if ($policecontact) {
                    $cnt = Ntd_url::where('record_type',SCART_INPUT_TYPE)
                        ->where('record_id',$record->id)
                        ->join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
                        ->where(SCART_NTD_TABLE.'.abusecontact_id',$policecontact->id)
                        ->count();
                    $police = ($cnt > 0) ? 'y' : 'n';
                } else {
                    $police = 'n';
                }
                $record->police = $police;

                // LEA
                if (!empty($leacontacts)) {
                    $exists = Ntd_url::where('record_type',SCART_INPUT_TYPE)
                        ->where('record_id',$record->id)
                        ->join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
                        ->whereIn(SCART_NTD_TABLE.'.abusecontact_id',$leacontacts)
                        ->exists();
                    $lea = ($exists) ? 'y' : 'n';
                } else {
                    $lea = 'n';
                }
                $record->lea = $lea;

                // Number of NTD's
                $record->number_of_ntd = Ntd_url::where('record_type',SCART_INPUT_TYPE)
                    ->where('record_id',$record->id)
                    ->join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
                    ->whereIn(SCART_NTD_TABLE.'.status_code',[SCART_NTD_STATUS_SENT_SUCCES,SCART_NTD_STATUS_SENT_API_SUCCES])
                    ->count();

                // NTD first send
//                $first_ntd = Ntd::whereIn('status_code',[SCART_NTD_STATUS_SENT_SUCCES,SCART_NTD_STATUS_SENT_API_SUCCES])
//                    ->join(SCART_NTD_URL_TABLE,SCART_NTD_URL_TABLE.'.ntd_id','=',SCART_NTD_TABLE.'.id')
//                    ->where(SCART_NTD_URL_TABLE.'.record_type',SCART_INPUT_TYPE)
//                    ->where(SCART_NTD_URL_TABLE.'.record_id',$record->id)
//                    ->orderBy('status_time','ASC')
//                    ->first();
                $firstntd = Ntd_url::where('record_type',SCART_INPUT_TYPE)
                    ->where('record_id',$record->id)
                    ->join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
                    ->whereIn(SCART_NTD_TABLE.'.status_code',[SCART_NTD_STATUS_SENT_SUCCES,SCART_NTD_STATUS_SENT_API_SUCCES])
                    ->orderBy(SCART_NTD_TABLE.'.status_time','ASC')
                    ->first();
                if ($firstntd) {
                    $record->hoster_first_ntd_at = $firstntd->status_time;
                }

                // NTD last send
                $lastntd = Ntd_url::where('record_type',SCART_INPUT_TYPE)
                    ->where('record_id',$record->id)
                    ->join(SCART_NTD_TABLE,SCART_NTD_TABLE.'.id','=',SCART_NTD_URL_TABLE.'.ntd_id')
                    ->whereIn(SCART_NTD_TABLE.'.status_code',[SCART_NTD_STATUS_SENT_SUCCES,SCART_NTD_STATUS_SENT_API_SUCCES])
                    ->orderBy(SCART_NTD_TABLE.'.status_time','DESC')
                    ->first();
                $record->ntd = ($lastntd)?'y':'n';
                $record->ntd_at = ($lastntd) ? $lastntd->status_time : '';

                // no url record
                $record->noUrl = (ImportWebform::isGeneratedUrl($record->url)) ? 'y' : 'n';

                // iccam country
                if (scartICCAMinterface::isActive()) {
                    // SCART_INPUT_EXTRAFIELD_ICCAM
                    $iccam_hotlineid = $record->getExtrafieldValue(SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_HOTLINEID);
                    if ($iccam_hotlineid) {
                        $country = Iccam_hotline::where('hotlineid',$iccam_hotlineid)->first();
                        if ($country) {
                            $record->iccam_hotline_country = $country->country;
                        } else {
                            $record->iccam_hotline_country = '?';
                        }
                    }
                    //scartLog::logLine("D-hotlineid=$iccam_hotlineid, country=$record->iccam_hotline_country");
                } else {
                    $record->iccam_hotline_country = '';
                }

                // fill related
                $abusecontact = Abusecontact::find($record->host_abusecontact_id);
                if ($abusecontact) {
                    $record->hoster_contact = $abusecontact->abusecustom;
                    $record->hoster_country = $abusecontact->abusecountry;
                    $record->hoster_owner = $abusecontact->owner;
                }
                if (!empty($record->registrar_abusecontact_id)) {
                    $registrar_contact = Abusecontact::find($record->registrar_abusecontact_id);
                    $record->registrar_contact = ($registrar_contact) ? $registrar_contact->owner: '';
                }

                // if graded then fill grading answers
                if (count($grademeta['labels']) > 0) {
                    foreach ($grademeta['types'] AS $id => $type) {
                        //$value = Grade_answer::where('record_type', $record_type)->where('record_id', $record->id)->where('grade_question_id', $id)->first();
                        $value = Grade_answer::where('record_id', $record->id)->where('grade_question_id', $id)->first();
                        $values = ($value) ? unserialize($value->answer) : '';
                        // $showvvalues = (is_array($values)) ? implode('-', $values) : $values; scartLog::logLine("D-id=$id, type=" . $type  . ", values=" . $showvvalues);
                        if ($type == 'select' || $type == 'checkbox') {
                            if ($values == '') $values = [];
                            foreach ($grademeta['values'][$id] AS $optval => $optlab) {
                                $fld = 'grade_'.$id.'_'.$optval;
                                $record->$fld = (in_array($optval, $values) ? 'y' : 'n');
                            }
                        } elseif ($type == 'radio') {
                            $fld = 'grade_'.$id;
                            if ($values != '' && is_array($values)) $values = implode('', $values);
                            $record->$fld = (isset($grademeta['values'][$id][$values])) ? $grademeta['values'][$id][$values] : '';
                        } elseif ($type == 'text') {
                            $fld = 'grade_'.$id;
                            if ($values != '' && is_array($values)) $values = implode('', $values);
                            $record->$fld = $values;
                        }
                        //scartLog::logLine("D-record->$fld=" . $record->$fld);
                    }
                }

                $row = '';
                foreach ($columns AS $column) {
                    if (substr($column,0,6) == 'extra_') {

                        $label = substr($column,6);
                        $extra = Input_extrafield::where('input_id',$record->id)->where('label',$label)->first();
                        if ($row!='') $row .= SCART_EXPORT_CSV_DELIMIT;
                        $columnvalue = (($extra)? $extra->value : '');
                        // convert dubble quotes
                        $columnvalue = str_replace('"','""',$columnvalue);
                        // we put " before and " at the end so Excel will ignore CRLF and these can be restored within Excel itself
                        $row .= '"'.$columnvalue.'"';

//                    } elseif (($record->noUrl!='y') || !in_array($column,self::$_defaultColumns)) {
//                    } elseif (!in_array($column,self::$_defaultColumns)) {
                    } else {
                        // when self generated url (=text only report) then skip default columns (hoster)
                        if ($row!='') $row .= SCART_EXPORT_CSV_DELIMIT;
                        $columnvalue = (isset($record->$column)? $record->$column : '');
                        // convert dubble quotes
                        $columnvalue = str_replace('"','""',$columnvalue);
                        // we put " before and " at the end so Excel will ignore CRLF and these can be restored within Excel itself
                        $row .= '"'.$columnvalue.'"';
                    }
                }
                $data[] = $row;

                if (count($data) % 500 == 0) {
                    scartLog::logLine("D-{$logname}; data process count: " . count($data));
                }

            }

            scartLog::logLine("D-{$logname}; data recordcount=" . count($data) . ", save in: $tmpfile");
            file_put_contents($tmpfile, implode("\n", $data) );
            $report->downloadfile = $tmpfile;

        } else {

            scartLog::logLine("D-{$logname}; no records found" );

        }

        if ($report->archivedatabase) {
            scartArchive::resetArchiveDefault();
        }

        return $data;
    }

    /*
     * Export AI attributes
     *
     * Export also correction
     *
     * Note: skip attribute 'Naam_afbeelding'
     *
     */

    static function exportAttributes($logname,$tmpfile,$report) {

        $data = [];

        $exportrecords = scartExport::exportFiltered($report->filter_grade,$report->filter_status,$report->filter_country,$report->filter_start,$report->filter_end,$logname);

        if ($exportrecords) {

            scartLog::logLine("D-{$logname}; recordcount=" . count($exportrecords) );

            $columns = [
                'filenumber',
                'reference',
                'url_type',
                'received_at',
                'type_code',
                'source_code',
                'status_code',
                'grade_code',
            ];

            $extracolumns = [];
            $extrafields = Input_extrafield::where('type',SCART_INPUT_EXTRAFIELD_PWCAI)->select('label')->distinct()->get();
            foreach ($extrafields as $extrafield) {
                if ($extrafield->label != SCART_INPUT_EXTRAFIELD_PWCAI_naamafbeelding) {
                    $extracolumns[] = $extrafield->label;
                    $extracolumns[] = $extrafield->label . ' (correction)';
                }
            }

            $headerrow = implode(SCART_EXPORT_CSV_DELIMIT,array_merge($columns,$extracolumns));

            $data[] = $headerrow;

            foreach ($exportrecords AS $record) {

                $record->addVisible($columns);
                $row = '';
                foreach ($columns AS $column) {
                    if ($row!='') $row .= SCART_EXPORT_CSV_DELIMIT;
                    $row .= (isset($record->$column)? $record->$column : '');
                }
                $extrafields = Input_extrafield::where('input_id',$record->id)->where('type',SCART_INPUT_EXTRAFIELD_PWCAI)->get();
                foreach ($extrafields as $extrafield) {
                    if ($extrafield->label != SCART_INPUT_EXTRAFIELD_PWCAI_naamafbeelding) {
                        if ($row!='') $row .= SCART_EXPORT_CSV_DELIMIT;
                        $row .= $extrafield->value;
                        if ($row!='') $row .= SCART_EXPORT_CSV_DELIMIT;
                        $row .= $extrafield->secondvalue;

                    }
                }
                $data[] = $row;

                if (count($data) % 500 == 0) {
                    scartLog::logLine("D-{$logname}; data process count: " . count($data));
                }

            }

            scartLog::logLine("D-{$logname}; data recordcount=" . count($data) . ", save in: $tmpfile");
            file_put_contents($tmpfile, implode("\n", $data) );
            $report->downloadfile = $tmpfile;

        } else {

            scartLog::logLine("D-{$logname}; no records found" );

        }

        return $data;
    }

}
