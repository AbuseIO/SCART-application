<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\iccam\scartICCAMinterface;
use abuseio\scart\models\Abusecontact;
use Flash;
use BackendMenu;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Input;

class Police extends scartController
{
    public $requiredPermissions = ['abuseio.scart.police'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Police');
    }

    /**
     * Filter on status_code=grade
     *
     * @param $query
     */
    public function listExtendQuery($query) {
        $query->where('status_code',SCART_STATUS_FIRST_POLICE);
    }

    public function onCheckNTD() {

        $checked = input('checked');
        if ($checked) {
            foreach ($checked AS $check) {
                $record = Input::find($check);
                if ($record) {

                    // reset online counter (direct NTD to ISP)
                    $record->online_counter = 0;

                    // take into account manual checkonline
                    if ($record->classify_status_code != SCART_STATUS_SCHEDULER_CHECKONLINE && $record->classify_status_code != SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL)
                        $record->classify_status_code = SCART_STATUS_SCHEDULER_CHECKONLINE;

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,$record->classify_status_code,"Goto checkonline by analist in POLICE function");

                    $record->status_code = $record->classify_status_code;
                    $record->save();
                    scartLog::logLine("D-Filenumber=$record->filenumber, url=$record->url, set on $record->status_code");
                }
            }
            Flash::info('NTD process started for selected url(s)');
            return $this->listRefresh();
        } else {
            Flash::warning('No url(s) selected');
        }
    }

    public function onClose() {

        $checked = input('checked');
        if ($checked) {
            foreach ($checked as $check) {
                $record = Input::find($check);
                if ($record) {

                    // log old/new for history
                    $record->logHistory(SCART_INPUT_HISTORY_STATUS,$record->status_code,SCART_STATUS_CLOSE,"Close by analist in POLICE function");
                    $record->status_code = SCART_STATUS_CLOSE;
                    $record->save();
                    $record->logText("Manual set on $record->status_code");

                    scartLog::logLine("D-Filenumber=$record->filenumber, grade=$record->grade_code, url=$record->url, set on $record->status_code");

                    if ($record->grade_code == SCART_GRADE_ILLEGAL) {

                        // check if ICCAM active (for this record)

                        if (scartICCAMinterface::isActive()) {
                            if (scartICCAMinterface::hasICCAMreportID($record->reference)) {
                                // get hoster
                                $abusecontact = Abusecontact::find($record->host_abusecontact_id);
                                if ($abusecontact) {
                                    $country = $abusecontact->abusecountry;
                                    // check if hoster local
                                    if (scartGrade::isLocal($country)) {
                                        // local -> then content removed (CR)
                                        $action = SCART_ICCAM_ACTION_CR;
                                        $reason = 'ContentNotFound';
                                    } else {
                                        // not local -> content moved (MO)
                                        $action = SCART_ICCAM_ACTION_MO;
                                        $reason = 'SCART content moved to '.$country;
                                    }
                                    $record->logText("POLICE function set CLOSE illegal content; inform ICCAM about '$reason'");
                                    scartICCAMinterface::addExportAction(SCART_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                        'record_type' => class_basename($record),
                                        'record_id' => $record->id,
                                        'object_id' => $record->reference,
                                        'action_id' => $action,
                                        'country' => $country,
                                        'reason' => $reason,
                                    ]);
                                }
                            }
                        }

                    }

                }
            }
            Flash::info('Selected url(s) set on closed');
            return $this->listRefresh();
        } else {
            Flash::warning('No url(s) selected');
        }
    }

    // if form display

    public function onShowImage() {

        $id = input('id');
        $record = Input::find($id);
        if ($record) {
            $msgclass = 'success';
            $msgtext = 'Image loaded';
            $src = scartBrowser::getImageCache($record->url,$record->url_hash);
        } else {
            $msgclass = 'error';
            $msgtext = 'Image NOT found!?';
            scartLog::logLine("E-".$msgtext);
            $src = '';
        }
        $txt = $this->makePartial('show_image', ['src' => $src, 'msgtext' => $msgtext, 'msgclass' => $msgclass] );
        return ['show_result' => $txt ];
    }

}
