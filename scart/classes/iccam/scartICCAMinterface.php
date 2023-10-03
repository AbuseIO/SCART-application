<?php
namespace abuseio\scart\classes\iccam;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_parent;
use abuseio\scart\Models\Maintenance;
use abuseio\scart\models\Systemconfig;

use abuseio\scart\classes\iccam\api2\scartExportICCAM;
use abuseio\scart\classes\iccam\api2\scartImportICCAM;
use abuseio\scart\classes\iccam\api2\scartICCAMfields;

use abuseio\scart\classes\iccam\api3\classes\ScartExportICCAMV3;
use abuseio\scart\classes\iccam\api3\classes\ScartImportICCAMV3;
use abuseio\scart\classes\iccam\api3\models\scartICCAMfieldsV3;

use abuseio\scart\classes\iccam\Exceptions\IccamException;

use abuseio\scart\models\ImportExport_job;

class scartICCAMinterface {

    public static function getVersion() {
        return Systemconfig::get('abuseio.scart::iccam.version', 'v2');
    }

    /** general active or not **/

    public static function isActive() {
        return Systemconfig::get('abuseio.scart::iccam.active', false);
    }

    public static function maintenance() {
        // check if no maintenance of ICCAM import/export

        $active = Systemconfig::get('abuseio.scart::scheduler.importexport.iccam_active', false);
        if (!$active){
            scartLog::logLine("D-scartICCAMinterface; import/export interface ICCAM OFF (maintenance)");
            $bool = true;
        } else {
            scartLog::logLine("D-scartICCAMinterface; import/export interface ICCAM ON");
            $bool = false;
        }
        return $bool;
    }

    /** general get & set ICCAM reference field **/

    public static $_ICCAMreference = ' (ICCAM)';

    public static function getICCAMreportID($reference) {
        // reference <reportID>#<contentID> (ICCAM)
        $reference = trim(str_replace(self::$_ICCAMreference,'',$reference));
        $refarr = explode('#',$reference);
        $reportID = (!empty($refarr[0])?$refarr[0]:'');
        return $reportID;
    }

    public static function getICCAMcontentID($reference) {
        // reference <reportID>#<contentID> (ICCAM)
        $reference = trim(str_replace(self::$_ICCAMreference,'',$reference));
        $refarr = explode('#',$reference);
        $contentID = (isset($refarr[1])?$refarr[1]:'');
        return $contentID;
    }

    public static function hasICCAMreportID($reference) {
        // reference <reportID>#<contentID> (ICCAM)
        if (!is_string($reference)) $reference = '';
        return (trim($reference) !== '');
    }

    public static function setICCAMreportID($reportID,$contentID='') {
        $ref = $reportID . (($contentID!='')?'#'.$contentID:'');
        return $ref.self::$_ICCAMreference;
    }

    public static function findICCAMreport($reportID,$contentID='') {

        $reference = self::setICCAMreportID($reportID,$contentID);
        return (Input::where('reference',$reference)->first());
    }

    public static function alreadyICCAMreport($reportID,$contentID='') {

        $reference = self::setICCAMreportID($reportID,$contentID);
        return (Input::where('reference',$reference)->count() > 0);
    }

    public static function alreadyICCAMreportID($reportID) {

        return (Input::where('reference','LIKE',$reportID.'#%')->count() > 0);
    }

    /** Import/export **/

    public static function import()
    {
        try {
            if (!self::maintenance()) {
                if (self::getVersion() == 'v2') {
                    $iccam = new scartImportICCAM();
                } elseif (self::getVersion() == 'v3') {
                    $iccam = new ScartImportICCAMV3();
                } else {
                    throw new \Exception('Unknown ICCAM version set: '.self::getVersion());
                }
                $iccam->do();
            }
        }
        catch(IccamException $err) {
            $err->logErrorMessage();
        }
    }

    /**
     * @description  Export factory
     */
    public static function export()
    {
        try {
            if (!self::maintenance()) {
                if (self::getVersion() == 'v2') {
                    $iccam = new scartExportICCAM();
                } elseif (self::getVersion() == 'v3') {
                    $iccam = new ScartExportICCAMV3();
                } else {
                    throw new \Exception('Unknown ICCAM version set: '.self::getVersion());
                }
                $iccam->do();
            }
        }
        catch(IccamException $err) {
            $err->logErrorMessage();
        }
    }

    /** ICCAM FIELDS  **/

    public static function getICCAMsupportedFields() {

        // 2021-12-22 supported within question maintenance
        return [
            '' => '(not set)',
            'ClassificationID' => 'ClassificationID',
            'GenderID' => 'GenderID',
            'AgeGroupID' => 'AgeGroupID',
            'CommercialityID' => 'CommercialityID',
            'PaymentMethodID' => 'PaymentMethodID',
            'ContentType' => 'ContentType',
            'IsVirtual' => 'IsVirtual',
            'IsChildModeling' => 'IsChildModeling',
            'IsUserGC' => 'IsUserGC',
        ];
    }

    public static function getICCAMfieldOptions($iccam_field) {

        $geticcamoptions = 'get'.$iccam_field.'Options';
        scartLog::logLine("D-scartICCAMinterface; getICCAMfieldOptions($iccam_field) -> call '$geticcamoptions'");
        if (self::getVersion() == 'v2') {
            $options = scartICCAMfields::$geticcamoptions();
        } elseif (self::getVersion() == 'v3') {
            $options = scartICCAMfieldsV3::$geticcamoptions();
        } else {
            throw new \Exception('Unknown ICCAM version set: '.self::getVersion());
        }
        return $options;
    }

    public static function getSiteTypeID($record)
    {

        if (self::getVersion() == 'v2') {
            $siteTypeId = scartICCAMfields::getSiteTypeID($record);
        } elseif (self::getVersion() == 'v3') {
            $siteTypeId = scartICCAMfieldsV3::getSiteTypeID($record);
        } else {
            throw new \Exception('Unknown ICCAM version set: ' . self::getVersion());
        }
        return $siteTypeId;
    }
    public static function getSiteType($siteTypeId) {

        if (self::getVersion() == 'v2') {
            $type_code = scartICCAMfields::getSiteType($siteTypeId);
        } elseif (self::getVersion() == 'v3') {
            $type_code = scartICCAMfieldsV3::getSiteType($siteTypeId);
        } else {
            throw new \Exception('Unknown ICCAM version set: ' . self::getVersion());
        }
        return $type_code;
    }


    /**  EXPORT (job) records **/

    /**
     * Add Export Action to importexport jobs
     *
     * Note: check if action already done for reportID
     *
     * @param $action
     * @param $data
     */
    public static function addExportAction($action,$data) {

        // unique checksum each record -> onetime action for reportID
        $checksum = $data['record_type'].'-'.$data['record_id'].(isset($data['action_id'])? '-'.$data['action_id'] : '');

        // check if not already, including trash
        $cnt = ImportExport_job::where('interface',SCART_INTERFACE_ICCAM)
            ->where('action',$action)
            ->where('checksum',$checksum)
            ->withTrashed()
            ->count();

        if ($cnt == 0) {
            // create if
            $export = new ImportExport_job();
            $export->interface = SCART_INTERFACE_ICCAM;
            $export->action = $action;
            $export->checksum = $checksum;
            $export->data = serialize($data);
            $export->status = SCART_IMPORTEXPORT_STATUS_EXPORT;
            $export->save();
        } else {
            scartLog::Logline("W-scartICCAMinterface; addExportAction; action=$action, checksum=$checksum already added ");
        }
    }

    static function delExportActionChecksum($action,$record_id) {

        // unique checksum each record -> onetime action for reportID
        $checksum = 'Input-'.$record_id;
        // delete WITH trash
        $jobdel = ImportExport_job::where('interface',SCART_INTERFACE_ICCAM)
            ->where('action',$action)
            ->where('checksum',$checksum)
            ->withTrashed()
            ->delete();
    }

    static function delExportAction($job) {
        $jobdel = ImportExport_job::find($job['job_id']);
        if ($jobdel) $jobdel->delete();
    }

    static function getExportActions($take) {

        $exports = ImportExport_job::where('interface',SCART_INTERFACE_ICCAM)
            ->whereIn('action',[SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT,SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION])
            ->orderBy('id','asc')
            ->take($take);
        $exports = $exports->get();
        $jobs = [];
        $exports->each(function($export) use (&$jobs) {
            $jobs[] = [
                'job_id' => $export->id,
                'timestamp' => $export->updated_at,
                'action' => $export->action,
                'data' => unserialize($export->data),
            ];
        });
        return $jobs;
    }

    /** LAST TIME IMPORT  **/

    static function getImportlast() {

        $importjob = ImportExport_job::where('interface',SCART_INTERFACE_ICCAM)
            ->where('action',SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE)
            ->first();
        return (($importjob) ? $importjob->data : '');
    }

    static function saveImportLast($lastdate) {

        $importjob = ImportExport_job::where('interface',SCART_INTERFACE_ICCAM)
            ->where('action',SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE)
            ->first();
        if ($importjob) {
            if ($importjob->data != $lastdate) {
                $importjob->data = $lastdate;
                $importjob->save();
            }
        } else {
            $importjob = new ImportExport_job();
            $importjob->interface = SCART_INTERFACE_ICCAM;
            $importjob->action = SCART_INTERFACE_ICCAM_ACTION_IMPORTLASTDATE;
            $importjob->data = $lastdate;
            $importjob->save();
        }
    }

    /** Handle action after classification **/

    public static function exportReport($record) {

        // different flow for v2 and v3 version
        if (self::getVersion() == 'v2') {

            if ($record->grade_code==SCART_GRADE_ILLEGAL) {

                if ($record->reference == '') {
                    // init ICCAM export report -> always inform ICCAM about illegal record
                    scartLog::logLine("D-scartICCAMinterface; [$record->filenumber] export new report"  );
                    scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT, [
                        'record_type' => class_basename($record),
                        'record_id' => $record->id,
                    ]);
                } elseif ($record->online_counter == 0) {
                    // first time here -> update ICCAM
                    scartLog::logLine("D-scartICCAMinterface; [$record->filenumber] export update report"  );
                    scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT, [
                        'record_type' => class_basename($record),
                        'record_id' => $record->id,
                    ]);
                }

            } else {

                if (scartICCAMinterface::hasICCAMreportID($record->reference)) {
                    scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                        'record_type' => class_basename($record),
                        'record_id' => $record->id,
                        'object_id' => $record->reference,
                        'action_id' => SCART_ICCAM_ACTION_NI,     // NOT_ILLEGAL
                        'country' => '',                          // hotline default
                        'reason' => 'SCART reported NI',
                    ]);
                }

            }

        } elseif (self::getVersion() == 'v3') {

            // send ONLY mainurl record to Export -> in Export mainurl is hanlded with items in one flow

            if ($record->url_type==SCART_URL_TYPE_MAINURL && $record->online_counter == 0) {
                if (!empty($record->host_abusecontact_id)) {
                    scartLog::logLine("D-scartICCAMinterface (v3); exportReport [$record->filenumber] is MAINURL; add action exportReport"  );
                    scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT, [
                        'record_type' => class_basename($record),
                        'record_id' => $record->id,
                    ]);
                } else {
                    scartLog::logLine("W-scartICCAMinterface (v3); exportReport [$record->filenumber] has NO hoster set - skip export"  );
                }
            } else {
                if ($record->online_counter == 0) {
                    $parent = Input_parent::where('input_id',$record->id)->first();
                    $parent = Input::find($parent->parent_id);
                    $parentfilenumber = ($parent) ? $parent->filenumber : '(unknown?!)';
                    $parentreference = ($parent) ? $parent->reference : '(not yet set)';
                    scartLog::logLine("D-scartICCAMinterface (v3); exportReport [$record->filenumber] is NO mainurl - is part of a mainurl filenumber '$parentfilenumber' with reference '$parentreference'");
                } else {
                    scartLog::logLine("D-scartICCAMinterface (v3); exportReport [$record->filenumber] already added (online_counter=$record->online_counter)");
                }
            }

            // for v3 the NotIllegal action are done AFTER assessment -> required by ICCAM API v3 -> see SCART_INTERFACE_ICCAM_ACTION_EXPORTREPORT in scartExportICCAMV3

        }

    }

}
