<?php
namespace abuseio\scart\classes\scheduler;

use abuseio\scart\classes\iccam\scartICCAMmapping;
use abuseio\scart\Controllers\Startpage;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Iccam_hotline;
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

                // give ourself memory and time!
                // @TOD-DO; config setting for reports
                $memory_min = scartScheduler::setMinMemory('8G');
                set_time_limit(0);

                // POLICE contact
                $policecontact = Abusecontact::where('police_contact','<>',0)->first();

                foreach ($reports AS $report) {

                    try {

                        // check checksum (if not already active) (is possible with long running reports)
                        if (scartExport::addExportJob($report)) {

                            scartLog::logLine("D-{$logname}; (memory=$memory_min); create report '$report->title'; start=$report->filter_start, end=$report->filter_end");

                            $report->status_code = SCART_STATUS_REPORT_WORKING;
                            $report->status_at = date('Y-m-d H:i:s');
                            $report->save();

                            $exportrecords = scartExport::exportFiltered($report->filter_grade,$report->filter_status,$report->filter_country,$report->filter_start,$report->filter_end);

                            if ($exportrecords) {

                                scartLog::logLine("D-{$logname}; recordcount=" . count($exportrecords) );

                                if ($report->export_columns) {
                                    //scartLog::logLine(print_r($report->export_columns,true));
                                    $columns = [];
                                    foreach ($report->export_columns AS $export_column) {
                                        $columns[] = $export_column['column'];
                                    }
                                } else {
                                    $columns = array_values((new Report())->getColumnDefaultOptions());

                                }
                                //scartLog::logLine(print_r($columns,true));

                                if (scartICCAMmapping::isActive()) {
                                    // add source hotline country
                                    $columns = array_merge($columns, [
                                            'iccam_hotline_country',]
                                    );
                                }

                                $columns = array_merge($columns, [
                                    'hoster_contact',
                                    'hoster_country',
                                    'hoster_owner',
                                    'hoster_first_ntd_at',
                                    'registrar_contact',
                                    'grade_code',]
                                );

                                $headerrow = implode(SCART_EXPORT_CSV_DELIMIT,$columns);

                                if (scartExport::inFilter($report->filter_grade,SCART_GRADE_ILLEGAL) || scartExport::inFilter($report->filter_grade,SCART_GRADE_NOT_ILLEGAL) ) {
                                    $grademeta = Grade_question::getGradeHeaders($report->filter_grade);
                                } else {
                                    $grademeta = [];
                                    $grademeta['labels'] = [];
                                }

                                if (count($grademeta['labels']) > 0) {
                                    $columns = array_merge($columns, array_keys($grademeta['labels']));
                                    $headerrow .= ';' . implode(SCART_EXPORT_CSV_DELIMIT,array_values($grademeta['labels']));;
                                }

                                $data = [];
                                $data[] = $headerrow;

                                $filter_grade = $report->filter_grade;

                                scartLog::logLine("D-{$logname}; Start foreach ;headerrow=$headerrow " );

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

                                    // iccam country
                                    if (scartICCAMmapping::isActive()) {
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

                                        /**
                                         * Get NTD's (sent_succes time) where record (url) was included
                                         * Take first for first-NTD-time
                                         */
                                        $first_ntd = Ntd::where('status_code',SCART_NTD_STATUS_SENT_SUCCES)
                                            ->where('abusecontact_id',$abusecontact->id)
                                            ->join(SCART_NTD_URL_TABLE,SCART_NTD_URL_TABLE.'.ntd_id','=',SCART_NTD_TABLE.'.id')
                                            ->where(SCART_NTD_URL_TABLE.'.record_type',SCART_INPUT_TYPE)
                                            ->where(SCART_NTD_URL_TABLE.'.record_id',$record->id)
                                            ->orderBy('status_time','ASC')
                                            ->first();
                                        if ($first_ntd) {
                                            $record->hoster_first_ntd_at = $first_ntd->status_time;
                                        }

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
                                                if ($values != '') $values = implode('', $values);
                                                $record->$fld = (isset($grademeta['values'][$id][$values])) ? $grademeta['values'][$id][$values] : '';
                                            } elseif ($type == 'text') {
                                                $fld = 'grade_'.$id;
                                                if ($values != '') $values = implode('', $values);
                                                $record->$fld = $values;
                                            }
                                            //scartLog::logLine("D-record->$fld=" . $record->$fld);
                                        }
                                    }

                                    $row = '';
                                    foreach ($columns AS $column) {
                                        if ($row!='') $row .= SCART_EXPORT_CSV_DELIMIT;
                                        $row .= (isset($record->$column)? $record->$column : '');
                                    }
                                    $data[] = $row;

                                    if (count($data) % 500 == 0) {
                                        scartLog::logLine("D-{$logname}; data process count: " . count($data));
                                    }


                                }

                                $tmpfile = temp_path() . '/export-' . date('YmdHis') . '.csv';
                                scartLog::logLine("D-{$logname}; data recordcount=" . count($data) . ", save in: $tmpfile");
                                file_put_contents($tmpfile, implode("\n", $data) );
                                $report->downloadfile = $tmpfile;

                            } else {

                                scartLog::logLine("D-{$logname}; no records found" );

                            }

                            scartLog::logLine("D-{$logname}; status_code=DONE" );
                            $report->status_code = SCART_STATUS_REPORT_DONE;
                            $report->status_at = date('Y-m-d H:i:s');
                            $report->number_of_records = count ($exportrecords);
                            $report->save();

                            // remove checksum
                            scartExport::delExportJob($report);

                            scartLog::logLine("D-{$logname}; remove tmpfile; downloadfile getLocalPath is:" .$report->downloadfile->getLocalPath() );
                            unlink($tmpfile);

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

                        } else {

                            //
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

}
