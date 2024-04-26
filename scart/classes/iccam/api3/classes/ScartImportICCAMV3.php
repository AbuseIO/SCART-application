<?php
namespace abuseio\scart\classes\iccam\api3\classes;

use abuseio\scart\classes\aianalyze\scartAIanalyze;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\iccam\api2\scartICCAMmapping;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMAuthentication;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMContent;
use abuseio\scart\classes\iccam\api3\classes\helpers\ICCAMcurl;
use abuseio\scart\classes\iccam\api3\models\ScartICCAMapi;
use abuseio\scart\classes\iccam\api3\models\scartICCAMfieldsV3;
use abuseio\scart\classes\iccam\scartICCAMinterface;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\online\scartHASHcheck;
use abuseio\scart\classes\rules\scartRules;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\Controllers\Grade;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Addon;
use abuseio\scart\models\Grade_answer;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_parent;
use abuseio\scart\models\Iccam_api_field;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Systemconfig;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class ScartImportICCAMV3 extends ScartGenericICCAMV3 {

    public function __construct($maxresults = 0) {

        $maxresults = ($maxresults == 0) ? Systemconfig::get('abuseio.scart::iccam.readimportmax', '20') : 20;
        $this->maxresults = $maxresults;
    }

    /**
     * @descripton find unreferenced content items in ICCAM and process them
     *
     */
    public function do()
    {

        try {

            $reports = [];

            // Check if we can do (ICCAM) requests and get Token
            if (ICCAMAuthentication::login('ScartImportICCAMV3')) {

                scartLog::logLine("D-ScartImportICCAMV3; authenticated" );

                // init/start browser
                scartBrowser::startBrowser();

                // set ICCAM time frame
                $iccamtimeframe = 30;

                // get import bulk count
                scartLog::logLine("D-ScartImportICCAMV3; import bulk maxresults=$this->maxresults" );

                // get time
                $hotlineAssignmentDate = $this->gethotlineAssignmentDate($iccamtimeframe);
                $lastdate = $hotlineAssignmentDate['lastdate'];

                // INHOPE/ICCAM/Kalina; act on unreference reports for current hotline; mark with own reference when done
                $types = [SCART_ICCAM_REPORTSTAGE_UNREFERENCE];
                foreach ($types as $type) {

                    scartLog::logLine("D-ScartImportICCAMV3; get type=$type" );
                    $apicall = 'get'.$type;
                    $iccamreports = (new ScartICCAMapi())->$apicall($this->maxresults,$lastdate);

                    if ($iccamreports) {

                        scartLog::logLine("D-ScartImportICCAMV3; got $type count=".count($iccamreports));

                        // collect all related fields for each report

                        $reportId = '';
                        $mainreports = [];
                        foreach ($iccamreports as $iccamreport) {

                            if ($reportId=='' || ($reportId != $iccamreport->reportId)) $reportId = $iccamreport->reportId;

                            //if (!scartICCAMinterface::alreadyICCAMreportID($reportId)) {

                                // group by main report
                                if (!isset($mainreports[$reportId])) {
                                    // get report with a list of content items
                                    $mainreports[$reportId] = (new ScartICCAMapi())->getReport($reportId);
                                    //scartLog::logDump("D-ScartImportICCAMV3; getReport($reportid)=",$mainreports[$reportid]);
                                }

                                // when interface error or ICCAM report gone, then $mainreports[$reportId] can be empty -> next time
                                if (isset($mainreports[$reportId]->reportContents)) {

                                    // get (Save) all info from each contentitem
                                    if (!isset($mainreports[$reportId]->contentItems)) {
                                        $mainreports[$reportId]->contentItems = [];
                                    }

                                    scartLog::logLine("D-ScartImportICCAMV3; add for processing reports[$reportId]->contentItems[$iccamreport->contentId] ");
                                    $mainreports[$reportId]->contentItems[$iccamreport->contentId] =(new ScartICCAMapi())->getContent($iccamreport->contentId);

                                } else {
                                    unset($mainreports[$reportId]);
                                }

                            //} else {
                            //    // already imported -> skip
                            //    scartLog::logLine("D-ScartImportICCAMV3; reportId=$reportId ALREADY in database - skip import");
                            //}

                        }

                        // parse the found report(s)
                        $reports = $this->parseContent($type,$mainreports);

                    } else {
                        scartLog::logLine("D-ScartImportICCAMV3; NO $type reports");
                    }

                }

                // stop browser
                scartBrowser::stopBrowser();

                // save date
                $this->saveNexthotlineAssignmentStartDate($hotlineAssignmentDate, $iccamtimeframe);

                // Finalize proccess : set Alerts or log
                if (count($reports) > 0) {
                    scartAlerts::insertAlert(SCART_ALERT_LEVEL_INFO, 'abuseio.scart::mail.scheduler_import_iccam', ['reports' => $reports]);
                } elseif (ICCAMcurl::hasErrors()) {
                    scartLog::logLine("W-scartImportICCAM; ICCAM OFFLINE!?: error=" . ICCAMcurl::getErrors());
                }
            }

        } catch (\Throwable $err) {
            scartLog::logLine("E-ScartImportICCAMV3; throwable exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
        }

    }


    /**
     * Note from INHOPE Kalina;
     *
     * In current API, if content item has actions you need to check if content item has a final action
     * set (Content Removed, Content Unavailable, Not Illegal).
     * (this is based on assumption EOKM/SCART also collect when LEA has been informed and when ISP has been informed)
     *
     * If not - it is still on your list to process. Then you should check if:
     *  SCART has LEA set  and ICCAM has no LEA set - set LEA
     *  SCART has ISP set  and ICCAM has no ISP set - set ISP
     *   SCART has final action set and ICCAM has no final action set (Content Removed, Content Unavailable, Not Illegal) -> add action in ICCAM
     *
     */

    /**
     * We got all content items for each report grouped
     * Parse mainurl (report) and content item(s)
     * Push the report/content directly to the CLASSIFY phase in SCART
     * So get the WhoIs and imagedata
     *
     * @param $type
     * @param $scartimports
     * @return array
     */
    private function parseContent($type,$scartimports) {

        $reports = [];

        //scartLog::logDump("D-scartImportICCAM(parseContent); scartimport=",$scartimport);

        foreach ($scartimports as $reportId => $reportRecord) {

            scartLog::logLine("D-ScartImportICCAMV3; parseContent(type=$type); got ICCAM reportId=$reportId");

            /**
             * Note:
             * in scartimports we got a couple of content items belonging to a ICCAM report
             * In these content items the main content (isSourceUrl) is not always included
             * We need this, so we have to loop through the report->reportContents to get the main one
             *
             */

            $mainContentId = 0; $addgot = '?';

            // Note: mainurl is not always included in reportRecord->contentItems
            $mainreport = (new ScartICCAMapi())->getReport($reportId);
            if (isset($mainreport->reportContents)) {

                foreach ($mainreport->reportContents as $reportContent) {
                    $contentReport = (new ScartICCAMapi())->getContent($reportContent->contentId);
                    if ($contentReport && $contentReport->url->isSourceUrl) {
                        // we got 'm
                        $mainContentId = $reportContent->contentId;
                        $addgot = (isset($reportRecord->contentItems[$mainContentId])) ? 'got' : 'add';
                        $reportRecord->contentItems[$mainContentId] = $contentReport;
                        break;
                    }
                }
                scartLog::logLine("D-ScartImportICCAMV3; $addgot mainContentId for processing reports[$reportId]->contentItems[$mainContentId]");

                if ($mainContentId) {
                    if (!scartICCAMinterface::alreadyICCAMreport($reportId,$mainContentId)) {

                        $reportRecord->report_type = $type;

                        // We got the main content id, so we can insert the Report with Content item(s)
                        $loglines = $this->insertReport($reportId,$mainContentId,$reportRecord);
                        if (count($loglines) > 0) {
                            $reports[] = [
                                'reportID' => $reportId,
                                'loglines' => $loglines
                            ];
                        }

                    } else {
                        // already imported -> skip
                        scartLog::logLine("D-ScartImportICCAMV3; (mainurl) reportId=$reportId (contentId=$mainContentId) ALREADY in database - skip import");

                        // set reference in ICCAM
                        $input = scartICCAMinterface::findICCAMreport($reportId,$mainContentId);
                        if ($input) {
                            $this->setSCARTreference($mainContentId,$input->filenumber);
                        }

                    }
                } else {
                    // we need main content item
                    scartLog::logLine("W-ScartImportICCAMV3; (mainurl) reportId=$reportId; cannot find MAIN contentId - skip import");
                }

            } else {
                scartLog::logLine("W-ScartImportICCAMV3; empty result from getReports(); skip ");
            }

        }

        return $reports;
    }

    private function insertReport($reportId,$mainContentId,$reportRecord) {

        $this->resetLoglines();

        /**
         * We insert the Report with the Content item(s)
         * the SCART report will directly go to the CLASSIFY state
         * we (fill) the whois information and get the image(data)
         *
         * if reports (contentitems) already exist in SCART, then they are reset to classify
         * the analist can decide what to do with these reports
         * may be online again, may be other content, may be not-found
         *
         * NOTE: WE HANDLE THE MAINURL FIRST BECAUSE:
         * 1. we need the mainurl input_id
         * 2. WHEN IN "RUNNING STATE" WE SKIP THE IMPORT OF THE MAINURL WITH ALL CONTENTITEMS
         *
         */

        $inputParentId = $delivered_items = 0;

        $contentItem = $reportRecord->contentItems[$mainContentId];
        if (Input::where('url', $contentItem->url->urlString)->count() == 0) {

            // analyze and create SCART input record
            if ($maininput = $this->insertContent($reportId,$mainContentId,$contentItem,$reportRecord,0)) {

                $inputParentId = $maininput->id;

                $this->setSCARTreference($mainContentId,$maininput->filenumber);

                $this->addLogline("(main) filenumber=$maininput->filenumber; ICCAM reference; ReportId=$reportId, ContentId=$mainContentId");

                $delivered_items += 1;

            } else {
                scartLog::logLine("W-ScartImportICCAMV3; error inserting (mainurl) contentId=$mainContentId - skip");

            }

        } else {

            scartLog::logLine("W-ScartImportICCAMV3; (MAINURL) url '".$contentItem->url->urlString."' already in SCART");

            if ($maininput = Input::where('url', $contentItem->url->urlString)->first()) {
                // Note: check in which state the SCRAT report is, don't mess with reports in a running state
                $delivered_items = $this->connectNotRunning($reportId,$contentItem,$maininput,$inputParentId);
            }

        }

        if ($inputParentId) {

            foreach ($reportRecord->contentItems as $contentItem) {

                if ($contentItem->contentId != $mainContentId) {

                    $existingRecord = false;

                    // note: imageurl (image) can already be imported
                    if (!scartICCAMinterface::alreadyICCAMreport($reportId,$contentItem->contentId)) {

                        if (Input::where('url', $contentItem->url->urlString)->count() == 0) {

                            // get content and analyzed
                            if ($input = $this->insertContent($reportId,$contentItem->contentId,$contentItem,$reportRecord,$inputParentId)) {

                                // set & mark status
                                $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_GRADE,"Import from ICCAM; direct to classify");
                                $input->status_code = $input->classify_status_code = SCART_STATUS_GRADE;
                                $input->save();

                                $this->setSCARTreference($contentItem->contentId,$input->filenumber);

                                $this->addLogline("filenumber=$input->filenumber; ICCAM reference; ReportId=$reportId, ContentId=$contentItem->contentId");
                                $delivered_items += 1;

                            } else {
                                scartLog::logLine("W-ScartImportICCAMV3; error inserting contentId=$contentItem->contentId - skip");
                            }

                        } else {

                            scartLog::logLine("W-ScartImportICCAMV3; (content item) url '".$contentItem->url->urlString."' ALREADY in SCART - add to this import");

                            $input = Input::where('url', $contentItem->url->urlString)->first();

                            $existingRecord = true;

                        }
                    } else {

                        scartLog::logLine("D-ScartImportICCAMV3; (content) reportId=$reportId, contentId=$contentItem->contentId ALREADY imported");

                        $input = scartICCAMinterface::findICCAMreport($reportId,$contentItem->contentId);

                        $existingRecord = true;

                    }

                    if ($existingRecord && $input) {

                        $delivered_items += $this->connectNotRunning($reportId,$contentItem,$input,$inputParentId);

                    }

                }

            }
            scartLog::logLine("D-ScartImportICCAMV3; found $delivered_items content item(s) for reportId=$reportId");

            $maininput->delivered_items = $delivered_items;

            // Next status -> can be AI_ANALYZE
            $status_next = (scartAIanalyze::isActive()) ? SCART_STATUS_SCHEDULER_AI_ANALYZE : SCART_STATUS_GRADE;
            if ($maininput->status_code != $status_next) {
                $maininput->logHistory(SCART_INPUT_HISTORY_STATUS,$maininput->status_code,$status_next,"Import from ICCAM; next fase");
                $maininput->status_code = $status_next;
            }
            $maininput->save();

            // special step when AI addon is active
            if ($maininput->status_code == SCART_STATUS_SCHEDULER_AI_ANALYZE) {
                $AIaddon = Addon::getAddonType(SCART_ADDON_TYPE_AI_IMAGE_ANALYZER);
                $this->addLogline("filenumber=$maininput->filenumber; (MAINURL) push first to AI analyzer");
                scartSchedulerAnalyzeInput::pushRecordsAI($AIaddon, $maininput);
            }

        }

        return $this->returnLoglines();
    }

    /**
     * Handle SCART exisiting report depending on the running state.
     *
     * @param $reportId
     * @param $contentItem
     * @param $input
     * @param $inputParentId    if 0 then mainurl
     * @return int
     */

    private function connectNotRunning($reportId,$contentItem,$input,&$inputParentId) {

        $mainurltxt = ($inputParentId==0) ? '(MAINURL)' : '';

        if ($this->inRunningState($input->status_code)) {

            // mainurl in "running state with ICCAM action to do" -> if we switch ICCAM reference here, action for old ICCAM reference are lost (!)
            scartLog::logLine("W-ScartImportICCAMV3; $mainurltxt filenumber '$input->filenumber' has status '$input->status_code' - in running state so ignore as new import");

            $this->addLogline("filenumber=$input->filenumber; $mainurltxt SCART report in running state; ignore as new import");
            $add_delivered = 0;

        } else {

            scartLog::logLine("D-ScartImportICCAMV3; $mainurltxt filenumber '$input->filenumber' has status '$input->status_code' - import and overwrite existing ICCAM reference");

            if ($inputParentId == 0) $inputParentId = $input->id;

            $this->connectAsNewImport($reportId,$contentItem,$input);

            $this->connect2parent($input,$inputParentId);

            $this->analyzeICCAMinput($input);

            $this->addLogline("filenumber=$input->filenumber; $mainurltxt ICCAM reference; ReportId=$reportId, ContentId=$contentItem->contentId");
            $add_delivered = 1;

        }

        // set ICCAM reference to this SCART report
        $this->setSCARTreference($contentItem->contentId,$input->filenumber);

        return $add_delivered;
    }

    /**
     * Return true when report is in running state
     *
     * @param $status_code
     * @return bool
     */
    private function inRunningState($status_code) {

        return (in_array($status_code,
            [SCART_STATUS_SCHEDULER_CHECKONLINE,
                SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,
                SCART_STATUS_ABUSECONTACT_CHANGED,
                SCART_STATUS_FIRST_POLICE]));
    }

    /**
     * connect exisiting (double url) item
     *
     * @param $reportId
     * @param $contentItem
     * @param $input
     */
    private function connectAsNewImport($reportId,$contentItem,$input) {

        $reference = scartICCAMinterface::setICCAMreportID($reportId,$contentItem->contentId);
        if ($reference != $input->reference) {
            $input->logHistory(SCART_INPUT_HISTORY_ICCAM,$input->reference,$reference,"Import from ICCAM; set new reference");
            $input->reference = $reference;
        }
        $input->addExtrafield(SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_CLASSIFICATION,'no');

        if ($input->status_code != SCART_STATUS_GRADE) {
            $input->logHistory(SCART_INPUT_HISTORY_STATUS,$input->status_code,SCART_STATUS_GRADE,"Import from ICCAM; direct to classify");
        }
        // reset classify_status_code (always)
        $input->status_code = $input->classify_status_code = SCART_STATUS_GRADE;
        // new received_at
        $input->received_at = date('Y-m-d H:i:s',strtotime($contentItem->countryAssignmentDate));
        // ICCAM source
        $input->source_code = SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM;
        // remove old analyst
        $input->workuser_id = 0;
        // remove old classification
        Grade_answer::where('record_type',SCART_INPUT_TYPE)->where('record_id',$input->id)->delete();
        $input->grade_code = SCART_GRADE_UNSET;

        $input->save();

    }

    /**
     * Find specific contentId in ICCAM report items based on url
     *
     * @param $record
     * @return string
     */
    private function getICCAMcontentId($reportId,$url) {

        $contentId = '';
        if ($reportId) {
            $report = (new ScartICCAMapi())->getReport($reportId);
            if (isset($report->reportContents) && count($report->reportContents) > 0) {
                foreach ($report->reportContents as $reportContent) {
                    if ($reportContent->urlString == $url) {
                        $contentId = $reportContent->contentId;
                        break;
                    }
                }
            }
        }
        scartLog::logLine("D-ScartImportICCAMV3; got contentId '$contentId' based on reporId=$reportId and url=$url");
        return $contentId;
    }

    private function insertContent($reportId,$contentId,$contentItem,$reportGeneral,$inputParentId) {

        scartLog::logLine("D-ScartImportICCAMV3; insertContent; reportId=$reportId, contentId=$contentId, inputParentId=$inputParentId");

        $input = false;

        try {

            $input = new Input();

            // check if siteType is set -> convert to SCART siteType value
            $siteTypeId = (isset($reportGeneral->sourceUrlSiteType)) ? $reportGeneral->sourceUrlSiteType : scartICCAMfieldsV3::$siteTypeIDWebsite;
            $type_code = scartICCAMinterface::getSiteType($siteTypeId);

            $input->url = $contentItem->url->urlString;
            // Note: we don't get the mediaType
            $input->url_type = ($inputParentId==0) ? SCART_URL_TYPE_MAINURL : SCART_URL_TYPE_IMAGEURL;
            $input->note = ($contentItem->memo!=null) ? $contentItem->memo : '';
            if ($input->note=='' && $reportGeneral->memo!=null && $inputParentId==0) $input->note = $reportGeneral->memo;
            $input->reference = scartICCAMinterface::setICCAMreportID($reportId,$contentId);
            $input->url_referer = ($reportGeneral->referrerUrl!=null) ? $reportGeneral->referrerUrl : '';
            $input->workuser_id = 0;
            $input->type_code = $type_code;
            $input->source_code = SCART_ICCAM_IMPORT_SOURCE_CODE_ICCAM;
            // illegal=default when here
            $input->grade_code = SCART_GRADE_ILLEGAL;

            /*
             * Note from Kalina Zografsk from INHOPE:
             *
             * - (report)-> hotlineReceivedDate  -> the date the public reported this content to the hotline
             * - (content)-> countryAssignmentDate -> the date the content item was assigned to a country.
             *   We use assignment and hosting separately because of orphan reports, i.e. we could have a content
             *   item in country where INHOPE does not have a country, so hosting is set to Moldova for example
             *   but it can be assigned for actioning to the UK, for example.
             *
             */

            //$input->received_at = date('Y-m-d H:i:s',strtotime($reportGeneral->hotlineReceivedDate));
            $input->received_at = date('Y-m-d H:i:s',strtotime($contentItem->countryAssignmentDate));
            $input->status_code = SCART_STATUS_WORKING;     // working...
            // Note: validation of field will be done when saving -> when not valid, we leave (goto catch) here
            $input->save();

            $input->generateFilenumber();
            $input->save();

            // log old/new for history
            $input->logHistory(SCART_INPUT_HISTORY_STATUS,'',$input->status_code,"Imported from ICCAM (v3); start work");

            if ($input->reference) {
                $input->logHistory(SCART_INPUT_HISTORY_ICCAM,'',$input->reference,'ICCAM reference');
            }

            if ($inputParentId == 0) {
                // extra ICCAM iccamreport fields
                $input->addExtrafield( SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_HOTLINEID,$reportGeneral->reportingHotlineId);
                $input->addExtrafield( SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_ANALYST,$reportGeneral->reportingAnalystName);
            }

            $input->logText("Added url '$input->url' ($input->reference)");

            $this->addLogline("filenumber=$input->filenumber; import url '$input->url', ICCAM contentId=$contentItem->contentId, received=$input->received_at");
            $this->addLogline("filenumber=$input->filenumber; ICCAM reference; ReportId=$reportId, ContentId=$contentId");

            if (count($contentItem->assessments) > 0) {

                scartLog::logLine("D-ScartImportICCAMV3; found ICCAM assessment(s)");

                // Note: confirm by email from Kalina Zografska Inhope; use last assessment
                $assessment = $contentItem->assessments[count($contentItem->assessments) - 1];

                // convert iccam_id values to scart_codes
                $assessmentIccam2scart = [
                    'classification' => 'ClassificationID',
                    'ageCategorization' => 'AgeGroupID',
                    'genderCategorization' => 'GenderID',
                    'virtualContentCategorization' => 'IsVirtual',
                    'userGeneratedContentCategorization' => 'IsChildModeling',
                    'childModelingCategorization' => 'IsUserGC',
                ];
                // Use iccam_api_field conversion table
                foreach ($assessmentIccam2scart as $iccam_field => $scart_field) {
                    $iccamvalue= (isset($assessment->$iccam_field)) ? $assessment->$iccam_field : '';
                    $iccamfield = Iccam_api_field::where('scart_field',$scart_field)
                        ->where('iccam_id',$iccamvalue)
                        ->first();
                    $assessment->$scart_field = ($iccamfield) ? $iccamfield->scart_code : '';
                }

                $classification = $assessment->ClassificationID;
                if ($classification) {

                    // Note: may be not illegal with assessment also sent to SCART
                    if ($classification == scartICCAMfieldsV3::$ClassificationNotIllegal) {
                        $input->grade_code = SCART_GRADE_NOT_ILLEGAL;
                        $input->save();
                    }

                    // import classification

                    $questions = Grade_question::fetchClassifyQuestions($input->url_type);
                    if (count($questions) > 0) {
                        // fill specific ICCAM fields
                        foreach ($questions AS $question) {
                            $iccamfield = $question->iccam_field;
                            $iccamvalue = (isset($assessment->$iccamfield)?$assessment->$iccamfield:'');
                            if ($iccamvalue!=='') {
                                scartLog::logLine("D-ScartImportICCAMV3; set classification; $iccamfield = '$iccamvalue'");
                                scartICCAMfieldsV3::setClassificationICCAMfield($question,$input,$iccamvalue);
                            }
                        }
                        $input->logText("ICCAM assessment (classification) copied");
                        scartLog::logLine("D-ScartImportICCAMV3; copied ICCAM assessment to SCART record");
                        $this->addLogline("filenumber=$input->filenumber; ICCAM assessment copied");

                        $input->addExtrafield(SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_CLASSIFICATION,'yes');

                    } else {
                        scartLog::logLine("W-ScartImportICCAMV3; no ICCAM question(s) found?!? ");
                    }

                } else {
                    scartLog::logLine("W-ScartImportICCAMV3; assessment given, but  classificationId not valid (or 0)  ");
                    $input->addExtrafield(SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_CLASSIFICATION,'no');
                    $input->grade_code = SCART_GRADE_UNSET;
                    $input->save();
                }

            } else {
                scartLog::logLine("D-ScartImportICCAMV3; no ICCAM assessments");
                $input->addExtrafield(SCART_INPUT_EXTRAFIELD_ICCAM,SCART_INPUT_EXTRAFIELD_ICCAM_CLASSIFICATION,'no');
                $input->grade_code = SCART_GRADE_UNSET;
                $input->save();
            }

            if ($inputParentId == 0) $inputParentId = $input->id;
            $this->connect2parent($input,$inputParentId);

            $this->analyzeICCAMinput($input);

        } catch (\Exception $err) {

            scartLog::logLine("E-ScartImportICCAMV3; insertContent($reportId,$contentId); exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            $input = false;

        }

        // return filled record
        return $input;
    }

    /**
     * Connect item to parent
     *
     * @param $input
     * @param $inputParentId
     */
    private function connect2parent($input,$inputParentId) {

        // connect in input_parent table -> also parent itself
        $this->addLogline("filenumber=$input->filenumber; Connected to parentId: $inputParentId");

        $iteminp = Input_parent::where('parent_id',$inputParentId)->where('input_id',$input->id)->first();
        if (!$iteminp) {
            $iteminp = new Input_parent();
            $iteminp->parent_id = $inputParentId;
            $iteminp->input_id = $input->id;
            $iteminp->save();
        }

    }

    /**
     * Analyze; get WhoIs and image data
     *
     * @param $input
     */
    private function analyzeICCAMinput($input) {

        // ** WhoIs information **

        scartLog::logLine("D-analyzeICCAMinput; get WhoIs info");

        try {

            $whois = scartWhois::getHostingInfo($input->url);
            if ($whois['status_success']) {

                $url = parse_url($input->url);

                $input->url_ip = (isset($whois['domain_ip']) ? $whois['domain_ip'] : '');
                $input->url_host = (isset($url['host']) ? $url['host'] : '');
                $input->registrar_abusecontact_id = $whois[SCART_REGISTRAR.'_abusecontact_id'];
                $input->host_abusecontact_id = $whois[SCART_HOSTER.'_abusecontact_id'];
                // add proxy_abusecontact_id if set
                $input = Abusecontact::fillProxyservice($input,$whois);
                $input->save();

                // log history
                $input->logHistory(SCART_INPUT_HISTORY_HOSTER,'',$whois[SCART_HOSTER.'_abusecontact_id'],"Found hoster");
                $input->logHistory(SCART_INPUT_HISTORY_IP,'',$input->url_ip,"Found IP");

                $input->logText("Whois information; " . $whois['status_text'] .
                    "; registrar_owner=".$whois['registrar_owner'].
                    ", host_owner=".$whois['host_owner'].
                    ", proxy_abusecontact_id=".$input->proxy_abusecontact_id.
                    ", country=".$whois['host_country']);

                $this->addLogline("filenumber=$input->filenumber; WhoIs info hoster=".$whois['host_owner'].", hosting country=".$whois['host_country']);

            } else {

                $input->logText("Warning WhoIs; looking up: " . $whois['status_text'] );
                $this->addLogline("filenumber=$input->filenumber; found NO WhoIs info");

            }

        } catch (\Exception $err) {

            scartLog::logLine("E-analyzeICCAMinput; get WhoIs exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            $input->logText("Warning WhoIs; error during WhoIslooking up ");
            $this->addLogline("filenumber=$input->filenumber; error during WhoIs lookup");

        }


        // ** get image(s) **

        scartLog::logLine("D-analyzeICCAMinput; get image/video/screenshot");

        try {

            // get images -> screenshot if mainurl
            $images = scartBrowser::getImages($input->url, $input->url_referer,($input->url_type==SCART_URL_TYPE_MAINURL));
            $imgcnt = count($images);
            scartLog::logLine("D-analyzeICCAMinput; filenumber=$input->filenumber, type=$input->url_type; found $imgcnt image(s)");

            // get image or screenshot

            $imagedata = null; $imagetype = '';

            foreach ($images AS $image) {

                $src = $image['src'];
                $hash = $image['hash'];

                // go look for image and/or screenshot (type=mainurl)

                if ($image['type'] == SCART_URL_TYPE_IMAGEURL && $image['src'] == $input->url) {

                    // url = imageurl

                    $imagedata = $image;
                    $imagedata['hash'] = $hash;
                    $imagetype = 'image/video';

                    if ($input->url_type != SCART_URL_TYPE_MAINURL) {
                        break;
                    }


                } elseif ($image['type'] == SCART_URL_TYPE_SCREENSHOT) {

                    if ($input->url_type == SCART_URL_TYPE_MAINURL) {

                        $imagedata = $image;
                        $imagedata['hash'] = $hash;
                        $imagetype = 'screenshot';
                        break;
                    }

                } else {
                    scartLog::logDump("D-analyzeICCAMinput; skip image '$src'; type=",$image['type']);
                }

            }

            if ($imagedata != null) {

                $input->url_hash = $imagedata['hash'];
                $input->url_base = $imagedata['base'];
                $input->url_host = $imagedata['host'];
                $input->url_image_width = $imagedata['width'];
                $input->url_image_height = $imagedata['height'];

                $input->hashcheck_at = date('Y-m-d H:i:s');
                $input->hashcheck_format = scartHASHcheck::getFormat();
                $input->hashcheck_return = scartHASHcheck::inDatabase($image['data']);

                // ** hashcheck **

                if ($input->hashcheck_return) {

                    $input->logText("Found url in HASH database - set classify illegal");

                    // ILLEGAL

                    $input->grade_code = SCART_GRADE_ILLEGAL;
                    $settings = scartHASHcheck::getClassification();
                    // set classification based on setting array
                    if ($settings['police_first'][0] == 'y') {
                        $input->classify_status_code = SCART_STATUS_FIRST_POLICE;
                    }
                    $input->type_code = $settings['type_code_illegal'][0];
                    // set classify
                    foreach ($settings['grades'] AS $answer) {
                        $clone = new Grade_answer();
                        $clone->record_id = $input->id;
                        $clone->record_type = SCART_INPUT_TYPE;
                        $clone->grade_question_id = $answer['grade_question_id'];
                        $clone->answer = serialize($answer['answer']);
                        $clone->save();
                    }

                }

                $input->save();

                $input->logText("image type '$imagetype' added");
                $this->addLogline("filenumber=$input->filenumber; image type '$imagetype'  ");

            } else {

                $input->url_base = scartBrowser::parse_base($input->url);
                $input->url_host = scartBrowser::get_host($input->url_base);

                $input->logText("Could not find image/video/screenshot");
                $this->addLogline("filenumber=$input->filenumber; image not found (?)");

                // send here action SCART_ICCAM_ACTION_CU to ICCAM? -> done by workflow CLASSIFY + analyst gives NOT FOUND

            }

        } catch (\Exception $err) {

            scartLog::logLine("E-analyzeICCAMinput; get-images exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage() );
            $input->logText("Warning; can not process image url=$src - message: " . $err->getMessage());

        }

    }

    /**
     * set SCART reference in ICCAM hotline reference field
     *
     * @param $contentId
     * @param $filenumber
     */
    private function setSCARTreference($contentId,$filenumber) {

        //Direct action here so import is optimized

        //ICCAMcurl::setDebug(true);
        $scartreference = $filenumber.'_'.date('YmdHi');
        $this->addPosts([
            'contentId' => $contentId,
            // make always unique
            'scartreference' => $scartreference,
        ]);
        scartLog::logLine("D-ScartImportICCAMV3; contentId=$contentId; set ICCAM hotline reference on $scartreference");
        $result = (new ScartICCAMapi())->putContentHotlineReference($contentId, $scartreference);
        //ICCAMcurl::setDebug(false);
    }

    /**
     * @param int $iccamtimeframe
     * @return string
     */
    private function gethotlineAssignmentDate(int $iccamtimeframe = 30)
    {
        // Note: use UTC date/time
        $currentutc = time() - date('Z');
        $currentdate = date('Y-m-d H:00:00',$currentutc);
        if ($iccamtimeframe < 60 && date('i') >= 30) $currentdate = date('Y-m-d H:30:00',$currentutc);

        if (($lastdate = scartICCAMinterface::getImportlast()) ) {
            $log = "D-ScartImportICCAMV3; currentdate (utc)=$currentdate, lastDate (utc)=$lastdate; date('Z)'=".date('Z');
        } else {
            $lastdate = $currentdate;
            scartICCAMinterface::saveImportLast($lastdate);
            $log = "D-ScartImportICCAMV3; init lastDate (utc) on: $lastdate";
        }

        scartLog::logLine($log);
        return ['currentdate' => $currentdate, 'lastdate' => $lastdate];
    }


    private function saveNexthotlineAssignmentStartDate($hotlineAssignmentDate, $iccamtimeframe) {

        if ($hotlineAssignmentDate['lastdate'] < $hotlineAssignmentDate['currentdate']) {
            // set on next 'iccamtimeframe' minutes
            $lastdate = date('Y-m-d H:i:00', strtotime('+'.$iccamtimeframe.' minutes', strtotime($hotlineAssignmentDate['lastdate'])));
            scartICCAMinterface::saveImportLast($lastdate);
            scartLog::logLine("D-ScartImportICCAMV3; currentdate={$hotlineAssignmentDate['currentdate']}, set lastDate on next hour: $lastdate");
        }

    }
}
