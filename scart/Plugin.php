<?php
namespace abuseio\scart;

use abuseio\scart\classes\rules\AlreadyHasFilter;
use abuseio\scart\classes\rules\URLnew;
use abuseio\scart\models\Input;
use App;
use Event;
use Lang;
use Db;
use Log;
use Config;
use Flash;
use Session;
use Request;
use Backend;
use Schema;
use BackendAuth;
use Redirect;
use abuseio\scart\models\Systemconfig;
use Validator;
use RuntimeException;
use ErrorException;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Winter\Storm\Exception\SystemException;
use Winter\Storm\Exception\ApplicationException;
use abuseio\scart\classes\base\scartErrorHandler;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\scheduler\scartSchedulerCleanup;
use abuseio\scart\classes\scheduler\scartSchedulerSendAlerts;
use abuseio\scart\classes\scheduler\scartSchedulerAnalyzeInput;
use abuseio\scart\classes\scheduler\scartSchedulerCheckOnline;
use abuseio\scart\classes\scheduler\scartSchedulerSendNTD;
use abuseio\scart\classes\scheduler\scartSchedulerImportExport;
use abuseio\scart\classes\scheduler\scartSchedulerArchive;
use abuseio\scart\classes\scheduler\scartSchedulerCreateReports;
use abuseio\scart\classes\scheduler\scartSchedulerUpdateWhois;
use abuseio\scart\models\Manual;

use System\Classes\PluginBase;
use System\Classes\SettingsManager;
use System\Classes\PluginManager;
use Backend\Models\User;
use System\Controllers\Settings;

include_once (__DIR__ . '/includes/scartConstants.php');

class Plugin extends PluginBase {

    public function pluginDetails() {

        return [
            'name' => 'AbuseIO SCARt',
            'description' => 'Tool to investigate and report abuse',
            'author' => 'AbuseIO',
            'icon' => 'icon-check-square',
            'homepage' => 'https://scart.io',
        ];
    }

    public function register() {

        $this->registerConsoleCommand('abuseio.analyzeInputs', 'abuseio\scart\console\analyzeInputs');
        $this->registerConsoleCommand('abuseio.iccamApi', 'abuseio\scart\console\iccamApi');
        $this->registerConsoleCommand('abuseio.exportClassified', 'abuseio\scart\console\exportClassified');
        $this->registerConsoleCommand('abuseio.whoisTest', 'abuseio\scart\console\whoisTest');
        $this->registerConsoleCommand('abuseio.sendMailTest', 'abuseio\scart\console\sendMailTest');
        $this->registerConsoleCommand('abuseio.readMailTest', 'abuseio\scart\console\readMailTest');
        $this->registerConsoleCommand('abuseio.Getwhois', 'abuseio\scart\console\Getwhois');
        $this->registerConsoleCommand('abuseio.dashboardReset', 'abuseio\scart\console\dashboardReset');
        $this->registerConsoleCommand('abuseio.sendCustomNTD', 'abuseio\scart\console\sendCustomNTD');
        $this->registerConsoleCommand('abuseio.doArchive', 'abuseio\scart\console\doArchive');
        $this->registerConsoleCommand('abuseio.checkHASH', 'abuseio\scart\console\checkHASH');
        $this->registerConsoleCommand('abuseio.iccamLoadDirect', 'abuseio\scart\console\iccamLoadDirect');
        $this->registerConsoleCommand('abuseio.testDragon', 'abuseio\scart\console\testDragon');
        $this->registerConsoleCommand('abuseio.testUpdateWhois', 'abuseio\scart\console\testUpdateWhois');
        $this->registerConsoleCommand('abuseio.testAddon', 'abuseio\scart\console\testAddon');
        $this->registerConsoleCommand('abuseio.testAI', 'abuseio\scart\console\testAI');
        $this->registerConsoleCommand('abuseio.iccamReadBack', 'abuseio\scart\console\iccamReadBack');
        $this->registerConsoleCommand('abuseio.testMailview', 'abuseio\scart\console\testMailview');
        $this->registerConsoleCommand('abuseio.testLog', 'abuseio\scart\console\testLog');

        $this->registerConsoleCommand('abuseio.conver2seeder', 'abuseio\scart\console\convert2seeder');
        $this->registerConsoleCommand('abuseio.langImportExport', 'abuseio\scart\console\langImportExport');

        $this->registerConsoleCommand('abuseio.testPooling', 'abuseio\scart\console\testPooling');
        $this->registerConsoleCommand('abuseio.scartRealtimeCheckonline', 'abuseio\scart\console\scartRealtimeCheckonline');
        $this->registerConsoleCommand('abuseio.monitorMemory', 'abuseio\scart\console\monitorMemory');
        $this->registerConsoleCommand('abuseio.monitorRealtime', 'abuseio\scart\console\monitorRealtime');
        $this->registerConsoleCommand('abuseio.analyzeTUDELFT', 'abuseio\scart\console\analyzeTUDELFT');
        $this->registerConsoleCommand('abuseio.correctImageurls', 'abuseio\scart\console\correctImageurls');
        $this->registerConsoleCommand('abuseio.setMaintenance', 'abuseio\scart\console\setMaintenance');

        $this->registerConsoleCommand('abuseio.iccamApi3', 'abuseio\scart\console\iccamApi3');

        $this->registerConsoleCommand('abuseio.testChrome', 'abuseio\scart\console\testChrome');
    }

    public function registerPermissions() {
        return [
            'abuseio.scart.startpage' => [
                'label' => 'Startpage',
                'tab' => 'SCARt reporting',
                'order' => 200,
            ],
            'abuseio.scart.input_manage' => [
                'label' => 'Inputs',
                'tab' => 'SCARt reporting',
                'order' => 210,
            ],
            'abuseio.scart.grade_notifications' => [
                'label' => 'Classify',
                'tab' => 'SCARt reporting',
                'order' => 215,
            ],
            'abuseio.scart.police' => [
                'label' => 'Police',
                'tab' => 'SCARt reporting',
                'order' => 217,
            ],
            'abuseio.scart.ntds' => [
                'label' => 'NTDs',
                'tab' => 'SCARt reporting',
                'order' => 220,
            ],
            'abuseio.scart.changed' => [
                'label' => 'Changed',
                'tab' => 'SCARt reporting',
                'order' => 223,
            ],
            'abuseio.scart.reporting' => [
                'label' => 'Report',
                'tab' => 'SCARt reporting',
                'order' => 225,
            ],
            'abuseio.scart.rules' => [
                'label' => 'Rules',
                'tab' => 'SCARt reporting',
                'order' => 227,
            ],
            'abuseio.scart.abusecontact_manage' => [
                'label' => 'Abusecontact management',
                'tab' => 'SCARt reporting',
                'order' => 230,
            ],
            'abuseio.scart.utility' => [
                'label' => 'Utilities',
                'tab' => 'SCARt reporting',
                'order' => 225,
            ],
            'abuseio.scart.grade_questions' => [
                'label' => 'Grade questions',
                'tab' => 'SCARt reporting',
                'order' => 235,
            ],
            'abuseio.scart.ntdtemplate_manage' => [
                'label' => 'NTD template management',
                'tab' => 'SCARt reporting',
                'order' => 240,
            ],
            'abuseio.scart.whois' => [
                'label' => 'WhoIs test',
                'tab' => 'SCARt reporting',
                'order' => 245,
            ],
            'abuseio.scart.manual_read' => [
                'label' => 'Manual',
                'tab' => 'SCARt reporting',
                'order' => 250,
            ],
            'abuseio.scart.manual_write' => [
                'label' => 'Manual Edit',
                'tab' => 'SCARt reporting',
                'order' => 255,
            ],
            'abuseio.scart.blocked_days' => [
                'label' => 'Blocked days',
                'tab' => 'SCARt reporting',
                'order' => 265,
            ],
            'abuseio.scart.whois_cache' => [
                'label' => 'Whois cache',
                'tab' => 'SCARt reporting',
                'order' => 260,
            ],
            'abuseio.scart.exporterrors' => [
                'label' => 'Export errors',
                'tab' => 'SCARt reporting',
                'order' => 266,
            ],
            'abuseio.scart.user_write' => [
                'label' => 'Users',
                'tab' => 'SCARt reporting',
                'order' => 270,
            ],
            'abuseio.scart.checkonline' => [
                'label' => 'Checkonline records',
                'tab' => 'SCARt reporting',
                'order' => 280,
            ],
            'abuseio.scart.system_config' => [
                'label' => 'System configuration',
                'tab' => 'SCARt reporting',
                'order' => 290,
            ],
            'abuseio.scart.system_addons' => [
                'label' => 'System addons records',
                'tab' => 'SCARt reporting',
                'order' => 292,
            ],
            'abuseio.scart.system_whitelist' => [
                'label' => 'System whitelist records',
                'tab' => 'SCARt reporting',
                'order' => 293,
            ],
            'abuseio.scart.sources' => [
                'label' => 'Input sources',
                'tab' => 'SCARt reporting',
                'order' => 294,
            ],
        ];
    }

    public function registerSchedule($schedule) {

        // if not setup yet, then exit
        if (!Schema::hasTable(SCART_CONFIG_TABLE)) {
            return;
        }

        $overlapping = 7 * 1440;    // default (no arguments is 1440 = 24 hours) -> FORCE FOR ONE WEEK without overlapping

        if (Systemconfig::get('abuseio.scart::scheduler.scrape.active',true)) {
            // Task-1: analyse input(s)
            $schedule->call(function() {
                scartSchedulerAnalyzeInput::doJob();
            })->name('SCART:AnalyseInput')->withoutOverlapping($overlapping)->everyMinute();
        }

        if (Systemconfig::get('abuseio.scart::scheduler.checkntd.active',true)) {
            if (Systemconfig::get('abuseio.scart::scheduler.checkntd.mode',SCART_CHECKNTD_MODE_CRON) == SCART_CHECKNTD_MODE_CRON) {
                // Task-2: CheckNTD
                $schedule->call(function () {
                    scartSchedulerCheckOnline::doJob();
                })->name('SCART:CheckNTD')->withoutOverlapping($overlapping)->everyMinute();
            }
        }

        if (Systemconfig::get('abuseio.scart::scheduler.sendntd.active',true)) {
            // Task-3: send NTD
            $schedule->call(function () {
                scartSchedulerSendNTD::doJob();
            })->name('SCART:SendNTD')->withoutOverlapping($overlapping)->everyMinute();
        }

        if (Systemconfig::get('abuseio.scart::scheduler.sendalerts.active',true)) {
            // Task-4; sendAlerts
            $schedule->call(function () {
                scartSchedulerSendAlerts::doJob();
            })->name('SCART:SendAlerts')->withoutOverlapping($overlapping)->everyMinute();
        }

        if (Systemconfig::get('abuseio.scart::scheduler.createreports.active',true)) {
            // Task-5; CreateReports
            $schedule->call(function () {
                scartSchedulerCreateReports::doJob();
            })->name('SCART:CreateReports')->withoutOverlapping($overlapping)->everyMinute();
        }

        if (Systemconfig::get('abuseio.scart::scheduler.importexport.active',true)) {
            // Task-6; read import
            $schedule->call(function () {
                scartSchedulerImportExport::doJob();
            })->name('SCART:ImportExport')->withoutOverlapping($overlapping)->everyMinute();
        }

        // every day

        if (Systemconfig::get('abuseio.scart::scheduler.updatewhois.active',true)) {
            // Task-7; cleanup
            $schedule->call(function () {
                scartSchedulerUpdateWhois::doJob();
            })->name('SCART:Updatewhois')->twiceDaily(1, 13);
        }

        if (Systemconfig::get('abuseio.scart::scheduler.cleanup.active',true)) {
            // Task-8; cleanup
            $schedule->call(function () {
                scartSchedulerCleanup::doJob();
            })->name('SCART:Cleanup')->dailyAt('00:15');
        }

        if (Systemconfig::get('abuseio.scart::scheduler.archive.active',true)) {
            // Task-9; Archive
            $schedule->call(function () {
                scartSchedulerArchive::doJob();
            })->name('SCART:Archive')->dailyAt('02:05');
        }


    }

    public function registerNavigation() {

        $ertmenu = parent::registerNavigation();

        // if not setup yet, then exit
        if (!Schema::hasTable(SCART_CONFIG_TABLE)) {
            return $ertmenu;
        }

        // dynamic creation of manual
        $manualchapters = Manual::where('deleted_at',null)->where('section','0')->orderBy('chapter')->get();

        if (!isset($ertmenu['manual'])) {
            $ertmenu['manual'] = [
                'label' => 'Manual',
                'url' => Backend::url('/abuseio/scart/manual/preview/1'),
                'icon' => 'icon-book',
                'permissions' => ['abuseio.scart.manual_read'],
            ];
        }

        $id = '';
        foreach ($manualchapters AS $chapter) {
            //scartLog::logLine("D-Chapter $chapter->chapter; label=$chapter->title ");
            if (!$id) $id =  $chapter->id;
            $ertmenu['manual']['sideMenu']['chapter'.($chapter->chapter)] = [
                'label' => (substr($chapter->title,0,1)=='*')?substr($chapter->title,1):$chapter->title,
                'url' => Backend::url('/abuseio/scart/manual/preview/' . $chapter->id),
                'icon' => 'icon-book',
            ];
        }

        if ($id) {
            $ertmenu['manual']['url'] = Backend::url('/abuseio/scart/manual/preview/'.$id);

        }

        return $ertmenu;
    }

    private function logError($exeception,$errtype='Exeception') {

        $errmsg = "E-$errtype on line " . $exeception->getLine() . " in " . $exeception->getFile() . "; message: " . $exeception->getMessage();
        scartLog::logLine($errmsg);
        scartLog::errorMail($errmsg, $exeception,"RuntimeException");
        return "FATAL '$errtype' ERROR " . Config::get('abuseio.scart::errors.display_user','Error found');
    }

    public function boot() {

        // patch for phpwhois library -> @TO-DO setup own phpwhois
        error_reporting(E_ALL ^ E_DEPRECATED);

        // if not setup yet, then exit
        if (!Schema::hasTable(SCART_CONFIG_TABLE)) {
            return;
        }

        // force own Customer Handler for
        $this->app->bind(
            ExceptionHandler::class,
            scartErrorHandler::class
        );

        // begin with error handlers
        App::error(function(RuntimeException $exeception) {
            return $this->logError($exeception,'RuntimeException');
        });
        App::error(function(ErrorException $exeception) {
            return $this->logError($exeception,'ErrorException');
        });
        App::error(function(SystemException $exeception) {
            return $this->logError($exeception,'SystemException');
        });
        App::error(function(ApplicationException $exeception) {
            return $this->logError($exeception,'ApplicationException');
        });
        // disable -> only get specific throw, validation
        //App::error(function(\Throwable $exeception) {
        //    return $this->logError($exeception,'Throwable');
        //});
        App::fatal(function(\Exception $exeception) {
            return $this->logError($exeception,'Exception');
        });

        // Check if we are currently in backend module.
        if (!App::runningInBackend()) {
            // if not so, then stop return here
            return;
        }

        if (!App::runningInConsole()) {
            scartLog::logMemory('scartInteractiveUser');
        }

        // General note: check always if db tables are up - can be in init/setup mode

        // own backendSkin (files)
        Config::set('cms.backendSkin', 'abuseio\scart\classes\base\scartBackendSkin');

        // Listen for `backend.page.beforeDisplay` event
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
            $controller->addCss($appUrl.'/plugins/abuseio/scart/assets/css/ert.css');

            // get current user
            $user = BackendAuth::getUser();

            if ($user->is_superuser!=1 && Schema::hasTable(SCART_USER_TABLE)) {
                // check if account is disabled or outside of working hours
                if (scartUsers::isDisabled()) {
                    BackendAuth::logout();
                    Session::flush();
                    Flash::warning('Access disabled or not within working hours');
                    return Backend::redirect('/backend');
                }
            }

            if ($user->is_superuser!=1 && Schema::hasTable(SCART_CONFIG_TABLE)) {
                // check if maintenance mode
                if (Systemconfig::get('abuseio.scart::maintenance.mode', false)) {
                    BackendAuth::logout();
                    Session::flush();
                    Flash::warning('System in maintenance (mode)');
                    return Backend::redirect('/backend');
                }
            }

        });

        // Listen for menu extendItems
        Event::listen('backend.menu.extendItems', function($manager) {

            // DYNAMIC; remove menu items
            $user = BackendAuth::getUser();
            $verifyactive = Systemconfig::get('abuseio.scart::verify.active',false);
            $menus = $manager->listMainMenuItems();
            foreach ($menus AS $menukey => $menu) {
                if (($user->is_superuser!=1 && $menu->owner!='abuseio.scart') || (strtolower($menu->code)=='verify' && !$verifyactive)) {
                    $manager->removeMainMenuItem($menu->owner, $menu->code);
                }
            }

        });

        // Listen for system settings
        Event::listen('system.settings.extendItems', function($settings) {

            // remove settings items when not admin
            $user = BackendAuth::getUser();
            if ($user->is_superuser!=1) {
                // obsolute!?
                $settings->removeSettingItem('AnandPatel.WysiwygEditors', 'settings');
                $settings->removeSettingItem('PanaKour.Backup', 'config');
            }

        });

        // overrule translation from backend lang
        Event::listen('translator.beforeResolve', function ($key, $replace, $locale) {

            // Check if the translation doesn't originate from this plugin
            $plugin = 'abuseio.scart';
            if (substr($key, 0, strlen($plugin)) != $plugin) {
                // Contruct a plugin translation path
                $path = $plugin . '::lang.' . str_replace('::lang', '', $key);
                // Retrieve its results
                $result = Lang::get($path,$replace, $locale);
                // If an overriding translation is found, return it
                if ($result != $path) {
                    return $result;
                }
            }

        });

        Backend\Classes\Controller::extend(function($controller) {

            // load/change language functions for each controller

            $controller->addDynamicMethod('onLoadLanguages', function() {
                $results = [];
                if (Schema::hasTable(SCART_CONFIG_TABLE)) {
                    $lang = Lang::getLocale();
                    scartLog::logLine("onLoadLanguages; lang=$lang");
                    $config = new Systemconfig();
                    $langs = $config->getAlertLanguageOptions();
                    foreach ($langs AS $key => $value) {
                        $results[] = [
                            'id' => $key,
                            'text' => $key,
                            'selected' => ($lang==$key),
                        ];
                    }
                }
                return ['results' => $results];

            });

            $controller->addDynamicMethod('onChangeLanguage', function() {
                if (Schema::hasTable(SCART_CONFIG_TABLE)) {
                    $lang = input('lang');
                    // get current preference (always here)
                    $pref = Backend\Models\Preference::instance();
                    $settings = $pref->value;
                    $settings['locale'] = $pref->locale = $lang;
                    $pref->value = $settings;
                    //scartLog::logLine("value=" . print_r($pref->value,true) );
                    $pref->save();
                    // set also in current session and reload
                    Session::put('locale', $lang);
                }
                return Redirect::refresh();
            });
        });

    }

    //**  Customer LIST types **/

    public function registerListColumnTypes()
    {
        return [
            'gradeinformationcolumn' => [new \abuseio\scart\Widgets\GradeInformationColumn, 'renderValue'],
            'gradecolumn' => [new \abuseio\scart\Widgets\GradeColumn, 'renderValue'],
            'jsontext' => [$this, 'ListConvertJson2Text'],
            'html' => function($value) {return $value;}
        ];
    }

    /**
     * JSON type field (eg for repeater field)
     *
     * Notes:
     * a. handle one dimension repeater field
     * b. can handle mixed input type; string or array
     *
     * @param $value
     * @param $column
     * @param $record
     * @return string
     */

    public function ListConvertJson2Text($value, $column, $record)
    {
        $text = '';
        //scartLog::logLine("D-value=" . print_r($value, true));
        if (is_array($value)) {
            foreach ($value AS $item) {
                foreach ($item AS $key => $value) {
                    $text .= (($text!='') ? ', ' : '')  . $value;
                }
            }
        } else {
            $text = $value;
        }
        return $text;
    }

}
