<?php namespace abuseio\scart\Controllers;

use abuseio\scart\models\Input_source;
use Flash;
use Backend\Classes\Controller;
use BackendMenu;
use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\classes\helpers\scartUsers;
use abuseio\scart\models\Grade_question;
use abuseio\scart\models\Grade_question_option;
use abuseio\scart\models\Input_status;
use abuseio\scart\models\Input_type;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\models\Rule_type;

class Domainrule extends scartController
{
    public $requiredPermissions = ['abuseio.scart.rules'];

    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\FormController'
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('abuseio.scart', 'Domainrules');
    }

    public function listExtendQuery($query) {

        if (scartUsers::isCorona()) {
            $domainrule = new \abuseio\scart\models\Domainrule();
            $exclude = $domainrule->getCoronaExclude();
            $query->whereNotIn('type_code',$exclude);
        }
    }

    public function listFilterExtendScopes($filter) {

        /*
        $recs = Rule_type::orderBy('sortnr')->select('code','title','description')->get();
        // convert to [$code] -> $text
        $options = array();
        foreach ($recs AS $rec) {
            $options[$rec->code] = $rec->title . ' - ' . $rec->description;
        }
        */

        $domainrule = new \abuseio\scart\models\Domainrule();
        $options = $domainrule->getTypeCodeOptions('','');

        $filter->addScopes([
            'type_code' => [
                'label' => 'type',
                'type' => 'group',
                'conditions' => 'type_code in (:filtered)',
                'options' => $options,
//                'default' => $options,
            ],
        ]);

    }

    public function getImportGradeQuestions($recordId,$grade_code) {

        scartLog::logLine("D-recordId=$recordId, grade_code=$grade_code");

        $rule = \abuseio\scart\models\Domainrule::find($recordId);
        $values = ($rule && !empty($rule->rulesetdata)) ? unserialize($rule->rulesetdata) : [];

        // fill dynamic question

        $questions = [];

        // type_code

        $question = new \stdClass();
        $question->type = 'dropdown';
        $question->label = 'Url type';
        $question->name = 'type_code_'.$grade_code;
        $question->leftright = 'full';
        $types = Input_type::orderBy('sortnr')->get();
        $options = [];
        $value = (isset($values[$question->name])) ? $values[$question->name] : '';
        if (empty($value)) $value = [SCART_TYPE_CODE_WEBSITE];
        foreach ($types AS $type) {
            $options[] = [
                'value' => $type->code,
                'label' => $type->title . ' - ' . $type->description,
                'sortnr' => $type->sortnr,
                'selected' => (in_array($type->code,$value) ? true : false),
            ];
        }
        $question->options = $options;
        $questions[] = $question;

        if ($grade_code==SCART_GRADE_QUESTION_GROUP_ILLEGAL) {

            // source_code

            $question = new \stdClass();
            $question->type = 'dropdown';
            $question->label = 'Source';
            $question->name = 'source_code_'.$grade_code;
            $question->leftright = 'full';
            $sources = Input_source::orderBy('sortnr')->get();
            $value = (isset($values[$question->name])) ? $values[$question->name] : '';
            if (empty($value)) $value = [''];
            $options = [
                [
                    'value' => '',
                    'label' => '(all)',
                    'sortnr' => 0,
                    'selected' => (in_array('',$value) ? true : false),
                ]
            ];
            foreach ($sources AS $source) {
                $options[] = [
                    'value' => $source->code,
                    'label' => $source->title . ' - ' . $source->description,
                    'sortnr' => $source->sortnr,
                    'selected' => (in_array($source->code,$value) ? true : false),
                ];
            }
            $question->options = $options;
            $questions[] = $question;

            // iccam hotline

            // note: currently hard code - not provided by iccam

            $question = new \stdClass();
            $question->type = 'dropdown';
            $question->label = 'Hotline ID (if source ICCAM)';
            $question->name = 'iccam_hotline_'.$grade_code;
            $question->leftright = 'full';

            // @TO-DO; database read
            $hotlines = [];
            $hotline = new \stdClass();
            $hotline->code = '';
            $hotline->title = '(all)';
            $hotline->sortnr = count($hotlines) + 1;
            $hotlines[] = $hotline;
            $hotline = new \stdClass();
            $hotline->code = '28';
            $hotline->title = 'DK Hotline (28)';
            $hotline->sortnr = count($hotlines) + 1;
            $hotlines[] = $hotline;
            $hotline = new \stdClass();
            $hotline->code = '33';
            $hotline->title = 'DE Hotline (33)';
            $hotline->sortnr = count($hotlines) + 1;
            $hotlines[] = $hotline;
            $hotline = new \stdClass();
            $hotline->code = '43';
            $hotline->title = 'NL Hotline (43)';
            $hotline->sortnr = count($hotlines) + 1;
            $hotlines[] = $hotline;
            $hotline = new \stdClass();
            $hotline->code = '44';
            $hotline->title = 'PL Hotline (44)';
            $hotline->sortnr = count($hotlines) + 1;
            $hotlines[] = $hotline;
            $hotline = new \stdClass();
            $hotline->code = '51';
            $hotline->title = 'UK Hotline (51)';
            $hotline->sortnr = count($hotlines) + 1;
            $hotlines[] = $hotline;

            $options = [];
            $value = (isset($values[$question->name])) ? $values[$question->name] : '';
            if (empty($value)) $value = [SCART_TYPE_CODE_WEBSITE];
            foreach ($hotlines AS $hotline) {
                $options[] = [
                    'value' => $hotline->code,
                    'label' => $hotline->title,
                    'sortnr' => $hotline->sortnr,
                    'selected' => (in_array($hotline->code,$value) ? true : false),
                ];
            }
            $question->options = $options;
            $questions[] = $question;

            // grade_questions

            $grades = Grade_question::where('questiongroup',SCART_GRADE_QUESTION_GROUP_ILLEGAL)
                ->where('url_type',SCART_URL_TYPE_MAINURL)->orderBy('sortnr')->get();

            // convert to direct selectable
            $valuegrades = [];
            if (isset($values['grades'])) {
                foreach ($values['grades'] AS $answer) {
                    $valuegrades[$answer['grade_question_id']] = $answer['answer'];
                }
            }

            $toggle = true;
            foreach ($grades AS $grade) {

                $question = new \stdClass();
                $question->type = $grade->type;
                $question->label = $grade->label;
                $question->name = $grade->name;
                $question->leftright = $grade->span;
                $toggle = !$toggle;

                $value = (isset($valuegrades[$grade->id])) ? $valuegrades[$grade->id] : [];

                if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

                    $selected = '';
                    $options = [];
                    $opts = Grade_question_option::where('grade_question_id',$grade->id)->orderBy('sortnr')->get();
                    foreach ($opts AS $opt) {
                        $options[] = [
                            'value' => $opt->value,
                            'label' => $opt->label,
                            'sortnr' => $opt->sortnr,
                            'selected' => (in_array($opt->value,$value) ? true : false),
                        ];
                    }
                    $question->options = $options;

                } elseif ($question->type == 'text') {

                    $question->value = $values;

                }

                $questions[] = $question;
            }

            // then POLICE FIRST

            $question = new \stdClass();
            $question->type = 'radio';
            $question->label = 'Police first';
            $question->name = 'police_first';
            $question->leftright = 'full';
            $types = [
                [
                    'value' => 'y',
                    'label' => 'yes',
                    'sortnr' => 1,
                ],
                [
                    'value' => 'n',
                    'label' => 'no',
                    'sortnr' => 2,
                ],
            ];
            $options = [];
            $value = (isset($values[$question->name])) ? $values[$question->name] : '';
            if (empty($value)) $value = ['n'];
            foreach ($types AS $type) {
                $options[] = [
                    'value' => $type['value'],
                    'label' => $type['label'],
                    'sortnr' => $type['sortnr'],
                    'selected' => (in_array($type['value'],$value) ? true : false),
                ];
            }
            $question->options = $options;
            $questions[] = $question;

            // POLICE REASON

            $grades = Grade_question::where('questiongroup',SCART_GRADE_QUESTION_GROUP_POLICE)
                ->where('url_type',SCART_URL_TYPE_MAINURL)->orderBy('sortnr')->get();

            // convert to direct selectable
            $valuegrades = [];
            if (isset($values['police_reason'])) {
                foreach ($values['police_reason'] AS $answer) {
                    $valuegrades[$answer['grade_question_id']] = $answer['answer'];
                }
            }

            $toggle = true;
            foreach ($grades AS $grade) {

                $question = new \stdClass();
                $question->type = $grade->type;
                $question->label = $grade->label;
                $question->name = $grade->name;
                $question->leftright = $grade->span;
                $toggle = !$toggle;

                $value = (isset($valuegrades[$grade->id])) ? $valuegrades[$grade->id] : [];

                if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

                    $selected = '';
                    $options = [];
                    $opts = Grade_question_option::where('grade_question_id',$grade->id)->orderBy('sortnr')->get();
                    foreach ($opts AS $opt) {
                        $options[] = [
                            'value' => $opt->value,
                            'label' => $opt->label,
                            'sortnr' => $opt->sortnr,
                            'selected' => (in_array($opt->value,$value) ? true : false),
                        ];
                    }
                    $question->options = $options;

                } elseif ($question->type == 'text') {

                    $question->value = $values;

                }

                $questions[] = $question;
            }


            // direct checkonline_manual

            $question = new \stdClass();
            $question->type = 'radio';
            $question->label = 'Set checkonline manual';
            $question->name = 'checkonline_manual';
            $question->leftright = 'full';
            $types = [
                [
                    'value' => 'y',
                    'label' => 'yes',
                    'sortnr' => 1,
                ],
                [
                    'value' => 'n',
                    'label' => 'no',
                    'sortnr' => 2,
                ],
            ];
            $options = [];
            $value = (isset($values[$question->name])) ? $values[$question->name] : '';
            if (empty($value)) $value = ['n'];
            foreach ($types AS $type) {
                $options[] = [
                    'value' => $type['value'],
                    'label' => $type['label'],
                    'sortnr' => $type['sortnr'],
                    'selected' => (in_array($type['value'],$value) ? true : false),
                ];
            }
            $question->options = $options;
            $questions[] = $question;

        } else {

            // grade_questions

            $grades = Grade_question::where('questiongroup',SCART_GRADE_QUESTION_GROUP_NOT_ILLEGAL)
                ->where('url_type',SCART_URL_TYPE_MAINURL)->orderBy('sortnr')->get();

            // convert to direct selectable
            $valuegrades = [];
            if (isset($values['grades'])) {
                foreach ($values['grades'] AS $answer) {
                    $valuegrades[$answer['grade_question_id']] = $answer['answer'];
                }
            }

            $toggle = true;
            foreach ($grades AS $grade) {

                $question = new \stdClass();
                $question->type = $grade->type;
                $question->label = $grade->label;
                $question->name = $grade->name;
                $question->leftright = $grade->span;
                $toggle = !$toggle;

                $value = (isset($valuegrades[$grade->id])) ? $valuegrades[$grade->id] : [];

                if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

                    $selected = '';
                    $options = [];
                    $opts = Grade_question_option::where('grade_question_id',$grade->id)->orderBy('sortnr')->get();
                    foreach ($opts AS $opt) {
                        $options[] = [
                            'value' => $opt->value,
                            'label' => $opt->label,
                            'sortnr' => $opt->sortnr,
                            'selected' => (in_array($opt->value,$value) ? true : false),
                        ];
                    }
                    $question->options = $options;

                } elseif ($question->type == 'text') {

                    $question->value = $values;

                }

                $questions[] = $question;
            }

        }

        return $questions;
    }

    public function update($recordId, $context=null) {
        //scartLog::logLine("D-Update record=$recordId");
        return $this->asExtension('FormController')->update($recordId, $context);
    }

    public function onCheckProxy() {

        $domain = input('domain');
        scartLog::logLine("D-Domainrul.onCheckProxy; domain=$domain");

        $resultproxy = \abuseio\scart\models\Domainrule::getProxyRealIP($domain);

        if ($resultproxy['error'] == '') {
            Flash::info('Got real IP from proxy service hoster ' . $resultproxy['proxy_service_owner']);
            return [
                'proxy_service_owner' => $resultproxy['proxy_service_owner'],
                'proxy_service_id' => $resultproxy['proxy_service_id'],
                'real_ip' => $resultproxy['real_ip'],
                'real_host_contact' => $resultproxy['real_host_contact'],
                'real_host_contact_id' => $resultproxy['real_host_contact_id'],
            ];
        } else {
            Flash::warning($resultproxy['error']);
        }


    }


}
