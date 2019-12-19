<?php namespace ReporterTool\EOKM\Controllers;

use reportertool\eokm\classes\ertController;
use Backend\Facades\BackendAuth;
use Backend\Models\UserRole;
use BackendMenu;
use Config;
use Db;
use ReporterTool\EOKM\Models\Grade_status;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Input_status;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Notification_status;
use ReporterTool\EOKM\Models\Ntd;

class Startpage extends ertController
{
    public $implement = [
        ];

    /**
     * @var array Permissions required to view this page.
     */
    public $requiredPermissions = ['reportertool.eokm.startpage'];

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'Startpage');
        
    }

    /**
     * Own index
     */
    public function index() {

        $this->pageTitle = 'Startpage';

        $this->bodyClass = 'compact-container ';

        $this->vars['release'] = Config::get('reportertool.eokm::release.version', '0.0a') . ' - ' . Config::get('reportertool.eokm::release.build', 'UNKNOWN');

        // INPUTS
        $stss = Input_status
            ::where('deleted_at',null)
            ->orderBY('sortnr','asc')
            ->get();
        $inputsts = [];
        $inputcnt = 0;
        foreach ($stss AS $sts) {
            $cnt = Input
                ::where('status_code',$sts->code)
                ->count();
            $inputcnt += $cnt;
            $inputsts[] = [
                'status' => $sts->description,
                'count' => $cnt,
            ];
        }
        $this->vars['inputsts'] = $inputsts;
        $this->vars['inputcnt'] = $inputcnt;

        // IMAGES
        $stss = Notification_status
            ::where('deleted_at',null)
            ->orderBY('sortnr','asc')
            ->get();
        $notificationsts = [];
        $notificationcnt = 0;
        foreach ($stss AS $sts) {
            $cnt = Notification
                ::where('status_code',$sts->code)
                ->count();
            $notificationcnt += $cnt;
            $notificationsts[] = [
                'status' => $sts->description,
                'count' => $cnt,
            ];
        }
        $this->vars['notificationsts'] = $notificationsts;
        $this->vars['notificationcnt'] = $notificationcnt;

        // CLASSIFICATION
        $stss = Grade_status
            ::where('deleted_at',null)
            ->orderBY('sortnr','asc')
            ->get();
        $classificationsts = [];
        $classificationcnt = 0;
        foreach ($stss AS $sts) {
            $cnt = Notification
                ::where('grade_code',$sts->code)
                ->count();
            $classificationcnt += $cnt;
            $classificationsts[] = [
                'status' => $sts->description,
                'count' => $cnt,
            ];
        }
        $this->vars['classificationsts'] = $classificationsts;
        $this->vars['classificationcnt'] = $classificationcnt;

        // IMAGE ONLINE
        $notcounts = [
            'Still online > 50' => [50,99999999],
            'Still online 30-49' => [30,49],
            'Still online 10-29' => [10,29],
            'Online 0-9' => [0,9],
            'Total' => [0,99999999],
        ];
        $nots = [];
        $tot = 0;
        foreach ($notcounts AS $label => $onlinecnt) {
            $cnt = Notification
                ::where('status_code',ERT_STATUS_SCHEDULER_CHECKONLINE)
                ->where('online_counter','<=',$onlinecnt[1])
                ->where('online_counter','>=',$onlinecnt[0])
                ->count();
            $nots[$label] = $cnt;
            $tot += $cnt;
        }
        $this->vars['onlinecheckcnt'] = $tot;
        $this->vars['nots'] = $nots;

        // NTD's
        $ntdcounts = [
            'Success' => ERT_NTD_STATUS_SENT_SUCCES,
            'Failed' => ERT_NTD_STATUS_SENT_FAILED,
            'Queued' => ERT_NTD_STATUS_QUEUED,
            'Grouping' => ERT_NTD_STATUS_GROUPING,
            'Close' => ERT_NTD_STATUS_CLOSE,
        ];
        $ntds = [];
        $tot = 0;
        foreach ($ntdcounts AS $label => $status_code) {
            if (is_array($status_code)) {
                $cnt = Ntd
                    ::whereIn('status_code',$status_code)
                    ->count();

            } else {
                $cnt = Ntd
                    ::where('status_code',$status_code)
                    ->count();
            }
            $ntds[$label] = $cnt;
            $tot += $cnt;
        }
        $this->vars['ntdscnt'] = $tot;
        $this->vars['ntds'] = $ntds;

        //trace_sql();
        $providers = Notification
            ::where('grade_code',ERT_GRADE_ILLEGAL)
            ->join('reportertool_eokm_abusecontact','reportertool_eokm_abusecontact.id','=','host_abusecontact_id')
            ->select(Db::raw('count(*) AS illegal, reportertool_eokm_abusecontact.owner AS name'))
            ->groupBY('host_abusecontact_id')
            ->orderBy('illegal','desc')
            ->take('10')
            ->get();

        $this->vars['providers'] = $providers;

    }

}
