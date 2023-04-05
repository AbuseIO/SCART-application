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

class GradeInformationColumn extends WidgetBase
{

    public function __construct($controller = [], $configuration = [])
    {
        parent::__construct($controller, $configuration);
    }

    public function renderValue($value, $column='', $record='')
    {
        return $this->makePartial('$/abuseio/scart/controllers/input_verify/_gradeInformationColumn.htm', ['record' => $record]);
    }


}
