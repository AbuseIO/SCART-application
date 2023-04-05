<?php namespace abuseio\scart\Controllers;

use abuseio\scart\classes\base\scartController;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\mail\scartAlerts;
use abuseio\scart\classes\online\scartCheckOnline;
use abuseio\scart\classes\parallel\scartRealtimeCheckonline;
use abuseio\scart\classes\parallel\scartRealtimeMonitor;
use abuseio\scart\classes\scheduler\scartSchedulerCheckOnline;
use Backend\Facades\BackendAuth;
use Backend\Models\UserRole;
use BackendMenu;
use Config;
use Db;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Grade_status;
use abuseio\scart\models\Input;
use abuseio\scart\models\Input_status;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Systemconfig;
use Illuminate\Support\Facades\Redirect;

class Startpage extends scartController
{
    public $implement = [
        ];

    /**
     * @var array Permissions required to view this page.
     */
    public $requiredPermissions = ['abuseio.scart.startpage'];

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Startpage');

    }

    /**
     * Own index
     */
    public function index() {

        $this->pageTitle = 'Startpage';

        $this->bodyClass = 'compact-container ';

        $this->vars['release'] = Systemconfig::get('abuseio.scart::release.version', '0.0a') . ' - ' . Systemconfig::get('abuseio.scart::release.build', 'UNKNOWN');
        $this->vars['title'] = Systemconfig::get('abuseio.scart::release.title', 'Classify & Reporting Tool');

        // Note: In the cleanup job each night the dashboard data is reloaded into cache
        //trace_sql();
        //$this->resetLoadCache();
        //scartLog::logLine("isLocal(nl) = " . scartGrade::isLocal('nl'));

        $cacheReload = $this->getCache('cacheLoad');
        $this->vars['cacheReload'] = $cacheReload;

        // INPUTS
        $statusInput = $this->getStatusInputs();
        $this->vars['inputsts'] = $statusInput['inputsts'];
        $this->vars['inputcnt'] = $statusInput['inputcnt'];

        // CHECKONLINE
        $status = $this->getStatusCheckonline();
        $this->vars['checkonlinests'] = $status['checkonlinests'];
        $this->vars['realtimests'] = (isset($status['realtimests'])?$status['realtimests'] : []);
        $this->vars['checkonlineadm'] = (scartUsers::isScartAdmin() || scartUsers::isAdmin());

        // IMAGES
        $statusImage = $this->getStatusImages();
        $this->vars['notificationsts'] = $statusImage['imagests'];
        $this->vars['notificationcnt'] = $statusImage['imagecnt'];

        // CLASSIFICATION
        $statusClassification = $this->getStatusClassification();
        $this->vars['classificationsts'] = $statusClassification['classificationsts'];
        $this->vars['classificationcnt'] = $statusClassification['classificationcnt'];

        // ONLINE
        $statusOnline = $this->getStatusOnline();
        $this->vars['onlinests'] = $statusOnline['onlinests'];
        $this->vars['onlinecnt'] = $statusOnline['onlinecnt'];

        // NTD's
        $statusNtds = $this->getStatusNtds();
        $this->vars['ntdsts'] = $statusNtds['ntdsts'];
        $this->vars['ntdcnt'] = $statusNtds['ntdcnt'];

        // Providers
        $this->vars['providers'] = $this->getStatusProviders();

    }

    private $_cacheprefix = 'startpage_';

    function getCache($name) {
        $data = scartUsers::getGeneralOption($this->_cacheprefix.$name);
        //scartLog::logLine("D-get($name)=" . print_r($data, true));
        return ($data) ? unserialize($data) : '';
    }

    function setCache($name,$data) {
        $data = ($data) ? serialize($data) : '';
        scartUsers::setGeneralOption($this->_cacheprefix.$name,$data);
        // last cache update
        scartUsers::setGeneralOption($this->_cacheprefix.'cacheLoad', serialize(date('Y-m-d H:i')) );
    }

    public function resetLoadCache() {
        $caches = [
            'statusInputs',
            'statusCheckonline',
            'statusImages',
            'statusClassification',
            'statusOnline',
            'statusNtds',
            'statusProviders',
            'statusInputWeek',
        ];
        scartLog::logLine("D-resetLoadCache; " . print_r($caches, true) );
        foreach ($caches AS $cache) {
            $this->setCache($cache,'');
            //scartLog::logLine("D-resetLoadCache($cache) " );
            call_user_func(array($this,'get'.ucfirst($cache)));
        }
        $currenty = date('Y');
        for ($year=($currenty - 3);$year<=$currenty;$year++) {
            scartLog::logLine("D-resetLoadCache; getStatusProvidersYear($year)");
            $this->setCache('statusProviders'.$year,'');
            call_user_func(array($this,'getStatusProvidersYear'),$year);
        }
    }

    public function getStatusInputs() {

        $statusInputs = $this->getCache('statusInputs');
        if ($statusInputs=='') {
            /*
            $stss = Input_status
                ::where('deleted_at',null)
                ->orderBY('sortnr','asc')
                ->get();
            */
            $stss = [
                'Open' => SCART_STATUS_OPEN,
                'Scrape & analyze whois' => SCART_STATUS_SCHEDULER_SCRAPE,
//                'Working' => SCART_STATUS_WORKING,
                'Cannot scrape and/or get whois info' => SCART_STATUS_CANNOT_SCRAPE,
                'To classify' => SCART_STATUS_GRADE,
            ];
            $inputsts = [];
            $inputcnt = 0;
            foreach ($stss AS $label => $code) {
                $cnt = Input
                    ::where('status_code',$code)
                    ->where('url_type',SCART_URL_TYPE_MAINURL)
                    ->count();
                $inputcnt += $cnt;
                $inputsts[] = [
                    'status' => $label,
                    'count' => $cnt,
                ];
            }
            $statusInputs = [
                'inputsts' => $inputsts,
                'inputcnt' => $inputcnt,
            ];
            $this->setCache('statusInputs',$statusInputs);
        }
        return $statusInputs;
    }

    public function getStatusCheckonline() {

        $status = $this->getCache('statusCheckonline');
        if ($status=='') {

            $statussts = $realtimests = [];

            $mode = Systemconfig::get('abuseio.scart::scheduler.checkntd.mode',SCART_CHECKNTD_MODE_CRON);
            $moderealtime = ($mode == SCART_CHECKNTD_MODE_REALTIME);
            $statussts[] = [
                'status' => 'Current checkonline mode',
                'count' => "$mode",
                'icon' => '',
            ];

            // note: CRON then more then 8 hours as warning
            $lookagain = ($moderealtime) ? Systemconfig::get('abuseio.scart::scheduler.checkntd.realtime_look_again',120) : 480;

            $lastseen = scartSchedulerCheckOnline::lastseen();
            $lastseen = ($lastseen) ? $lastseen->lastseen_at : '';
            $lastseenago = ($lastseen) ? (time() - strtotime($lastseen)) : 0;
            $warning = ($lastseenago >= ($lookagain * 60));
            $statussts[] = [
                'status' => 'oldest lastseen of checkonline reports',
                'count' => "$lastseen",
                'icon' => (!$warning) ? 'success' : 'warning',
            ];
            $statussts[] = [
                'status' => 'oldest time ago (limit '.($lookagain).' minutes)',
                'count' => round($lastseenago / 60,0).' minutes',
                'icon' => (!$warning) ? 'success' : 'warning',
            ];
            if ($warning) {
                $lookagaintime = date('Y-m-d H:i:s', strtotime("-$lookagain minutes"));
                $lastseencnt = scartSchedulerCheckOnline::lastseenCount($lookagaintime);
                $statussts[] = [
                    'status' => 'number of old lastseen records',
                    'count' => $lastseencnt,
                    'icon' => 'warning',
                ];
            }

            if ($moderealtime) {

                $statussts[] = [
                    'status' => 'check each report (url) within',
                    'count' => $lookagain.' minutes',
                    'icon' => '',
                ];

                $avg = scartSchedulerCheckOnline::checkAvgTime();
                $statussts[] = [
                    'status' => 'avg checkonline time (WhoIs & browser)',
                    'count' => round($avg,2).' sec',
                    'icon' => '',
                ];
                $max = scartSchedulerCheckOnline::checkMaxTime();
                $min = scartSchedulerCheckOnline::checkMinTime();
                $statussts[] = [
                    'status' => 'max/min checkonline time (WhoIs & browser)',
                    'count' => round($max,2).'/'.round($min,2).' sec',
                    'icon' => '',
                ];

                $realtimests = scartRealtimeMonitor::realtimeStatus();


            } else {

                $statussts[] = [
                    'status' => 'checkonline batch every',
                    'count' => Systemconfig::get('abuseio.scart::scheduler.checkntd.check_online_every',15).' minutes',
                    'icon' => '',
                ];

                $count = Systemconfig::get('abuseio.scart::scheduler.checkntd.scheduler_process_count','');
                if ($count=='') $count = Systemconfig::get('abuseio.scart::scheduler.scheduler_process_count',15);
                $statussts[] = [
                    'status' => 'checkonline number of records in one batch',
                    'count' => $count,
                    'icon' => '',
                ];

                $countnormal = scartSchedulerCheckOnline::Normal(0)->count();
                $statussts[] = [
                    'status' => 'number of checkonline records',
                    'count' => $countnormal,
                    'icon' => '',
                ];


            }

            $status = [
                'checkonlinests' => $statussts,
                'realtimests' => $realtimests,
            ];
            $this->setCache('statusCheckonline',$status);
        }
        return $status;
    }

    public function getStatusInputWeek() {

        $statusInputWeek = $this->getCache('statusInputWeek');
        if ($statusInputWeek=='') {
            $day1 = date('Y-m-d');
            $day7 = date('Y-m-d', strtotime("-8 days"));
            //scartLog::logLine("D-between $day7 and $day1");
            $stss = Input
                ::where('received_at','>',$day7)
                ->where('received_at','<',$day1)
                ->groupBy(Db::raw('SUBSTRING(received_at,1,10)'))
                ->select(Db::raw('COUNT(*) AS count, SUBSTRING(received_at,1,10) AS received_at'))
                ->get();
            $statusInputWeek = [];
            foreach ($stss AS $sts) {
                $statusInputWeek[] = [
                    'date' => $sts->received_at,
                    'count' => $sts->count,
                ];
            }
            $this->setCache('statusInputWeek',$statusInputWeek);
        }
        return $statusInputWeek;
    }

    public function getStatusImages() {

        $statusImages = $this->getCache('statusImages');
        if ($statusImages=='') {
            $stss = [
                'To classify' => SCART_STATUS_GRADE,
                'First police' => SCART_STATUS_FIRST_POLICE,
                'Abusecontact changed' => SCART_STATUS_ABUSECONTACT_CHANGED,
                'Check if online' => SCART_STATUS_SCHEDULER_CHECKONLINE,
                'Manual checkonline' => SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,
                'Gone offline' => SCART_STATUS_CLOSE_OFFLINE,
                'Manual set offline' => SCART_STATUS_CLOSE_OFFLINE_MANUAL,
                'Closed' => SCART_STATUS_CLOSE,
            ];
            $imagests = [];
            $imagecnt = 0;
            foreach ($stss AS $desc => $sts) {
                $cnt = Input::where('status_code',$sts)
                    ->count();
                $imagecnt += $cnt;
                $imagests[] = [
                    'status' => $desc,
                    'count' => $cnt,
                ];
            }
            $statusImages =  [
                'imagests' => $imagests,
                'imagecnt' => $imagecnt,
            ];
            $this->setCache('statusImages',$statusImages);
        }
        return $statusImages;
    }

    public function getStatusClassification() {

        $statusClassification = $this->getCache('statusClassification');
        if ($statusClassification=='') {
            $stss = Grade_status::orderBY('sortnr','asc')
                ->get();
            $classificationsts = [];
            $classificationcnt = 0;
            foreach ($stss AS $sts) {
                // only status from approved records
                $cnt = Input
                    ::where('grade_code',$sts->code)
                    ->whereIn('status_code',
                        [SCART_STATUS_FIRST_POLICE,
                         SCART_STATUS_ABUSECONTACT_CHANGED,
                         SCART_STATUS_SCHEDULER_CHECKONLINE,
                         SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL,
                         SCART_STATUS_CLOSE,
                         SCART_STATUS_CLOSE_OFFLINE,
                         SCART_STATUS_CLOSE_OFFLINE_MANUAL])
                    ->count();
                $classificationcnt += $cnt;
                $classificationsts[] = [
                    'status' => $sts->description,
                    'count' => $cnt,
                ];
            }
            $statusClassification = [
                'classificationsts' => $classificationsts,
                'classificationcnt' => $classificationcnt,
            ];
            $this->setCache('statusClassification',$statusClassification);
        }
        return $statusClassification;
    }

    public function getStatusOnline() {

        $statusOnline = $this->getCache('statusOnline');
        if ($statusOnline=='') {
            // IMAGE ONLINE
            $notcounts = [
                'Still online > 50' => [50,99999999],
                'Still online 30-49' => [30,49],
                'Still online 10-29' => [10,29],
                'Online 0-9' => [1,9],
                'Total' => [1,99999999],
            ];
            $onlinests = [];
            $onlinecnt = 0;
            foreach ($notcounts AS $label => $onlinerange) {
                $cnt = Input
                    ::where('online_counter','<=',$onlinerange[1])
                    ->where('online_counter','>=',$onlinerange[0])
                    ->count();
                $onlinests[$label] = $cnt;
                $onlinecnt += $cnt;
            }
            $statusOnline =  [
                'onlinests' => $onlinests,
                'onlinecnt' => $onlinecnt,
            ];
            $this->setCache('statusOnline',$statusOnline);
        }
        return $statusOnline;
    }

    public function getStatusNtds() {

        $statusNtds = $this->getCache('statusNtds');
        if ($statusNtds=='') {
            $ntdcounts = [
                'Direct' => SCART_NTD_STATUS_QUEUE_DIRECTLY,
                'Direct police' => SCART_NTD_STATUS_QUEUE_DIRECTLY_POLICE,
                'Grouping' => SCART_NTD_STATUS_GROUPING,
                'Queued' => SCART_NTD_STATUS_QUEUED,
                'Failed' => SCART_NTD_STATUS_SENT_FAILED,
                'Success' => SCART_NTD_STATUS_SENT_SUCCES,
                'Close' => SCART_NTD_STATUS_CLOSE,
            ];
            $ntdsts = [];
            $ntdcnt = 0;
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
                $ntdsts[$label] = $cnt;
                $ntdcnt += $cnt;
            }
            $statusNtds = [
                'ntdsts' => $ntdsts,
                'ntdcnt' => $ntdcnt,
            ];
            $this->setCache('statusNtds',$statusNtds);
        }
        return $statusNtds;
    }

    public function getStatusProviders() {

        $statusProviders = $this->getCache('statusProviders');
        if ($statusProviders=='') {
            $statusProviders = Input
                ::where('grade_code',SCART_GRADE_ILLEGAL)
                ->join('abuseio_scart_abusecontact','abuseio_scart_abusecontact.id','=','host_abusecontact_id')
                ->select(Db::raw('count(*) AS illegal, abuseio_scart_abusecontact.owner AS name'))
                ->groupBY('abuseio_scart_abusecontact.owner')
                ->orderBy('illegal','desc')
                ->take('10')
                ->get();
            $statusProviders = $statusProviders->toArray();
            $this->setCache('statusProviders',$statusProviders);
        }
        return $statusProviders;
    }

    public function getStatusProvidersYear($year) {

        $statusProviders = $this->getCache('statusProviders'.$year);
        if ($statusProviders=='') {

            $yearstart = "$year-01-01 00:00:00";
            $yearend = "$year-12-31 23:59:59";

            $statusProviders = Input
                ::where('grade_code',SCART_GRADE_ILLEGAL)
                ->whereBetween('received_at',[$yearstart,$yearend])
                ->join('abuseio_scart_abusecontact','abuseio_scart_abusecontact.id','=','host_abusecontact_id')
                ->select(Db::raw('count(*) AS illegal, abuseio_scart_abusecontact.owner AS name'))
                ->groupBY('abuseio_scart_abusecontact.owner')
                ->orderBy('illegal','desc')
                ->take('10')
                ->get();
            $statusProviders = $statusProviders->toArray();
            $this->setCache('statusProviders'.$year,$statusProviders);
        }
        return $statusProviders;
    }

    public function onShowStatusProviders() {

        /**
         * Top-10 per jaar / maand
         *
         */

        $statusProviders = [];
        $endyear = date('Y');
        $statusProviders['providers'] = $this->getStatusProviders();
        $years = [];
        for ($y=2019;$y<=$endyear;$y++) {
            $statusProviders['providers'.$y] = $this->getStatusProvidersYear($y);
            $years[] = $y;
        }
        $statusProviders['years'] = $years;

            //trace_log($statusInputWeek);
        $dashboard = $this->makePartial('show_providers',$statusProviders);
        return ['dashboard_area' => $dashboard];
    }

    public function onShowInputWeek() {

        $statusInputWeek = $this->getStatusInputWeek();
        //trace_log($statusInputWeek);
        $dashboard = $this->makePartial('inputs_week',
            [
                'weeks' => $statusInputWeek
            ]);

        return ['dashboard_area' => $dashboard];
    }

    public function onUpdateCheckonline() {

        // clear
        $this->setCache('statusCheckonline','');

        return Redirect::refresh();
    }
}
