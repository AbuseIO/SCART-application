<?php  namespace abuseio\scart\classes\mail;

use abuseio\scart\Models\Maintenance;
use abuseio\scart\Models\Whitelist;
use Config;
use League\Flysystem\Exception;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\ImportExport_job;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_source;
use abuseio\scart\models\Input_status;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\classes\iccam\scartICCAMinterface;

class scartImportMailbox {

    public static function isActive() {
        // host must be set
        $mode =  Systemconfig::get('abuseio.scart::scheduler.importexport.readmailbox.mode','');
        // on this  moment imap and m356 is supported
        return in_array($mode,['imap','m356']);
    }

    public static function importMailbox() {

        try {

            if (self::isActive()) {

                $reports = self::readImportMailbox();

                if (count($reports) > 0) {

                    // report JOB

                    $params = ['reports' => $reports];
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO,'abuseio.scart::mail.scheduler_import_mailbox', $params);
                    foreach ($reports AS $report) {
                        // report to sender (from)
                        $params = ['report' => $report];
                        if ($to = scartReadMail::parseRfc822($report['from'])) {
                            scartLog::logLine("D-importMailbox; send reply to: $to");
                            scartMail::sendMail($to, 'abuseio.scart::mail.scheduler_import_mailbox_reply', $params);
                        } else {
                            scartLog::logLine('D-importMailbox; cannot reply; no valid FROM address found in: ' . $report['from']);
                        }
                    }

                }

            }

        } catch (\Exception $err) {

            scartLog::logLine("E-importMailbox exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

    }

    public static function processBodylines($msg,$source,$status_code) {

        $loglines = []; $cnt = 0;
        $bodylines = $msg->getBodyLines();

        foreach ($bodylines AS $bodyline) {

            $reportline = '';

            // cleanup body line
            $bodyline = trim($bodyline);

            scartLog::logLine("D-processBodylines; bodyline='$bodyline'");
            if ($bodyline != '') {

                $bodyline = $bodyline . ';';

                $arrline = explode(';',$bodyline);
                if (count($arrline) >= 1) {

                    $url = $arrline[0];

                    if (filter_var($url, FILTER_VALIDATE_URL)) {

                        if (Input::where('url',$url)->count() == 0) {

                            $referer = (count($arrline) >= 2) ? $arrline[1] : '';
                            if ($referer) {
                                if (!filter_var($referer, FILTER_VALIDATE_URL)) {
                                    $reportline = "failed: referer '$referer' not a valid url; line=$bodyline";
                                    $url = '';
                                }
                            }
                            if (count($arrline) >= 3) {
                                $arrnote = array_splice($arrline,2);
                                $note = implode(';' , $arrnote);
                                $note = (trim($note)!=';') ? $note : '';
                            } else {
                                $note = '';
                            }

                            if ($url) {

                                $reportline = "import url '$url' success";

                                scartLog::logLine("D-processBodylines got url '$url', referer '$referer'; generate INPUT record");

                                try {

                                    $input = new Input();
                                    $input->url = $url;
                                    $input->url_type = SCART_URL_TYPE_MAINURL;
                                    $input->url_referer = $referer;
                                    $input->note = $note;
                                    $input->status_code = $status_code;
                                    $input->workuser_id = 0;
                                    $input->type_code = SCART_MAILBOX_IMPORT_TYPE_CODE_WEBSITE;
                                    $input->source_code = $source;

                                    $input->received_at = date('Y-m-d H:i:s');

                                    $input->save();

                                    $input->generateFilenumber();
                                    $input->save();

                                    // log old/new for history
                                    $input->logHistory(SCART_INPUT_HISTORY_STATUS,'',$status_code,"Import by email import");

                                    $input->logText("Added by mailbox import");

                                } catch (\Exception $err) {

                                    scartLog::logLine("E-processBodylines exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
                                    $reportline = "error processing '$url'; message=".$err->getMessage();

                                }

                            }

                        } else {
                            $reportline = "double: url '$url' already in database";
                        }

                        $cnt += 1;

                    } else {
                        // skip
                        $reportline = "failed: url '$url' not valid; line=$bodyline";
                    }

                }

            }

            if ($reportline) {
                $reportline = "[line $cnt] $reportline";
                scartLog::logLine("D-process import line; $reportline");
                $loglines[] = $reportline;
            }

        }

        return $loglines;
    }


    public static function processWEBSITEinput($msg) {

        $import_mail_direct_Scrape = Systemconfig::get('abuseio.scart::options.import_mail_direct_Scrape',false);
        $newstatus = (($import_mail_direct_Scrape)? SCART_STATUS_SCHEDULER_SCRAPE : SCART_STATUS_OPEN);
        scartLog::logLine("D-readImportMailbox; processWEBSITEinput; new status code will be: $newstatus");

        $loglines = self::processBodylines($msg,SCART_MAILBOX_IMPORT_SOURCE_CODE_WEBFORM,$newstatus);

        return $loglines;
    }

    public static function processHotlineInput($msg) {

        scartLog::logLine("D-readImportMailbox; processHotlineInput");

        $loglines = self::processBodylines($msg,SCART_MAILBOX_IMPORT_SOURCE_CODE_HOTLINE,SCART_STATUS_SCHEDULER_SCRAPE);

        return $loglines;
    }

    public static function processInputSource($msg) {

        scartLog::logLine("D-readImportMailbox; processInputSource");

        // get source
        $source = trim(substr($msg->getSubject(), strlen(SCART_MAILBOX_IMPORT_INPUT_SOURCE)));

        if ($source) {

            // check if exists; if not, add
            $rec = Input_source::where('code',$source)->first();
            if (!$rec) {
                $maxsortnr = Input_source::max('sortnr');
                $rec = new Input_source();
                $rec->sortnr = ($maxsortnr) ? ($maxsortnr + 1) : 1;
                $rec->lang = 'en';
                $rec->code = str_replace(' ','_',$source);
                $rec->title = $source;
                $rec->description = $source;
                $rec->save();
                $reportline = "Import email with source '$source' - added to source table";
            } else {
                $reportline = "Import email with source '$source' - use existing ";
            }
            scartLog::logLine("D-processInputSource; $reportline");
            $loglines[] = $reportline;

            $loglines = array_merge($loglines,self::processBodylines($msg,$source,SCART_STATUS_OPEN));

        } else {

            $loglines[] = "Import email with subject '{$msg->getSubject()}' - cannot find SOURCE ";

        }

        return $loglines;
    }



    public static function processICCAMsetActionClose($msg,$actionID) {

        $loglines = []; $cnt = 0;
        $bodylines = $msg->getBodyLines();
        foreach ($bodylines AS $bodyline) {

            $reportline = '';

            //scartLog::logLine("D-analyse '$bodyline'");
            if (trim($bodyline)!='') {

                $bodyline = trim($bodyline) . ';';

                $arrline = explode(';',$bodyline);
                if (count($arrline) >= 1) {

                    $url = trim($arrline[0]);

                    if (filter_var($url, FILTER_VALIDATE_URL)) {

                        // 2020/7/16/gs; possible more then 1
                        $inputs = Input::where('url',$url)->get();
                        if (count($inputs) > 0) {

                            foreach ($inputs AS $input) {

                                if ($input->status_code != SCART_STATUS_CLOSE_OFFLINE && $input->status_code != SCART_STATUS_CLOSE_OFFLINE_MANUAL) {

                                    $reportline = "Close (offline) url '$url'; filenumber=$input->filenumber; status was '$input->status_code'";

                                    // log old/new for history
                                    $new = ($input->status_code==SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL) ? SCART_STATUS_CLOSE_OFFLINE_MANUAL : SCART_STATUS_CLOSE_OFFLINE;
                                    $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,$new,"Close offline by email import");
                                    $input->status_code = $new;
                                    $input->save();
                                    $input->logText("Close offline by mailbox import");

                                    // be sure this url is removed from (any) (grouping) NTD
                                    Ntd::removeUrlgrouping($input->url);
                                    $input->logText("Removed from any (grouping) NTD's");

                                    if (scartICCAMinterface::isActive() && scartICCAMinterface::hasICCAMreportID($input->reference)) {

                                        // ICCAM content removed
                                        scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                            'record_type' => class_basename($input),
                                            'record_id' => $input->id,
                                            'object_id' => $input->reference,
                                            'action_id' => $actionID,
                                            'country' => '',                                // hotline default
                                            'reason' => 'SCART closed offline',
                                        ]);

                                        $reportline .= "; ICCAM action $actionID set for ICCAM reference '$input->reference'";

                                    }

                                } else {

                                    $reportline = "url '$url'; filenumber=$input->filenumber; already set on CLOSE_OFFLINE ";

                                }

                            }

                        } else {

                            $reportline = "Can not find url '$url' ";

                        }

                        $cnt += 1;

                    } else {
                        // skip
                        $reportline = "failed: url not valid; line=$bodyline";
                    }

                }

            }

            if ($reportline) {
                $reportline = "[line $cnt] $reportline";
                scartLog::logLine("D-processICCAMsetActionClose; $reportline");
                $loglines[] = $reportline;
            }

        }

        return $loglines;
    }

    public static function processERTreadICCAM($msg) {

        $loglines = []; $cnt = 0;
        $bodylines = $msg->getBodyLines();
        foreach ($bodylines AS $bodyline) {

            $reportline = '';

            //scartLog::logLine("D-processERTreadICCAM; analyse '$bodyline'");
            if (trim($bodyline)!='') {

                $bodyline = trim($bodyline) . ';';

                $arrline = explode(';',$bodyline);
                if (count($arrline) >= 1) {

                    $reportID = trim($arrline[0]);

                    if ($reportID) {

                        $status_timestamp = '[' . date('Y-m-d H:i:s') . '] ';

                        // check if not already in SCART
                        if (!scartICCAMmapping::alreadyImportedICCAMreportID($reportID)) {

                            $txt = scartICCAMmapping::alreadyActionsSetICCAMreportID($reportID);
                            if ($txt == '') {

                                $iccamreport = scartICCAMmapping::readICCAMreportID($reportID);

                                if ($iccamreport) {

                                    $note = (!empty($iccamreport->Memo)) ? $iccamreport->Memo : '';

                                    // check if siteType is set -> convert to SCART siteType value
                                    $siteTypeId = (isset($iccamreport->SiteTypeID)) ? $iccamreport->SiteTypeID : 1;
                                    $type_code = scartICCAMinterface::getSiteType($siteTypeId);

                                    $first = true;
                                    foreach ($iccamreport->Items as $item) {

                                        scartLog::logLine("D-processERTreadICCAM; found url '$item->Url' to import ");

                                        if (Input::where('url', $item->Url)->count() == 0) {

                                            try {

                                                $input = new Input();
                                                $input->url = $item->Url;
                                                $input->url_type = SCART_URL_TYPE_MAINURL;

                                                // only the first get reportID -> import other items with reference = empty
                                                // -> then this will lead to an new export to ICCAM with new reportID

                                                if ($first) {
                                                    $input->note = $note;
                                                    $input->reference = scartICCAMinterface::setICCAMreportID($reportID);
                                                    $first = false;  // reset
                                                } else {
                                                    $input->note = (!empty($item->Memo)) ? $item->Memo : '';
                                                    $input->reference = '';
                                                }

                                                //$input->url_referer = '';

                                                $input->status_code = SCART_STATUS_SCHEDULER_SCRAPE;
                                                $input->workuser_id = 0;
                                                $input->type_code = $type_code;
                                                $input->source_code = SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM;

                                                $input->received_at = date('Y-m-d H:i:s');

                                                $input->save();

                                                $input->generateFilenumber();
                                                $input->save();

                                                // log old/new for history
                                                $input->logHistory(SCART_INPUT_HISTORY_STATUS,'',SCART_STATUS_SCHEDULER_SCRAPE,"Read from ICCAM based on email import");

                                                $input->logText("Added url '$item->Url' (reference is reportID=$reportID) by ICCAM import");

                                                $reportline = $status_timestamp . "import url '$item->Url' (filenumber=$input->filenumber); type=$type_code (id=$siteTypeId)";


                                            } catch (\Exception $err) {

                                                $reportline = $status_timestamp . "error inserting into SCART - SKIP - error message: " . $err->getMessage();
                                                scartLog::logLine("W-processERTreadICCAM; error inserting into SCART, SKIP this one; message: " . $err->getMessage());

                                            }

                                        } else {
                                            $reportline = $status_timestamp . "import ReportID '$reportID' already imported - SKIP";
                                            //scartLog::logLine("D-processERTreadICCAM; import ReportID '$reportID' already imported into ERT");
                                        }

                                    }

                                } else {
                                    $reportline = $status_timestamp . "cannot import reportID '$reportID' from ICCAM";
                                    //scartLog::logLine("D-processERTreadICCAM; cannot import reportID '$reportID' from ICCAM");
                                }


                            } else {

                                $reportline = $status_timestamp . "import reportID '$reportID' has already ACTIONS set in ICCAM ($txt) - SKIP";
                                //scartLog::logLine("D-processERTreadICCAM; reportID '$reportID' has already ACTIONS ($txt) set in ICCAM");

                            }

                        } else {
                            $reportline = $status_timestamp . "reportID=$reportID; already imported into ERT";
                            //scartLog::logLine("D-processERTreadICCAM; reportID=$reportID; already imported into ERT");
                        }

                        $cnt += 1;

                    }
                }
            }

            if ($reportline) {
                $reportline = "[line $cnt] $reportline";
                scartLog::logLine("D-processERTreadICCAM; $reportline");
                $loglines[] = $reportline;
            }

        }

        return $loglines;
    }

    public static function processSetMaintenance($msg) {

        $bodylines = $msg->getBodyLines();
        scartLog::logDump("D-body=",$bodylines);

        $fields = ['module','start','end','note'];
        $fieldnr = 0;
        $fieldtxt = '';
        $maintenance = new Maintenance();
        foreach ($bodylines as $bodyline) {
            if (trim($bodyline) != '') {
                $bodyline = str_replace("\r",'',$bodyline);
                $field = $fields[$fieldnr++];
                $maintenance->$field = $bodyline;
                $fieldtxt .= ($fieldtxt ? ', ':'') . "$field='$bodyline'";
                if ($fieldnr == count($fields)) {
                    break;
                }
            }
        }
        if ($fieldnr == count($fields))  {
            $maintenance->save();
            $logline = "Insert maintenance record; $fieldtxt";
            scartLog::logLine("D-$logline");
        } else {
            $logline = 'Unknown maintenance body contents: '.implode("\n",$bodylines);
        }
        return [$logline];
    }

    public static function readImportMailbox() {

        $reports = [];

        scartReadMail::init();

        // note; read max 10 messages in one time
        $messages = scartReadMail::getInboxMessages();
        if ($messages) {

            $adminreport = [];

            scartLog::logLine("D-readImportMailbox; process " . count($messages) . ' messages');

            foreach($messages as $msg) {

                $delmsg = true;

                $from = $msg->getFrom();

                // check if the adress is added to the whitelist
                scartLog::logLine("D-readImportMailbox; check whitelist of '$from'");
                if (!Whitelist::emailIsWhitelisted($from)) {
                    scartLog::logLine("W-readImportMailbox; NOT IN whitelist: from='$from'");
                    //scartAlerts::insertAlert(SCART_ALERT_LEVEL_WARNING, 'abuseio.scart::mail.whitelist_notfound_abusecontact', [
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN, 'abuseio.scart::mail.whitelist_notfound_abusecontact', [
                        'from'      => $from,
                        'subject'   => $msg->getSubject(),
                        'uid'       => $msg->getId(),
                        'date'      => $msg->getDate(),
                    ]);

                    // skip processing

                } else {

                    $report = "message '{$msg->getSubject()}' (uid={$msg->getId()} from '{$msg->getFrom()}' arrived at '{$msg->getDate()}', with {$msg->getBodyLinesCount()} body lines";
                    scartLog::logLine("D-readImportMailbox; $report");

                    /**
                     * checksum check -> some messages takes such a time (hours), the IMAP interface is gone so the DELETE is not working
                     * the message is then looping
                     * use abuseio_scart_importexport_job
                     *
                     */

                    if (SELF::addImportmail($msg)) {

                        $loglines = [];

                        // check if correct SUBJECT

                        if (strpos($msg->getSubject(), SCART_MAILBOX_IMPORT_INPUT_SOURCE ) === 0) {

                            $loglines = self::processInputSource($msg);

                        } elseif (strpos($msg->getSubject(), SCART_MAILBOX_IMPORT_WEBSITE_INPUTS ) === 0) {

                            $loglines = self::processWEBSITEinput($msg);

                        } elseif (strpos($msg->getSubject(), SCART_MAILBOX_IMPORT_ICCAM_INPUTS ) === 0){

                            // OBSOLUTE
                            //$loglines = self::processERTreadICCAM($msg);
                            $loglines = ["DISABLED (OBSOLUTE) ICCAM REPORTID IMPORT OPTION"];


                        } elseif (strpos($msg->getSubject(), SCART_MAILBOX_IMPORT_HOTLINE_INPUTS ) === 0) {

                            $loglines = self::processHotlineInput($msg);

                        } elseif (strpos($msg->getSubject(), SCART_MAILBOX_IMPORT_CONTENT_REMOVED ) === 0) {

                            $loglines = self::processICCAMsetActionClose($msg,SCART_ICCAM_ACTION_CR);

                        } elseif (strpos($msg->getSubject(), SCART_MAILBOX_IMPORT_CONTENT_UNAVAILABLE ) === 0) {

                            $loglines = self::processICCAMsetActionClose($msg,SCART_ICCAM_ACTION_CU);

                        } elseif (strpos($msg->getSubject(), SCART_MAILBOX_IMPORT_SET_MAINTENANCE ) === 0) {

                            $adminreport = self::processSetMaintenance($msg);

                        } else {

                            $logline = "CANNOT process this message, WRONG subject format '{$msg->getSubject()}'";
                            scartLog::logLine("D-readImportMailbox; $logline" );
                            $loglines[] = $logline;

                        }

                        if (count($loglines) > 0) {
                            $reports[] = [
                                'from' => $msg->getFrom(),
                                'subject' => $msg->getSubject(),
                                'arrived' => $msg->getDate(),
                                'loglines' => $loglines,
                            ];
                        }

                    } else {

                        //
                        $adminreport[] = "Already processed; $report";
                        scartLog::logLine("W-readImportMailbox; ALREADY PROCESSED!?");

                        if (!self::isDelImportmail($msg)) {
                            // skip -> still working
                            $delmsg = false;
                        } else {
                            // delete again...
                            scartLog::logLine("W-readImportMailbox; message already delete; subject={$msg->getSubject()} - try again");
                        }

                    }

                }

                if ($delmsg) {
                    scartLog::logLine("D-readImportMailbox; delete processed message; subject={$msg->getSubject()} ");
                    $msg->delete();
                    SELF::delImportmail($msg);
                }

            }

            if (count($adminreport) > 0) {

                // inform admin
                $params = [
                    'reportname' => 'readImportMailbox; admin report(s)',
                    'report_lines' => $adminreport,
                ];
                scartAlerts::insertAlert(SCART_ALERT_LEVEL_ADMIN,'abuseio.scart::mail.admin_report',$params);

            }

        } else {
            scartLog::logLine("D-readImportMailbox; no messages in (".scartReadMail::getMode().") INBOX");
        }

        scartReadMail::close();

        return $reports;
    }

    static function getImportmailChecksum($msg) {
        // unique checksum each import mail
        return md5($msg->getSubject() . $msg->getFrom() . $msg->getId() );
    }

    /**
     * Add import mail checksum to ImportExport_job
     *
     * @param $action
     * @param $data
     */
    public static function addImportmail($msg) {

        // unique checksum each import mail
        $checksum = SELF::getImportmailChecksum($msg);

        // check if not already, including trash
        $cnt = ImportExport_job::where('interface',SCART_INTERFACE_IMPORTMAIL)
            ->where('action',SCART_INTERFACE_IMPORTMAIL_ACTION)
            ->where('checksum',$checksum)
            ->withTrashed()
            ->count();

        if ($cnt == 0) {

            // data for reference
            $data = [
                'subject' => $msg->getSubject(),
                'uid' => $msg->getId(),
                'from' => $msg->getFrom(),
                'arrived' => $msg->getDate(),
            ];

            $export = new ImportExport_job();
            $export->interface = SCART_INTERFACE_IMPORTMAIL;
            $export->action = SCART_INTERFACE_IMPORTMAIL_ACTION;
            $export->checksum = $checksum;
            $export->data = serialize($data);
            $export->status = SCART_IMPORTEXPORT_STATUS_IMPORTED;
            $export->save();

        }

        // return true when not found
        return ($cnt==0);
    }

    static function isDelImportmail($msg) {

        $checksum = SELF::getImportmailChecksum($msg);
        // check if deleted record
        $cnt = ImportExport_job::where('interface',SCART_INTERFACE_IMPORTMAIL)
            ->where('action',SCART_INTERFACE_IMPORTMAIL_ACTION)
            ->where('checksum',$checksum)
            ->whereNotNull('deleted_at')
            ->withTrashed()
            ->count();
        // if count not 0, then deleted record found
        return ($cnt!=0);
    }

    static function delImportmail($msg) {

        $checksum = SELF::getImportmailChecksum($msg);
        $job = ImportExport_job::where('interface',SCART_INTERFACE_IMPORTMAIL)
            ->where('action',SCART_INTERFACE_IMPORTMAIL_ACTION)
            ->where('checksum',$checksum)
            ->delete();
    }


}
