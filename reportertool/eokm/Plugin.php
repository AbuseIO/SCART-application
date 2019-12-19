<?php namespace ReporterTool\EOKM;

use App;
use Event;
use Db;
use Log;
use Config;
use reportertool\eokm\classes\ertSchedulerCleanup;
use reportertool\eokm\classes\ertSchedulerSendAlerts;
use Session;
use Request;
use Backend;
use Schema;
use BackendAuth;

use League\Flysystem\Exception;
use RuntimeException;
use ErrorException;
use October\Rain\Exception\SystemException;

use reportertool\eokm\classes\ertSchedulerAnalyseInput;
use reportertool\eokm\classes\ertSchedulerCheckNTD;
use reportertool\eokm\classes\ertSchedulerSendNTD;
use reportertool\eokm\classes\ertSchedulerImportExport;
use ReporterTool\EOKM\Models\Manual;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertBrowser;
use reportertool\eokm\classes\ertAnalyzeInput;

use Symfony\Component\Debug\Exception\FatalThrowableError;
use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use System\Classes\PluginManager;
use Backend\Models\User;

include_once (__DIR__ . '/includes/ertConstants.php');

class Plugin extends PluginBase {

    public function pluginDetails() {

        return [
            'name' => 'EOKM Reporter Tool (ERT)',
            'description' => 'Tool to investigate and report (child)abuse images',
            'author' => 'AbuseIO',
            'icon' => 'icon-check-square',
            'homepage' => '',
        ];
    }

    public function register() {

        //$this->registerConsoleCommand('reportertool.analyzeInputs', 'reportertool\eokm\console\analyzeInputs');
        //$this->registerConsoleCommand('reportertool.check', 'reportertool\eokm\console\checkOnline');
        $this->registerConsoleCommand('reportertool.iccamApi', 'reportertool\eokm\console\iccamApi');
        $this->registerConsoleCommand('reportertool.exportClassified', 'reportertool\eokm\console\exportClassified');

        $this->registerConsoleCommand('reportertool.whoisTest', 'reportertool\eokm\console\whoisTest');
        $this->registerConsoleCommand('reportertool.sendMailTest', 'reportertool\eokm\console\sendMailTest');
        $this->registerConsoleCommand('reportertool.readMailTest', 'reportertool\eokm\console\readMailTest');

        $this->registerConsoleCommand('reportertool.testDragon', 'reportertool\eokm\console\testDragon');

        $this->registerConsoleCommand('reportertool.multitest', 'reportertool\eokm\console\multitest');
    }

    public function registerSchedule($schedule) {

        // Task-1: analyse input(s)


        $schedule->call(function() {
            ertSchedulerAnalyseInput::doJob();
        })->name('ERT:AnalyseInput')->withoutOverlapping(5)->everyMinute();
//          })->name('ERT:AnalyseInput')->dailyAt('03:05');

        // Task-2: CheckNTD
        // NB: reset flag auto after 5min
        $schedule->call(function() {
            ertSchedulerCheckNTD::doJob();
        })->name('ERT:CheckNTD')->withoutOverlapping(5)->everyMinute();

        // Task-3: send NTD
        $schedule->call(function() {
            ertSchedulerSendNTD::doJob();
        })->name('ERT:SendNTD')->everyMinute();

        // Task-4; read import
        $schedule->call(function() {
            ertSchedulerImportExport::doJob();
        })->name('ERT:ImportExport')->withoutOverlapping(5)->everyMinute();

        // Task-5; sendAlerts
        $schedule->call(function() {
            ertSchedulerSendAlerts::doJob();
        })->name('ERT:SendAlerts')->withoutOverlapping(3)->everyMinute();

        // Task-6; cleanup
        $schedule->call(function() {
            ertSchedulerCleanup::doJob();
//        })->name('ERT:AnalyseInput')->withoutOverlapping(3)->everyMinute();
        })->name('ERT:Cleanup')->dailyAt('00:05');

    }

    public function registerPermissions() {
        return [
            'reportertool.eokm.startpage' => [
                'label' => 'Startpage',
                'tab' => 'EOKM Reporter Tool',
                'order' => 200,
            ],
            'reportertool.eokm.input_manage' => [
                'label' => 'Inputs',
                'tab' => 'EOKM Reporter Tool',
                'order' => 210,
            ],
            'reportertool.eokm.grade_notifications' => [
                'label' => 'Classify',
                'tab' => 'EOKM Reporter Tool',
                'order' => 215,
            ],
            'reportertool.eokm.police' => [
                'label' => 'Police',
                'tab' => 'EOKM Reporter Tool',
                'order' => 217,
            ],
            'reportertool.eokm.ntds' => [
                'label' => 'NTDs',
                'tab' => 'EOKM Reporter Tool',
                'order' => 220,
            ],
            'reportertool.eokm.changed' => [
                'label' => 'Changed',
                'tab' => 'EOKM Reporter Tool',
                'order' => 223,
            ],
            'reportertool.eokm.reporting' => [
                'label' => 'Report',
                'tab' => 'EOKM Reporter Tool',
                'order' => 225,
            ],
            'reportertool.eokm.rules' => [
                'label' => 'Rules',
                'tab' => 'EOKM Reporter Tool',
                'order' => 227,
            ],
            'reportertool.eokm.abusecontact_manage' => [
                'label' => 'Abusecontact management',
                'tab' => 'EOKM Reporter Tool',
                'order' => 230,
            ],
            'reportertool.eokm.utility' => [
                'label' => 'Utilities',
                'tab' => 'EOKM Reporter Tool',
                'order' => 225,
            ],
            'reportertool.eokm.grade_questions' => [
                'label' => 'Grade questions',
                'tab' => 'EOKM Reporter Tool',
                'order' => 235,
            ],
            'reportertool.eokm.ntdtemplate_manage' => [
                'label' => 'NTD template management',
                'tab' => 'EOKM Reporter Tool',
                'order' => 240,
            ],
            'reportertool.eokm.whois' => [
                'label' => 'WhoIs test',
                'tab' => 'EOKM Reporter Tool',
                'order' => 245,
            ],
            'reportertool.eokm.manual_read' => [
                'label' => 'ERT Manual',
                'tab' => 'EOKM Reporter Tool',
                'order' => 250,
            ],
            'reportertool.eokm.manual_write' => [
                'label' => 'ERT Manual Edit',
                'tab' => 'EOKM Reporter Tool',
                'order' => 255,
            ],
        ];
    }

    public function registerNavigation() {

        // TO-DO; get/put in Session::get('ert_saved_menuitems','');

        $ertmenu = parent::registerNavigation();
        //trace_log($ertmenu);

        // in empty (init) octobercms environment table is possible not avalable
        if (Schema::hasTable('reportertool_eokm_manual') ) {

            // dynamic creation of manual
            $manualchapters = Manual::where('deleted_at',null)->where('section','0')->orderBy('chapter')->get();
            if (!isset($ertmenu['manual'])) {
                $ertmenu['manual'] = [
                    'label' => 'Manual',
                    'url' => Backend::url('/reportertool/eokm/manual/preview/1'),
                    'icon' => 'icon-book',
                    'permissions' => ['reportertool.eokm.manual_read'],
                ];
            }
            $nr = 1;
            foreach ($manualchapters AS $chapter) {
                //ertLog::logLine("D-Chapter $chapter->chapter; label=$chapter->title ");
                $ertmenu['manual']['sideMenu']['chapter'.($chapter->chapter)] = [
                    'label' => (substr($chapter->title,0,1)=='*')?substr($chapter->title,1):$chapter->title,
                    'url' => Backend::url('/reportertool/eokm/manual/preview/' . $chapter->id),
                    'icon' => 'icon-book',
                ];
            }

        }

        return $ertmenu;
    }

    public function boot() {

        // 2019/7/25/Gs: ignore E_DEPRECATED
        error_reporting(E_ALL ^ E_DEPRECATED);

        // begin with error handlers
        App::error(function(RuntimeException $exeception) {
            $errmsg = "E-RuntimeException on line " . $exeception->getLine() . " in " . $exeception->getFile() . "; message: " . $exeception->getMessage();
            ertLog::logLine($errmsg);
            ertLog::errorMail($errmsg, $exeception,"RuntimeException on line " . $exeception->getLine() . " in " . $exeception->getFile() );
            $reterror = 'APPLICATION Runtime ERROR ' . Config::get('reportertool.eokm::errors.error_display_user','Error found');
            return $reterror;
        });
        App::error(function(ErrorException $exeception) {
            $errmsg = "E-ErrorException on line " . $exeception->getLine() . " in " . $exeception->getFile() . "; message: " . $exeception->getMessage();
            ertLog::logLine($errmsg);
            ertLog::errorMail($errmsg, $exeception, "ErrorException on line " . $exeception->getLine() . " in " . $exeception->getFile() );
            $reterror = 'APPLICATION ErrorException ERROR ' . Config::get('reportertool.eokm::errors.error_display_user','Error found');
            return $reterror;
        });
        App::error(function(SystemException $exeception) {
            $errmsg = "E-SystemException on line " . $exeception->getLine() . " in " . $exeception->getFile() . "; message: " . $exeception->getMessage();
            ertLog::logLine($errmsg);
            ertLog::errorMail($errmsg, $exeception, "SystemException on line " . $exeception->getLine() . " in " . $exeception->getFile() );
            $reterror = 'APPLICATION SystemException ERROR ' . Config::get('reportertool.eokm::errors.error_display_user','Error found');
            return $reterror;
        });
        App::fatal(function($exeception) {
            // php fatals
            $errmsg = "E-Fatal exception on line " . $exeception->getLine() . " in " . $exeception->getFile() . "; message: " . $exeception->getMessage();
            ertLog::logLine($errmsg);
            ertLog::errorMail($errmsg, $exeception, "Fatal exceptio on line " . $exeception->getLine() . " in " . $exeception->getFile());
            return 'FATAL ERROR ' . Config::get('reportertool.eokm::errors.error_display_user','Error found');
        });

        // Check if we are currently in backend module.
        if (!App::runningInBackend()) {
            return;
        }

        // Listen for `backend.page.beforeDisplay` event and inject js to current controller instance.
        Event::listen('backend.page.beforeDisplay', function($controller, $action, $params) {
            // when secure then convert http to https
            $appUrl = url('/');
            if (Request::secure()) {
                $appUrl = preg_replace(
                    "/^http:/i",
                    "https:",
                    $appUrl
                );
                // bioffice01
                $appUrl = str_replace(
                    "81",
                    "444",
                    $appUrl
                );
            }

            // set own CSS
            $controller->addCss($appUrl.'/plugins/reportertool/eokm/assets/css/ert.css');
        });

        // own backendSkin (files)
        Config::set('cms.backendSkin', 'reportertool\eokm\classes\BackendSkin');

        // Listen for menu extendItems
        Event::listen('backend.menu.extendItems', function($manager) {

            // remove menu items when not admin
            $user = BackendAuth::getUser();
            if ($user->is_superuser!==1) {

                // DYNAMIC; remove menu items

                $menus = $manager->listMainMenuItems();
                //trace_log($menus);
               foreach ($menus AS $menukey => $menu) {
                    //trace_log("menukey=$menukey, owner=$menu->owner, code=$menu->code");
                    if ($menu->owner!='ReporterTool.EOKM') {
                        $manager->removeMainMenuItem($menu->owner, $menu->code);
                    }
                }

            }
        });

        // Listen for system settings
        Event::listen('system.settings.extendItems', function($settings) {

            // remove settings items when not admin
            $user = BackendAuth::getUser();
            //if ($user->email != 'support@svsnet.nl') {
            if ($user->is_superuser!==1) {

                /**
                 * 2018/8/17/Gs: remove unwanted system settings -> will be showed when user clicks My Account
                 *
                 * Want to do this dynamic, problem is that $settings->listItems() is not loaded
                 * The event (fire) is called before the groupItems are set which listItems returns..
                 *
                 * So we can hack and remove every access the items -> performance! -> loop every item...performance!?
                 * We directly remove the specific settings
                 *
                 */

                /*
                $items = (array) $settings;
                $prefix = chr(0).'*'.chr(0);
                $items = $items[$prefix.'items'];
                trace_log(count($items));  // 2018/8/17/Gs -> 18
                if (is_array($items)) {
                    foreach ($items as $item) {
                        //trace_log($item);
                        $settings->removeSettingItem($item->owner, $item->code);
                    }
                }
                */

                $settings->removeSettingItem('AnandPatel.WysiwygEditors', 'settings');
                $settings->removeSettingItem('PanaKour.Backup', 'config');

            }

        });


    }


}
