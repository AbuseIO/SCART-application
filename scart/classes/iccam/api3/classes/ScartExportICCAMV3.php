<?php
namespace abuseio\scart\classes\iccam\api3\classes;

use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\iccam\api2\scartICCAM;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMcurl;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMAuthentication;
use abuseio\scart\classes\iccam\api3\models\ScartICCAMapi;
use abuseio\scart\classes\iccam\api3\models\scartICCAMfieldsV3;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Grade_question;
use abuseio\scart\Models\Iccam_api_field;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_history;
use abuseio\scart\models\Input_parent;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\ImportExport_job;

class ScartExportICCAMV3 extends ScartGenericICCAMV3 {

    public function __construct($maxresults = 0) {

        $maxresults = ($maxresults == 0) ? Systemconfig::get('abuseio.scart::iccam.exportmax', '20') : 20;
        $this->maxresults = $maxresults;
    }

    /**
     * @descripton process new reports to
     */
    public function do() {

        try {

            $reports = [];

            // Check if we can do (ICCAM) requests and get Token
            if (ICCAMAuthentication::login('ScartExportICCAMV3')) {

                scartLog::logLine("D-ScartExportICCAMV3; authenticated" );

                $jobs = scartICCAMinterface::getExportActions($this->maxresults);
                $cntjobs = count($jobs); $cnt = 1;
                scartLog::logLine("D-scartExportICCAMV3; export jobs count: $cntjobs");
                $iccamwaitfornext = 1;  // sleep time between ICCAM calls

                $notinonerun = [];
                foreach ($jobs AS $job) {

                    scartLog::logLine("D-scartExportICCAMV3; [$cnt/$cntjobs]; got job-id: ". $job['job_id'] .", action: " . $job['action'] . ", timestamp: " . $job['timestamp']);

                    $this->resetLoglines();
                    $this->resetPosts();

                    $this->_status = SCART_IMPORTEXPORT_STATUS_SUCCESS;
                    $this->_status_text = '';

                    $skip = false;

                    if ($record = $this->getDataRecord($job['data']) ) {

                        switch ($job['action']) {

                            case SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT:

                                $skip = $this->doExportReport($record,$job);
                                if ($record->reference!='') {
                                    // do exclude in one run the export of a report with general fields AND action(s) for this report
                                    // ICCAM will give errors because ICCAM need time to process these report settings and to put the report into MONITOR stage
                                    $reportId = scartICCAMinterface::getICCAMreportID($record->reference);
                                    $notinonerun[] = $reportId;
                                }
                                break;

                            case SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION:

                                $reportId = scartICCAMinterface::getICCAMreportID($record->reference);
                                if (!in_array($reportId,$notinonerun)) {
                                    $skip = $this->doExportAction($record,$job);
                                } else {
                                    scartLog::logLine("D-scartExportICCAMV3; action(s) for report $reportId not in one run with report (general) export" );
                                }
                                break;

                            default:
                                $logline = $this->_status_text = "Unknown job action: ".$job['action'];
                                scartLog::logLine("W-scartExportICCAMV3; $logline");
                                $this->addLogline($logline);
                                $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;
                                break;

                        }

                    } else {

                        $logline = $this->_status_text = "ICCAM no valid (technical) exportdata";
                        $this->addLogline($logline);
                        scartLog::logLine("W-scartExportICCAMV3; $this->_status_text; job=" . print_r($job, true) );
                        $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;
                    }

                    if (!$skip) {
                        // UPDATE abuseio_scart_importexport_job with status and status_text
                        $importexport = ImportExport_job::withTrashed()->find($job['job_id']);
                        if ($importexport) {
                            $importexport->status = $this->_status;
                            $importexport->status_text = $this->_status_text;
                            // extra info when errors
                            //if (ICCAMcurl::hasErrors()) $importexport->postdata = $this->getPosts();
                            $importexport->postdata = $this->getPosts();
                            $importexport->save();
                        }
                        // Note: soft delete
                        scartICCAMinterface::delExportAction($job);
                    }

                    if ($this->bLoglines()) {
                        if (isset($record->id)) {
                            $record = Input::find($record->id);
                        } else {
                            $record = (object) [
                                'reference' => '(not valid)',
                            ];
                        }
                        $reports[] = [
                            'reportID' => $record->reference,
                            'loglines' => $this->returnLoglines(),
                        ];
                    }

                    // sleep to overcome ICCAM API overloading
                    sleep($iccamwaitfornext);
                    $cnt+= 1;
                }

                // Finalize proccess : set Alerts or log
                if (!empty($reports)) {
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO, 'abuseio.scart::mail.scheduler_export_iccam', ['reports' => $reports]);
                } elseif (ICCAMcurl::hasErrors()) {
                    scartLog::logLine("W-ScartExportICCAMV3; ICCAM OFFLINE!?");
                }

            }

        } catch (\Exception $err) {
            scartLog::logLine("E-ScartExportICCAMV3; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
        }

    }

    /**
     * ICCAM V3
     * - only support of one post with the mainurl with all (illegal) urls
     * - get receive the reportID
     * - with this reportID we can map all the contentID's to main- and sub-urls
     *
     * So we do:
     * 1. look if already ICCAM reference
     * 2. if not, then lookup of the mainurl of the record and insert this mainurl with sub urls
     * 3. if already a reference, then update based on ICCAM contentID
     *
     * @param $record
     * @param $job
     * @return bool[]
     */

    private function doExportReport($record,$job) {

        $skip = false;

        /**
         * We get MAINURL records; handle step for step
         *
         * 1: sent all assessment
         * 2: set commerciality/paymentMethods (=complete general fields report in ICCAM)
         * 3: sent actions
         *
         * Note: if step 3 is done without first 1+2 then errors in ICAM
         *
         */

        // when at this point, don't look anymore if first time (online_counter=0), can be a reset
        //if ($record->url_type==SCART_URL_TYPE_MAINURL && $record->online_counter == 0) {
        if ($record->url_type==SCART_URL_TYPE_MAINURL) {

            // check if not new content from an existing reportid

            if ($record->reference == '') {

                scartLog::logLine("D-ScartExportICCAMV3; [$record->filenumber] doExport new ICCAM report");

                // Insert record with all items
                $this->insertReport($record);

                if (ICCAMcurl::isOffline()) {
                    // if ICCAM offline then skip and retry later
                    $logline = $this->_status_text = "ICCAM (curl) offline error";
                    $this->addLogline($logline);
                    $this->_status = SCART_IMPORTEXPORT_STATUS_EXPORT;
                    $skip = true;
                } elseif (ICCAMcurl::hasErrors()) {
                    $logline = $this->_status_text = "ICCAM (curl) error: " . ICCAMcurl::getErrors();
                    $this->addLogline($logline);
                    $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;
                }

            } else {

                scartLog::logLine("D-ScartExportICCAMV3; [$record->filenumber] doExport exisiting ICCAM report; ICCAM reference=$record->reference");

                // export record items
                $skip = $this->insertExistingReport($record);

                if (!$skip) {
                    if (ICCAMcurl::isOffline()) {
                        // if ICCAM offline then skip and retry later
                        $logline = $this->_status_text = "ICCAM (curl) offline error";
                        $this->addLogline($logline);
                        $this->_status = SCART_IMPORTEXPORT_STATUS_EXPORT;
                        $skip = true;
                    } elseif (ICCAMcurl::hasErrors()) {
                        $logline = $this->_status_text = "ICCAM (curl) doExportReport error: " . ICCAMcurl::getErrors();
                        $this->addLogline($logline);
                        $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;
                    }
                }

            }

            // After this ready to receive actions in ICCAM  (monitor stage)

        } else {

            $logline = $this->_status_text = "No mainurl";
            $this->addLogline($logline);
            scartLog::logLine("W-scartExportICCAMV3; $this->_status_text; job=" . print_r($job, true) );
            $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;

        }

        // Note: exit obsolute
        return $skip;
    }

    /**
     * New record(s) for ICCAM -> insert with all sub urls
     * add assessment also
     *
     * @param $parent
     */
    private function insertReport($parent) {

        // set workuser on ICCAM (API) user -> SCART workusers not always ICCAM user
        $workuser = Systemconfig::get('abuseio.scart::iccam.apiuser', '');

        $hotlineReceivedDate = scartICCAMfieldsV3::iccamDate(strtotime($parent->received_at));

        // init with general/defaults
        $iccamreport = [
            'reportingAnalystName' => $workuser,
            'hotlineReceivedDate' => $hotlineReceivedDate,
            // make ALWAYS unique
            'hotlineReference' => $parent->filenumber.'_'.date('YmdHi'),
            'memo' => $parent->note,
            'urls' => [],
            'sourceUrlUsername' => '',
            'sourceUrlPassword' => '',
            'sourceUrlReferrer' => (empty($parent->url_referer) ? null : $parent->url_referer),
            'sourceUrlSiteType' => scartICCAMfieldsV3::getSiteTypeID($parent),
            'sourceUrlCommerciality' => scartICCAMfieldsV3::$CommercialityIDNotDetermined,
            'sourceUrlPaymentMethods' => [1],
        ];

        // fill specific fields from classification iccamfield questions
        $scartfield2iccamreport = [
            'CommercialityID' => 'sourceUrlCommerciality',
            'PaymentMethodID' => 'sourceUrlPaymentMethods',
        ];
        $iccamreport = array_merge($iccamreport,$this->iccamClassification($parent,$scartfield2iccamreport));

        // sub urls with assessment
        $urls = $parentitems = [];
        $inputs = Input_parent::where('parent_id',$parent->id)->get();
        foreach ($inputs as $input_parent) {
            $input = Input::find($input_parent->input_id);
            $parentitems[$input->url] = $input;
            $url = $this->makeUrl($input,$parent->id);
            if (is_array($url)) $urls[$input->url] = (object) $url;
        }

        // rules flow -> there can be no input_parent
        if (empty($urls)) {
            scartLog::logLine("W-scartExportICCAMV3; [$parent->filenumber]; missing parent url, repair");
            $parentitems[$parent->url] = $parent;
            $url = $this->makeUrl($parent,$parent->id);
            if (is_array($url)) $urls[$parent->url] = (object) $url;
        }

        // when assessment is NOT-ILLEGAL, sent (also) action NOT-ILLEGAL
        if ($parent->grade_code != SCART_GRADE_ILLEGAL) {

            // addAction NotIllegal for next loop
            scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                'record_type' => class_basename($parent),
                'record_id' => $parent->id,
                'object_id' => $parent->reference,
                'action_id' => SCART_ICCAM_ACTION_NI,     // NOT_ILLEGAL
                'country' => '',                          // hotline default
                'reason' => 'SCART reported NI',
            ]);

        }

        $iccamreport = (object) $iccamreport;
        $iccamreport->urls = array_values($urls);

        //scartLog::logDump("D-scartExportICCAMV3; postReport, iccamdata=",$iccamreport);
        //ICCAMcurl::setDebug(true);
        $this->addPosts($iccamreport);
        $ICCAMreportID = (new ScartICCAMapi())->postReport($iccamreport);
        //ICCAMcurl::setDebug(false);

        if ($ICCAMreportID && is_numeric($ICCAMreportID)) {

            // get ICCAM contentId's and map to SCART records
            $report = (new ScartICCAMapi())->getReport($ICCAMreportID);
            if (isset($report->reportContents) && count($report->reportContents) > 0) {
                foreach ($report->reportContents as $reportContent) {
                    if (isset($parentitems[$reportContent->urlString])) {
                        $input = $parentitems[$reportContent->urlString];
                        $oldref = $input->reference;
                        $input->reference = scartICCAMinterface::setICCAMreportID($ICCAMreportID,$reportContent->contentId);
                        if ($oldref) {
                            scartLog::logLine("W-scartExportICCAMV3; [$input->filenumber]; already has reference in SCART ($oldref) - overrule by ICCAM ($input->reference)");
                        }
                        $input->save();
                        $input->logHistory(SCART_INPUT_HISTORY_ICCAM,$oldref,$input->reference,'Got reportID/contentId from export to ICCAM');
                        $input->logText("(ICCAM) Exported; got ICCAM reportID=$ICCAMreportID, contentId=$reportContent->contentId");
                        scartLog::logLine("D-scartExportICCAMV3; [$input->filenumber]; got contentId, set reference=$input->reference");
                    }
                }
            }
            $this->addLogline("exported SCART report (mainurl=$parent->filenumber) to ICCAM (reporId=$ICCAMreportID)");

        } else {
            scartLog::logLine("W-scartExportICCAMV3; [filenumber=$parent->filenumber] ICCAM reportId empty or not numeric!?!");
        }

    }

    private function makeUrl($input,$parent_id) {

        $url = false;

        $hostabusecontact = Abusecontact::find($input->host_abusecontact_id);
        if (!$hostabusecontact) {
            scartLog::logLine("W-scartExportICCAMV3; filenumber=$input->filenumber; hoster not set?!? - skip");
        } else {

            $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
            $hostcounty = (scartGrade::isLocal($hostabusecontact->abusecountry) ? $hotlinecountry : $hostabusecontact->abusecountry);

            // skip not illegal records (except mainurl)

            if ($input->grade_code == SCART_GRADE_ILLEGAL || $input->id == $parent_id) {

                scartLog::logLine("D-scartExportICCAMV3; filenumber=$input->filenumber; add to insert export ");

                $assessment = (object) $this->makeAssessment($input);

                // Note (To-Do): imagedata is in this stage already cleaned from the scrapecache; how to get the sha1...!?
                // may be add field url_hash_sha1 also for other hash export (eg Verify)

                $url = [
                    "urlString" => $input->url,
                    'hotlineReference' => $input->filenumber.'_'.date('YmdHi'),
                    "hostingCountryCode" => $hostcounty,
                    "hostingIpAddress" => $input->url_ip,

                    "hostingIsp" => $hostabusecontact->owner,
                    "hostingOrganization" => [
                        //"autonomousSystemNumber" => "string",
                        "organizationName" => $hostabusecontact->owner,
                        //"isContentDeliveryNetwork" => true/false,
                    ],

                    "isSourceUrl"=> (($input->id==$parent_id)?true:false),
                    "memo" => $input->note,
                    "contentType" => (($input->url_type=='mainurl')?SCART_ICCAM_CONTENTTYPE_WEBSITE:SCART_ICCAM_CONTENTTYPE_MEDIA),
                    'assessment' => $assessment,
                    "actions" => [],
                ];

            } else {
                scartLog::logLine("D-scartExportICCAMV3; filenumber=$input->filenumber; grade_code=$input->grade_code, skip record");
            }

        }

        return $url;
    }


    /**
     * Export an existing report to ICCAM
     *
     * @param $record
     * @return bool
     */
    private function insertExistingReport($record) {

        $skip = false;
        $reportId = scartICCAMinterface::getICCAMreportID($record->reference);

        if ($reportId) {

            $contentIds = [];

            $inputs = Input_parent::where('parent_id',$record->id)->get();
            foreach ($inputs as $input_parent) {

                $input = Input::find($input_parent->input_id);

                // handle every input - update assessment of specific RECORD in ICCAM

                $contentId = scartICCAMinterface::getICCAMcontentID($input->reference);
                if ($contentId == '') {
                    // no ICCAM reference in SCARt -> is possible with record from old API
                    $contentId = $this->getICCAMcontentId($reportId,$input->url);
                    if ($contentId) {
                        $input->reference = scartICCAMinterface::setICCAMreportID($reportId,$contentId);
                        $input->save();
                    }
                }
                if ($contentId) {

                    if (!in_array($contentId,$contentIds)) {


                        /**
                         * item is imported from ICCAM and assigned to current hotline for review/ntd local hoster
                         *
                         * Reference logic: 2023/4/26/Kalina/INHOPE
                         *
                         * -1- Baseline
                         * This is actually a feature, i.e. if something was classified as Baseline, we would already know
                         * it is illegal everywhere - therefore no further classification required. That means that when you
                         * check all items of a report, you need to set a condition if the classification sent =1 (Baseline),
                         * you need to skip classification (as is also not editable). You can however record actions on items
                         * that are classified Baseline and are assigned to NL.
                         *
                         * -2-
                         * Only NATIONAL assessed content items can be overruled in assessment by local hotline
                         *
                         * -3-
                         * Skip sending assessment for mainurl (parent); already done in ICCAM
                         *
                         */

                        $toAssessed = $this->isICCAMClassificationToAssessed($contentId);
                        if ($toAssessed) {
                            $toAssessed = ($input_parent->input_id != $input_parent->parent_id);
                        }

                        if ($toAssessed) {

                            scartLog::logLine("D-scartExportICCAMV3; update assessment for reportId=$reportId, contentId=$contentId");

                            // insert (last) assessment
                            $this->insertAssessment($contentId,$input);

                            if (ICCAMcurl::isOffline()) {
                                // if ICCAM offline then skip and retry later
                                $logline = $this->_status_text = "ICCAM (curl) offline error";
                                $this->addLogline($logline);
                                $this->_status = SCART_IMPORTEXPORT_STATUS_EXPORT;
                                scartLog::logLine("W-scartExportICCAMV3; $logline");
                                $skip = true;
                            } elseif (ICCAMcurl::hasErrors()) {
                                $logline = $this->_status_text = "ICCAM (curl) insertExistingReport error: " . ICCAMcurl::getErrors();
                                $this->addLogline($logline);
                                $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;
                                scartLog::logLine("W-scartExportICCAMV3; $logline");
                            } else {
                                $logline = $this->_status_text = "ICCAM updated report";
                                $this->addLogline($logline);
                            }

                        } else {
                            scartLog::logLine("D-scartExportICCAMV3; ICCAM classification<>NATIONAL; do not update assessment for reportId=$reportId, contentId=$contentId");
                        }

                        // new ICCAM api; for assessment = NOT-ILLEGAL, sent NOT-ILLEGAL action

                        if ($input->grade_code != SCART_GRADE_ILLEGAL) {

                            // addAction NotIllegal
                            scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                'record_type' => class_basename($input),
                                'record_id' => $input->id,
                                'object_id' => $input->reference,
                                'action_id' => SCART_ICCAM_ACTION_NI,     // NOT_ILLEGAL
                                'country' => '',                          // hotline default
                                'reason' => 'SCART reported NI',
                            ]);

                        }

                        $contentIds[] = $contentId;

                    } else {

                        scartLog::logLine("W-scartExportICCAMV3; [filenumber=$input->filenumber, reference=$input->reference]; contentId=$contentId already added ");

                    }

                } else {
                    // if still not found, then we have a problem
                    $logline = $this->_status_text = "Cannot get (ICCAM) contentId of this (ICCAM) record (mainurl.reportId=$reportId, id=$input->id, reference=$input->reference)";
                    $this->addLogline($logline);
                    scartLog::logLine("W-scartExportICCAMV3; $this->_status_text; ");
                    $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;
                    //$skip = true;
                    break;
                }

            }

            if (!$skip) {
                // check/update set important stage report fields -> always
                $this->checkUpdateReport($reportId,$record);
            }

        } else {
            scartLog::logLine("W-scartExportICCAMV3; insertExistingReport; [filenumber=$record->filenumber] has no ICCAM reportId empty!?!");
        }

        return $skip;
    }

    /**
     * Check if general report fields are filled
     * NOTE: if valid, then the report will automatically go to the monitor stage in ICCAM
     *
     * @param $reportId
     * @param $record
     */
    private function checkUpdateReport($reportId,$record) {

        scartLog::LogLine("D-scartExportICCAMV3; checkUpdateReport(reportId=$reportId)");

        $report = (new ScartICCAMapi())->getReport($reportId);
        if ($report) {
            if ($report->sourceUrlSiteType == null || $report->sourceUrlCommerciality == null || empty($report->sourceUrlPaymentMethods)) {

                // get always these fields from parent
                $parent = Input_parent::where('input_id',$record->id)->first();
                $parent = Input::find($parent->parent_id);
                if ($parent) {

                    $scartfield2iccamreport = [
                        'CommercialityID' => 'sourceUrlCommerciality',
                        'PaymentMethodID' => 'sourceUrlPaymentMethods',
                    ];
                    $iccamreport = (object) array_merge([
                        'sourceUrlSiteType' => scartICCAMfieldsV3::getSiteTypeID($parent),
                        'sourceUrlCommerciality' => scartICCAMfieldsV3::$CommercialityIDNotDetermined,
                        'sourceUrlPaymentMethods' => [scartICCAMfieldsV3::$PaymentMethodIDNotDetermined],
                    ],$this->iccamClassification($parent,$scartfield2iccamreport));

                    //ICCAMcurl::setDebug(true);
                    if ($report->sourceUrlSiteType == null) {
                        scartLog::LogLine("D-scartExportICCAMV3; reportId=$reportId; update sourceUrlSiteType");
                        $this->addPosts([
                            'reportId' => $reportId,
                            'sourceUrlSiteType' => $iccamreport->sourceUrlSiteType,
                        ]);
                        (new ScartICCAMapi())->putReportSiteType($reportId,$iccamreport->sourceUrlSiteType);
                    }
                    if ($report->sourceUrlCommerciality == null) {
                        scartLog::LogLine("D-scartExportICCAMV3; reportId=$reportId; update sourceUrlCommerciality");
                        $this->addPosts([
                            'reportId' => $reportId,
                            'sourceUrlCommerciality' => $iccamreport->sourceUrlCommerciality,
                        ]);
                        (new ScartICCAMapi())->putReportCommerciality($reportId,$iccamreport->sourceUrlCommerciality);
                    }
                    if (empty($report->sourceUrlPaymentMethods)) {
                        scartLog::LogLine("D-scartExportICCAMV3; reportId=$reportId; update sourceUrlPaymentMethods");
                        $this->addPosts([
                            'reportId' => $reportId,
                            'sourceUrlPaymentMethods' => $iccamreport->sourceUrlPaymentMethods,
                        ]);
                        (new ScartICCAMapi())->putReportPaymentMethods($reportId,$iccamreport->sourceUrlPaymentMethods);
                    }
                    //ICCAMcurl::setDebug(false);
                } else {
                    scartLog::logLine("W-scartExportICCAMV3; cannot find parent from input_id=$record->id!?!");
                }

            }
        } else {
            scartLog::logLine("W-scartExportICCAMV3; reportId=$reportId; cannot get ICCAM report!?!");
        }

    }

    /**
     * Export to ICCAM the assessment
     *
     * @param $contentId
     * @param $input
     */
    private function insertAssessment($contentId,$input) {

        // get specific assessment
        $assessment = (object) $this->makeAssessment($input);
        // set workuser on ICCAM (API) user -> SCART workusers not always ICCAM user
        $assessment->assessingAnalystName = Systemconfig::get('abuseio.scart::iccam.apiuser', '');
        // sent to ICCAM
        scartLog::LogLine("D-scartExportICCAMV3; contentId=$contentId; post assessment to ICCAM ");
        //ICCAMcurl::setDebug(true);
        $this->addPosts([
            'contentId' => $contentId,
            'assessment' => $assessment,
        ]);
        $result = (new ScartICCAMapi())->putContentAssessment($contentId,$assessment);
        //ICCAMcurl::setDebug(false);
    }

    /**
     * make array with assessment
     *
     * @param $input
     * @return array
     */
    private function makeAssessment($input) {

        // Note: can be used for Illegal and NotIllegal (ignore) records (grade_code)

        $assessmentIccam2scart = [
            'classification' => 'ClassificationID',
            'ageCategorization' => 'AgeGroupID',
            'genderCategorization' => 'GenderID',
            'virtualContentCategorization' => 'IsVirtual',
            'userGeneratedContentCategorization' => 'IsChildModeling',
            'childModelingCategorization' => 'IsUserGC',
        ];

        // fill default with NotIllegal
        $assessment = [
            'assessingAnalystName' => Systemconfig::get('abuseio.scart::iccam.apiuser', ''),
            'classification' => scartICCAMfieldsV3::$ClassificationIDignore,
            'ageCategorization' => scartICCAMfieldsV3::$AgeGroupIDNotDetermined,
            'genderCategorization' => scartICCAMfieldsV3::$GenderIDNotDetermined,
        ];

        if ($input->grade_code == SCART_GRADE_ILLEGAL) {
            $assessment = array_merge($assessment,$this->iccamClassification($input,$assessmentIccam2scart));
        }

        return $assessment;
    }

    /**
     * Get from 'iccam field' questions the answers and put into asessment array
     *
     * @param $input
     * @param $scartfield2iccamreport
     * @return array
     */
    private function iccamClassification($input,$scartfield2iccamreport) {

        $assessment = [];

        try {

            $questions = Grade_question::fetchClassifyQuestions($input->url_type);
            if (count($questions) > 0) {
                // fill specific ICCAM fields
                foreach ($questions AS $question) {
                    if ($assessmentfield = array_search($question->iccam_field,$scartfield2iccamreport)) {
                        $get = 'get'.$question->iccam_field;
                        // try-catch because of statement below
                        $id = scartICCAMfieldsV3::$get($question,$input);
                        $assessment[$assessmentfield] = $id;
                        scartLog::logLine("D-scartExportICCAMV3; set assessment[$assessmentfield]='$id'");
                    }
                }
                scartLog::logLine("D-scartExportICCAMV3; got classification from SCART record");
            }

        } catch (\Exception $err) {
            scartLog::logLine("E-ScartExportICCAMV3; iccamClassification; exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
        }

        return $assessment;
    }

    /**
     * Find specific contentId in ICCAM report items based on url
     *
     * @param $record
     * @return string
     */
    private function getICCAMcontentId($reportId,$url) {

        $contentId = $firstContentId = $contentCount = '';

        if ($reportId) {
            $report = (new ScartICCAMapi())->getReport($reportId);
            if (isset($report->reportContents) && count($report->reportContents) > 0) {
                $contentCount = count($report->reportContents);
                foreach ($report->reportContents as $reportContent) {
                    if ($firstContentId == '') {
                        $firstContentId = $reportContent->contentId;
                    }
                    if ($reportContent->urlString == $url) {
                        $contentId = $reportContent->contentId;
                        break;
                    }
                }
            }
        }

        // Note: following not working
        /*
        // fall back on first contentId when only one content item
        if ($firstContentId && $contentCount === 1) {
            $contentId = $firstContentId;
            scartLog::logLine("D-scartExportICCAMV3; got (use) FIRST contentId '$contentId' based on reporId=$reportId ");
        }
        */

        scartLog::logLine("D-scartExportICCAMV3; got (use) contentId '$contentId' based on reporId=$reportId and url=$url");
        return $contentId;
    }


    /**
     * Export action to ICCAM
     *
     * Reference Kalina/INHOPE; when content item is assigned to another hotline, skip sending actions -> not authorized
     *
     * @param $record
     * @param $job
     * @return bool
     */

    private function doExportAction($record,$job) {

        $skip = false;

        $this->addLogline("url: $record->url ($record->filenumber)");
        $actionID = $job['data']['action_id'];

        $reportId = scartICCAMinterface::getICCAMreportID($record->reference);
        if ($reportId=='' && $record->url_type !=SCART_URL_TYPE_MAINURL) {
            // try to get from mainurl the reportId
            $parent = Input_parent::where('input_id',$record->id)->first();
            $parent = Input::find($parent->parent_id);
            $reportId = scartICCAMinterface::getICCAMreportID($parent->reference);
            scartLog::logLine("D-scartExportICCAMV3; got reportId '$reportId' from parent (id=$parent->id)");
        }
        $contentId = scartICCAMinterface::getICCAMcontentID($record->reference);
        if ($reportId && $contentId == '') {
            // no ICCAM reference in SCARt -> is possible with record from old API
            $contentId = $this->getICCAMcontentId($reportId,$record->url);
            if ($contentId) {
                $record->reference = scartICCAMinterface::setICCAMreportID($reportId,$contentId);
                $record->save();
            }
        }
        if ($reportId && $contentId) {

            $actionname = ($actionID == SCART_ICCAM_ACTION_SETHOTLINE) ? 'set hotline reference' : scartICCAMfieldsV3::getActionName($actionID);

            if (self::checkAssignedLocalHotline($contentId)) {

                // ICCAM set action

                if ($actionID == SCART_ICCAM_ACTION_MO) {

                    // in ICCAM V3 seperated flow for MOVED
                    $newIpAddress = $record->url_ip;
                    $newCountryCode = $job['data']['country'];
                    if (empty($newCountryCode)) $newCountryCode = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
                    $this->addLogline("Set ICCAM hotline country to '$newCountryCode'");
                    $this->postMovedAction($contentId,$newIpAddress,$newCountryCode);

                } elseif ($actionID == SCART_ICCAM_ACTION_NI) {

                    // sent NOT ILLEGAL
                    $this->postNotIllegalAction($contentId,$job['data']);

                } elseif ($actionID == SCART_ICCAM_ACTION_SETHOTLINE) {

                    // user defined action; set hotline reference
                    $this->postHotlineReference($contentId,$record->filenumber);

                } else {

                    // any other action
                    $this->postAction($contentId,$job['data']);

                }

                if (ICCAMcurl::isOffline()) {
                    // if ICCAM offline then skip and retry later
                    $logline = $this->_status_text = "ICCAM (curl) action=$actionname ($actionID); offline error";
                    $this->addLogline($logline);
                    $this->_status = SCART_IMPORTEXPORT_STATUS_EXPORT;
                    $skip = true;
                } elseif (ICCAMcurl::hasErrors()) {
                    $logline = $this->_status_text = "ICCAM (curl) action=$actionname ($actionID); error: " . ICCAMcurl::getErrors();
                    $this->addLogline($logline);
                    $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;
                } else {
                    $logtext = "Export action '$actionname' ($actionID) ";
                    $record->logText("$logtext");
                    $this->addLogline($logtext);
                    // ICCAMreportID no change, force history insert, external (iccam) action add
                    $record->logHistory(SCART_INPUT_HISTORY_ICCAM,$record->reference,$record->reference,$logtext,true);
                }

            } else {

                if ($actionID == SCART_ICCAM_ACTION_MO) {
                    $logline = $this->_status_text = "ICCAM content item (already) assigned to another hotline - skip action '$actionname' (id=$actionID)";
                } else {
                    $logline = $this->_status_text = "ICCAM content item assigned to another hotline - skip action '$actionname' (id=$actionID)";
                }
                scartLog::logLine("W-scartExportICCAMV3; $logline");
                $this->addLogline($logline);
                $this->_status = SCART_IMPORTEXPORT_STATUS_SKIP;

            }

        } else {
            $logline = $this->_status_text = "empty (ICCAM) ICCAM referrence in SCART?; cannot export action '$actionID'";
            scartLog::logLine("W-scartExportICCAMV3; $logline");
            $this->addLogline($logline);
            $this->_status = SCART_IMPORTEXPORT_STATUS_ERROR;
        }

        return $skip;
    }


    private function checkAssignedLocalHotline($contentId) {

        $localassigned = true;

        // check if not already set on other country (by Report Export)
        $iccamcontent = (new ScartICCAMapi())->getContent($contentId);
        if ($iccamcontent) {
            // when not assigned to this hotline, we have NO right for this in ICCAM
            $hotlinecountry = Systemconfig::get('abuseio.scart::classify.hotline_country', '');
            $localassigned = ($iccamcontent->assignedCountryCode == $hotlinecountry);
            scartLog::logLine("D-scartExportICCAMV3; checkAssignedHotline; localhostline '$hotlinecountry', assigned in ICCAM is '$iccamcontent->assignedCountryCode'");
        } else {
            scartLog::logLine("W-scartExportICCAMV3; checkAssignedHotline; cannot find contentId=$contentId in iCCAM !?!");
        }

        return $localassigned;
    }
    /**
     * Export the action to ICCAM
     *
     * @param $contentId
     * @param $data
     */
    private function postAction($contentId,$data) {

        $actionID = $data['action_id'];
        $iccamActionId = scartICCAMfieldsV3::getActionID($actionID);
        $iccamdate = scartICCAMfieldsV3::iccamDate(time());
        // Note: reason has to be in sync with ICCAM reason (text)
        $reason = $data['reason'];
        $iccamActionReasonId = scartICCAMfieldsV3::getActionReasonID($reason);
        $action = (object) [
            'actionType' => $iccamActionId,
            'actioningAnalystName' => Systemconfig::get('abuseio.scart::iccam.apiuser', ''),
            'actionDate' => $iccamdate,
        ];
        // only set fields if required by ICCAM -> first three iccamActionId have NO actionReason
        if ($iccamActionId > 3) {
            $action->reasonId = $iccamActionReasonId;
            $action->reasonText = $reason;
        }
        //ICCAMcurl::setDebug(true);
        $this->addPosts([
            'contentId' => $contentId,
            'action' => $action,
        ]);
        scartLog::logLine("D-scartExportICCAMV3; postAction; contentId=$contentId put action '".scartICCAMfieldsV3::getActionName($actionID)."' ");
        $result = (new ScartICCAMapi())->postContentAction($contentId, $action);
        //ICCAMcurl::setDebug(false);
    }

    /**
     * Export a NotIllegal action to ICCAM
     *
     * @param $contentId
     * @param $data
     */
    private function postNotIllegalAction($contentId,$data) {

        // Note: we can do here special things for NotIllegal

        $actionID = $data['action_id'];
        $iccamActionId = scartICCAMfieldsV3::getActionID($actionID);
        if (!$this->hasICCAMaction($contentId,$iccamActionId)) {
            $this->postAction($contentId, $data);
        } else {
            scartLog::logLine("D-scartExportICCAMV3; contentId=$contentId has already action=".scartICCAMfieldsV3::getActionName($actionID)." - skip");
        }

    }

    /**
     * Special actions when exporting MOVE action
     *
     * @param $contentId
     * @param $newIpAddress
     * @param $newCountryCode
     */
    public function postMovedAction($contentId,$newIpAddress,$newCountryCode) {

        //ICCAMcurl::setDebug(true);
        // new hoster with country (outside hotline country)
        $this->addPosts([
            'contentId' => $contentId,
            'newIpAddress' => $newIpAddress,
            'newCountryCode' => $newCountryCode,
        ]);
        $result = (new ScartICCAMapi())->putContentNewHosting($contentId, $newIpAddress, $newCountryCode);
        if (!ICCAMcurl::isOffline() && !ICCAMcurl::hasErrors()) {
            // move to new country hotline
            $result = (new ScartICCAMapi())->putContentAssignedCountry($contentId, $newCountryCode);
        }
        //ICCAMcurl::setDebug(false);

    }

    /**
     * Export content hotline reference
     *
     * @param $contentId
     * @param $scartreference
     */
    private function postHotlineReference($contentId, $scartreference) {

        //ICCAMcurl::setDebug(true);
        // addPost?!
        $this->addPosts([
            'contentId' => $contentId,
            // make always unique
            'scartreference' => $scartreference.'_'.date('YmdHi'),
        ]);
        $result = (new ScartICCAMapi())->putContentHotlineReference($contentId, $scartreference);
        //ICCAMcurl::setDebug(false);
    }

    /**
     * get job data
     *
     * @param $jobdata
     * @return string
     */
    private function getDataRecord($jobdata) {

        if (isset($jobdata['record_id']) ) {
            $record = Input::find($jobdata['record_id']);
        } else {
            $record = '';
        }
        return $record;
    }

    /**
     * isICCAMClassificationToAssessed
     *
     * Special function to check if the content has assessmentable classification in ICCAM
     *
     */

    private function isICCAMClassificationToAssessed($contentId) {

        $toAssessed = true;
        $contentItem = (new ScartICCAMapi())->getContent($contentId);
        if (!empty($contentItem->assessments)) {
            // Reference Kalina/INHOPE; use last assessment
            $assessment = $contentItem->assessments[count($contentItem->assessments) - 1];
            $classification = (isset($assessment->classification) ? $assessment->classification : 0);
            $toAssessed = ($classification != scartICCAMfieldsV3::$ClassificationIDbaseline && $classification != scartICCAMfieldsV3::$ClassificationIDignore);
        }
        return $toAssessed;
    }


    private function hasICCAMaction($contentId,$iccamActionId) {

        $hasaction = false;
        $contentItem = (new ScartICCAMapi())->getContent($contentId);
        if (!empty($contentItem->actions)) {
            foreach ($contentItem->actions as $action) {
                if ($action->actionType == $iccamActionId) {
                    $hasaction = true;
                    break;
                }
            }
        }
        return $hasaction;
    }


}
