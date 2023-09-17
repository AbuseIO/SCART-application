<?php namespace abuseio\scart\Controllers;

use Redirect;
use Backend\Classes\Controller;
use BackendMenu;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\models\User_options;

class Config extends Controller
{
    public $requiredPermissions = ['abuseio.scart.system_config'];

    public $implement = [
        'Backend\Behaviors\FormController'
    ];

    public $formConfig = 'config_form.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'utility', 'config');
    }

    public function index() {

        // Check if record exists -> if not, load from ENV
        $systemconfig = Systemconfig::initLoad();

        return Redirect::to('/backend/abuseio/scart/config/update/'.$systemconfig->id);
    }

    public function onCheckMaintenance() {

        $id = input('id');
        $setmode = input('setmode');
        scartLog::logLine("onCheckMaintenance (id=$id, setmode=$setmode)");

        $result = '';
        if ($id) {

            $field = 'maintenance-mode';
            $systemconfig = Systemconfig::find($id);
            if ($systemconfig) {

                if ($setmode) {

                    // if all scheduler(s) are set on 0, then all clear for maintenance (stopping docker/jobs eg)
                    $value = serialize(1);
                    $count = User_options::where('user_id', 0)->where('name','like','scheduler%')->where('value',$value)->count();
                    //scartLog::logLine("D-count=$count");
                    $result = ($count > 0) ? 'running' : 'ok';

                }

                $systemconfig->$field = ($setmode=='1');
                $systemconfig->save();

            }

        }

        scartLog::logLine("onCheckMaintenance (result=$result)");
        return $result;
    }

}
