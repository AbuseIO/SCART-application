<?php
namespace abuseio\scart\widgets;

use abuseio\scart\classes\browse\scartBrowser;
use abuseio\scart\classes\classify\scartGrade;
use abuseio\scart\classes\helpers\scartImage;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\classes\scheduler\scartScheduler;
use abuseio\scart\classes\whois\scartWhois;
use abuseio\scart\Controllers\Grade;
use abuseio\scart\models\Systemconfig;
use abuseio\scart\widgets\tiles\classes\helpers\Image;
use abuseio\scart\widgets\tiles\classes\helpers\Input;
use abuseio\scart\widgets\tiles\classes\helpers\Form;
use abuseio\scart\widgets\tiles\classes\helpers\Lists;
use Backend\Classes\WidgetBase;
use Illuminate\Support\Facades\Session;

class Timeline extends WidgetBase
{

    private $data = '';

    public function init() {
        $this->addJs('js/timeline.js');
        $this->addCss('css/timeline.css?'.time());
    }

    public function setData($data)
    {
        $array = [];
        if (empty($data) || count($data) == 0) {return;}

        foreach($data as $message) {
            $key = (!isset($message->belongsTo)) ? $message->type : $message->belongsTo;
            $array[$key][] = $message;
        }



        $this->data = $array;
    }

    public function setJson(string $jsonData)
    {
        $jsonData = json_decode($jsonData);

        // check if json is correct
        if (json_last_error() === 0) {
            $this->data = $jsonData;
        } else {
            scartLog::logLine("D-Timeline; Json-format is incorrect");

        }

    }




    /**
     * Renders the widget in the nav.
     */
    public function render()
    {
        return $this->makePartial('timeline', ['timeline' => $this->data]);
    }


}
