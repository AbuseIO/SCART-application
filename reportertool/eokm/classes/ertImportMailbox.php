<?php  namespace reportertool\eokm\classes;

use Config;
use League\Flysystem\Exception;
use reportertool\eokm\classes\ertLog;
use ReporterTool\EOKM\Models\Input;

class ertImportMailbox {

    public static function importMailbox() {

        try {

            // host set, then readimport config set
            $host =  Config::get('reportertool.eokm::scheduler.importExport.readmailbox.host','');
            if ($host!='') {

                $reports = self::readImportMailbox([
                    'host' =>  Config::get('reportertool.eokm::scheduler.importExport.readmailbox.host',''),
                    'port' => Config::get('reportertool.eokm::scheduler.importExport.readmailbox.port', ''),
                    'sslflag' => Config::get('reportertool.eokm::scheduler.importExport.readmailbox.sslflag', ''),
                    'username' => Config::get('reportertool.eokm::scheduler.importExport.readmailbox.username', ''),
                    'password' => Config::get('reportertool.eokm::scheduler.importExport.readmailbox.password', ''),
                ]);

                if (count($reports) > 0) {

                    // report JOB

                    $cnt = 0;

                    $params = [
                        'reports' => $reports,
                    ];
                    ertAlerts::insertAlert(ERT_ALERT_LEVEL_INFO,'reportertool.eokm::mail.scheduler_import_mailbox', $params);

                    foreach ($reports AS $report) {

                        // report to sender (from)
                        $params = [
                            'report' => $report,
                        ];
                        $to = ertIMAPmail::parseRfc822($report['from']);
                        if ($to) {
                            ertLog::logLine("D-importMailbox; send reply to: $to");
                            ertMail::sendMail($to, 'reportertool.eokm::mail.scheduler_import_mailbox_reply', $params);
                        } else {
                            ertLog::logLine('D-importMailbox; cannot reply; no valid FROM address found in: ' . $report['from']);
                        }

                        $cnt += 1;

                    }

                }

            }

        } catch (\Exception $err) {

            ertLog::logLine("E-importMailbox exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());

        }

    }

    public static function readImportMailbox($config) {

        $reports = [];

        ertIMAPmail::setConfig($config);

        $messages = ertIMAPmail::imapGetInboxMessages();
        if ($messages) {

            foreach($messages as $msg) {

                $delmsg = true;

                // get subject and body
                $msg->subject = (isset($msg->subject)) ? $msg->subject : '';
                $msg->body = ertIMAPmail::imapGetMessageBody($msg->msgno);

                // log
                $report = "Process message '$msg->subject' from '$msg->from' arrived at '".date('Y-m-d H:i:s',$msg->udate)."' ";
                ertLog::logLine("D-$report");
                $report .= CRLF_NEWLINE;
                $loglines = [];
                $cnt = 0;

                // check if correct SUBJECT
                if (strpos($msg->subject, ERT_MAILBOX_IMPORT_SUBJECT ) === 0) {

                    // get (optional) reference
                    $ref = trim(substr($msg->subject, strlen(ERT_MAILBOX_IMPORT_SUBJECT)));

                    $bodylines = explode("\n", $msg->body);
                    foreach ($bodylines AS $bodyline) {

                        $reportline = '';

                        //ertLog::logLine("D-analyse '$bodyline'");
                        if (trim($bodyline)!='') {

                            $bodyline = trim($bodyline) . ';';

                            $cnt += 1;

                            $url = $note = '';

                            $arrline = explode(';',$bodyline);
                            if (count($arrline) >= 1) {

                                $url = $arrline[0];

                                if (filter_var($url, FILTER_VALIDATE_URL)) {

                                    if (Input::where('url',$url)->count() == 0) {

                                        $referer = (count($arrline) >= 2) ? $arrline[1] : '';
                                        if ($referer) {
                                            if (!filter_var($referer, FILTER_VALIDATE_URL)) {
                                                $reportline = "failed: referer not a valid url; line=$bodyline";
                                                $url = '';
                                            }
                                        }
                                        if (count($arrline) >= 3) {
                                            $arrnote = array_splice($arrline,2);
                                            $note = implode(';' , $arrnote);
                                        } else {
                                            $note = '';
                                        }

                                        if ($url) {

                                            $reportline = "success: input ($url) imported";

                                            ertLog::logLine("D-Got ERT-INPUT; ref=$ref, url=$url; generate INPUT record");

                                            // BESTAAND BEPALEN!

                                            try {

                                                $input = new Input();
                                                $input->url = $url;
                                                $input->url_referer = $referer;
                                                $input->note = $note;
                                                $input->status_code = ERT_STATUS_SCHEDULER_SCRAPE;

                                                $input->workuser_id = 0;
                                                $input->type_code = ERT_MAILBOX_IMPORT_TYPE_CODE_WEBSITE;
                                                $input->source_code = ERT_MAILBOX_IMPORT_SOURCE_CODE_WEBFORM;

                                                $input->received_at = date('Y-m-d H:i:s');

                                                $input->save();

                                                $input->generateFilenumber();
                                                $input->save();

                                                $input->logText("Added by mailbox import");

                                            } catch (\Exception $err) {

                                                ertLog::logLine("E-readImportMailbox exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );

                                            }

                                        }

                                    } else {
                                        $reportline = "double: input ($url) already in database";
                                    }

                                } else {
                                    // skip
                                    //$reportline = "failed: url not valid; line=$bodyline";
                                }

                            }

                        }

                        if ($reportline) {
                            $reportline = "[line $cnt] $reportline";
                            ertLog::logLine("D-$reportline");
                            $loglines[] = $reportline;
                        }

                    }


                } else {
                    ertLog::logLine("D-Wrong message subject format: " . $msg->subject );
                    $loglines[] = "CANNOT process this message, WRONG subject format";
                }

                $reports[] = [
                    'from' => $msg->from,
                    'subject' => $msg->subject,
                    'arrived' => date('Y-m-d H:i:s',$msg->udate),
                    'loglines' => $loglines
                ];

                if ($delmsg) {
                    ertLog::logLine("D-Delete processed message ($msg->msgno)");
                    ertIMAPmail::imapDeleteMessage($msg->msgno);
                }

            }

        } else {
            //ertLog::logLine("D-No messages in INBOX");
        }

        ertIMAPmail::closeExpunge();

        return $reports;
    }

}
