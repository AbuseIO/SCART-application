<?php

namespace ReporterTool\EOKM\Controllers;

/**
 * Grade function
 *
 * Show ANALYZE screen with Inputs and Notifications.
 * Next and Previous
 * Select ALL or DESELECT
 * Set selected
 * Show and hide ignored
 * Show grade questions
 *
 * Use of general ertGrade class
 *
 */

use Backend\Models\User;
use Backend\Widgets\Form;
use Db;
use Flash;
use Config;
use Validator;
use ValidationException;
use BackendMenu;
use BackendAuth;
use Illuminate\Support\Facades\Redirect;
use reportertool\eokm\classes\ertBrowser;
use reportertool\eokm\classes\ertExportICCAM;
use reportertool\eokm\classes\ertICCAM2ERT;
use ReporterTool\EOKM\Models\Abusecontact;
use ReporterTool\EOKM\Models\Ntd_template;
use function GuzzleHttp\Promise\all;
use Illuminate\Support\Facades\Session;
use reportertool\eokm\classes\ertController;
use reportertool\eokm\classes\ertGrade;
use reportertool\eokm\classes\ertWhois;
use reportertool\eokm\classes\ertLog;
use reportertool\eokm\classes\ertAnalyzeInput;
use ReporterTool\EOKM\Models\Grade_question;
use ReporterTool\EOKM\Models\Grade_question_option;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Grade_answer;
use ReporterTool\EOKM\Models\Notification_input;
use ReporterTool\EOKM\Models\User_options;
use ReporterTool\EOKM\Models\Domainrule;
use ReporterTool\EOKM\Plugin;
use reportertool\eokm\classes\ertUsers;

class Grade extends ertController
{
    public $requiredPermissions = ['reportertool.eokm.grade_notifications'];

    public $implement = [
        'Backend\Behaviors\ListController',
        //'Backend\Behaviors\FormController',
    ];

    public $listConfig = 'config_list.yaml';
    //public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    private $_labels = [
        'registrar_owner' => 'registrar',
        'registrar_lookup' => '_domain ',
        'registrar_country' => '_country ',
        'registrar_abusecontact' => '_abuse ',
        'registrar_abusecustom' => '_custom ',
        'host_owner' => 'hoster',
        'host_lookup' => '_IP',
        'host_country' => '_country',
        'host_abusecontact' => '_abuse',
        'host_abusecustom' => '_custom',
    ];

    private $_infofields = [
        'filenumber' => 'filenumber',
        'type_code' => 'type',
    ];

    private $_displaycolumns = [
        // 1920 -> 3=120/32, 4=180/46, 6=260/68
        '1920' => [
            '2' => [
                'colspan' => 6,
                'boxheightsize' => 550,
                'imgsize' => 530,
                'txtsize' => 118,
            ],
            '3' => [
                'colspan' => 4,
                'boxheightsize' => 550,
                'imgsize' => 480,
                'txtsize' => 76,
            ],
            '4' => [
                'colspan' => 3,
                'boxheightsize' => 450,
                'imgsize' => 380,
                'txtsize' => 55,
            ],
        ],
        // 1620 -> 3=110/26, 4=160/39, 6=220/60
        '1620' => [
            '2' => [
                'colspan' => 6,
                'boxheightsize' => 500,
                'imgsize' => 480,
                'txtsize' => 94,
            ],
            '3' => [
                'colspan' => 4,
                'boxheightsize' => 400,
                'imgsize' => 380,
                'txtsize' => 60,
            ],
            '4' => [
                'colspan' => 3,
                'boxheightsize' => 400,
                'imgsize' => 350,
                'txtsize' => 45,
            ],
        ],
        // 1280 -> 3=80/16, 4=120/26, 6=190/46
        '1020' => [
            '2' => [
                'colspan' => 6,
                'boxheightsize' => 500,
                'imgsize' => 480,
                'txtsize' => 75,
            ],
            '3' => [
                'colspan' => 4,
                'boxheightsize' => 400,
                'imgsize' => 380,
                'txtsize' => 45,
            ],
            '4' => [
                'colspan' => 3,
                'boxheightsize' => 400,
                'imgsize' => 300,
                'txtsize' => 32,
            ],
        ],
        // small fallback -> one column
        '0' => [
            '2' => [
                'colspan' => 12,
                'boxheightsize' => 400,
                'imgsize' => 100,
                'txtsize' => 10,
            ],
            '3' => [
                'colspan' => 12,
                'boxheightsize' => 400,
                'imgsize' => 100,
                'txtsize' => 10,
            ],
            '4' => [
                'colspan' => 6,
                'boxheightsize' => 400,
                'imgsize' => 100,
                'txtsize' => 10,
            ],
        ]

    ];

    /** SETTINGS **/

    //  SCREENSIZE
    function onSetScreensize() {
        $screensize = input('screensize');
        Session::put('grade_screensize',$screensize);
        ertLog::logLine("D-onSetScreensize; screensize=$screensize ");
    }
    function getScreensize() {
        return Session::get('grade_screensize','1620');
    }
    // COL IMG SIZE
    function getColimgsize() {
        return Session::get('grade_colimgsize','250');
    }
    function setColimgsize($colimgsize) {
        Session::put('grade_colimgsize',$colimgsize);
    }
    // COLUMNSIZE
    function getColumnsize() {
        $columnsize = ertUsers::getOption(ERT_USER_OPTION_SCREENCOLS);
        if ($columnsize=='') $columnsize = 3;
        return $columnsize;
    }
    function setColumnsize($columnsize) {
        ertUsers::setOption(ERT_USER_OPTION_SCREENCOLS, $columnsize);
    }
    // IGNORE SHOW/HIDE
    function getIgnoreShowHide() {
        $show = Session::get('grade_ignoreshowhide','');
        if ($show=='') $show = '1';
        return ($show=='1');
    }
    function setIgnoreShowHide($show) {
        Session::put('grade_ignoreshowhide',$show);
    }
    function jsIgnoreShowHide() {
        $show = $this->getIgnoreShowHide();
        return $this->makePartial('js_ignoreshowhide', ['show' => ($show=='1'),]);
    }


    /**
     * Own index
     */

    public $inputWidget = null;

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', 'grade');

        // Note: very important to bindToController in this __construct phase; else not working
        $config = $this->makeConfig('$/reportertool/eokm/models/input/fields_grade.yaml');
        $config->model = new Input();
        $this->inputWidget = $this->makeWidget('Backend\Widgets\Form', $config);
        $this->inputWidget->bindToController();

    }

    public function index() {

        $this->pageTitle = 'Classify';
        $this->bodyClass = 'compact-container ';

        $workuser_id = Session::get('grade_workuser_id','');
        if ($workuser_id=='') {
            $workuser_id = ertUsers::getId();
        }
        ertLog::logLine("D-index; workuser_id=$workuser_id");

        // workuser
        $this->vars['workuser_id'] = $workuser_id;

        $this->asExtension('ListController')->index();
    }

    /**
     * Filter based on dynamic data
     * - notification status
     * - logged in user
     *
     * @param $filter
     */
    public function listFilterExtendScopes($filter) {

        // if manager

        if (!ertUsers::isUser()) {

            $workuser_id = ertUsers::getId();
            $filter->addScopes([
                'workuser_id' => [
                    'label' => 'My work',                               // @TO-DO; lang translation
                    'type' =>'checkbox',
                    'default' => 1,
                    'conditions' => "workuser_id=$workuser_id",
                ],
            ]);

        }

    }

    /**
     * Filter on status_code=grade
     *
     * @param $query
     */
    public function listExtendQuery($query) {
        $query->where('status_code',ERT_STATUS_GRADE)->orderBy('filenumber','ASC');
    }

    // list_toolbar function(s)

    public function onNotIllegalClose() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $input = Input::find($check);
            if ($input) {

                $nots = ertGrade::getNotifications($input->id);
                foreach ($nots AS $not) {
                    $notification = Notification::find($not->notification_id);
                    if ($notification) {
                        $notification->grade_code = ERT_GRADE_NOT_ILLEGAL;
                        $notification->status_code = ERT_STATUS_CLOSE;
                        $notification->save();
                        $notification->logText('closed with classification not-illegal');
                        if ($notification->reference != '' && ertICCAM2ERT::isActive() ) {
                            ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                'record_type' => class_basename($notification),
                                'record_id' => $notification->id,
                                'object_id' => $notification->reference,
                                'action_id' => ERT_ICCAM_ACTION_NI,     // NOT_EILLEGAL
                                'country' => 'NL',
                                'reason' => 'ERT reported NI',
                            ]);
                        }
                    }
                }
                $input->grade_code = ERT_GRADE_NOT_ILLEGAL;
                $input->status_code = ERT_STATUS_CLOSE;
                $input->save();
                $input->logText('closed with classification not-illegal');
                if ($input->reference != '' && ertICCAM2ERT::isActive() ) {
                    ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                        'record_type' => class_basename($input),
                        'record_id' => $input->id,
                        'object_id' => $input->reference,
                        'action_id' => ERT_ICCAM_ACTION_NI,     // NOT_EILLEGAL
                        'country' => 'NL',
                        'reason' => 'ERT reported NI',
                    ]);
                }
                ertLog::logLine("D-Filenumber=$input->filenumber, url=$input->url, set on $input->status_code (with found notifications)");
            }
        }
        Flash::info('Selected url(s) set on closed');
        return $this->listRefresh();
    }

    public function onIgnoreClose() {

        $checked = input('checked');
        foreach ($checked AS $check) {
            $input = Input::find($check);
            if ($input) {
                $nots = ertGrade::getNotifications($input->id);
                foreach ($nots AS $not) {
                    $notification = Notification::find($not->notification_id);
                    if ($notification) {
                        $notification->grade_code = ERT_GRADE_IGNORE;
                        $notification->status_code = ERT_STATUS_CLOSE;
                        $notification->save();
                        $notification->logText('closed with classification ignore');
                    }
                }
                $input->grade_code = ERT_GRADE_NOT_ILLEGAL;
                $input->status_code = ERT_STATUS_CLOSE;
                $input->save();
                $input->logText('closed with classification ignore');
                ertLog::logLine("D-Filenumber=$input->filenumber, url=$input->url, set on $input->status_code (with found notifications)");
            }
        }
        Flash::info('Selected url(s) set on closed');
        return $this->listRefresh();
    }

    /**
     * update -> analyze screen of input (id)
     *
     * @param $recordId
     * @param null $context
     * @return mixed
     */
    public function update($recordId, $context=null) {

        $this->pageTitle = 'Analyze';

        // 2019/7/12/Gs: current user
        //$input = Input::where('id',$recordId)->first();
        //$workuser_id = $input->workuser_id;
        $workuser_id = ertUsers::getId();

        $columnsize = $this->getColumnsize();
        $screensize = $this->getScreensize();

        $next = $this->nextCall($recordId,$workuser_id,$screensize,$columnsize,'', true);

        //$input = Db::table('reportertool_eokm_input')->where('id',$recordId)->first();
        //$this->initForm($input);
        //$this->initRelation($input,'abusecontacts');

        return $this->makePartial('update', ['nexttxt' => $next['id_grade_screen']] );
    }

    /**
     * Next (prev) input with notification(s)
     *
     * - build screen; top the input spec, below for each notification (image) an image preview (small) and action buttons
     * - show previous and next based on number of current input
     *
     * - function can be called with START, PREVIOUS and NEXT
     * - function can also called directly with parameters -> then no next or previous, but current input_id
     *
     * @return screen output
     */

    public function nextCall($input_id,$workuser_id,$screensize=0,$columnsize=0,$warning='',$current=false) {

        if (empty($screensize)) $screensize = input('screensize');
        if (empty($screensize)) $screensize = $this->getScreensize();

        if (empty($columnsize)) $columnsize = input('columnsize');
        if (empty($columnsize)) $columnsize = $this->getColumnsize();
        // save option for user
        $this->setColumnsize($columnsize);

        $txt = '';
        $imagelastloaded = 0;

        $next = ertGrade::next($input_id,$workuser_id,$current);

        if ($next) {

            ertLog::logLine("D-nextCall; new input_id=" . $next->input->id. ", workuser_id=$workuser_id, screensize=$screensize, columnsize=$columnsize");

            $showignore = $this->getIgnoreShowHide();

            // default values
            $colnum = $coladdnum = 0; $coltxtsize = 10; $colspan = 2; $colboxheightsize = 400; $colimgsize = 380;

            // check screensize
            if ($screensize < 0) $screensize = 0;

            // check columns input
            if (!in_array($columnsize, array('2','3','4'))) $columnsize = 3;
            foreach ($this->_displaycolumns AS $size => $displaycolumn) {
                if ($screensize >= $size) {
                    $colspan = $displaycolumn[$columnsize]['colspan'];
                    $colimgsize = $displaycolumn[$columnsize]['imgsize'];
                    $colboxheightsize = $displaycolumn[$columnsize]['boxheightsize'];
                    $coltxtsize = $displaycolumn[$columnsize]['txtsize'];
                    ertLog::logLine("D-nextCall; use viewport-size=$size; colspan=$colspan, colimgsize=$colimgsize, colboxheightsize=$colboxheightsize, coltxtsize=$coltxtsize");
                    break;
                }
            }
            $this->setColimgsize($colimgsize);
            // last add colspan
            $colspanlast = 12 - $colspan;

            $imagestxt = '';
            $imageshow = 0;

            // moved to lazy loading
            //$imagemax =  Config::get('reportertool.eokm::grade.max_images_column_screen',250);
            //ertLog::logLine("D-max_images_column_screen=$imagemax");

            foreach ($next->notifications AS $notification) {

                if ($showignore || $notification->grade_code!=ERT_GRADE_IGNORE) {

                    // check if first of last row

                    $rowfirst = $rowlast = false;
                    if ($colnum==0) {
                        $rowfirst = true;
                    } elseif ($colnum >= $colspanlast) {
                        $rowlast = true;
                        $colnum = -$colspan;
                    }
                    $colnum += $colspan;

                    // detect img size setting

                    $imgsize = $this->getImageSizeAttr($notification,$colimgsize);
                    $imgbigsize = $this->getImageSizeAttr($notification,$colimgsize,true);

                    // fill whois fields

                    // init
                    $info = [];
                    foreach ($this->_infofields AS $fld => $label) {
                        $field = [
                                'name' => $fld,
                                'label' => $label,
                                'value' => (($notification->$fld)?$notification->$fld:'(unknown)'),
                                'mark' => false,
                            ];
                        $info[] = $field;
                    }
                    // get fields from Abusecontact WhoIs info
                    $whoisfields = Abusecontact::getWhois($notification);
                    foreach ($whoisfields as $fld => $val) {
                        if ($fld!=ERT_HOSTER.'_rawtext' && $fld!=ERT_REGISTRAR.'_rawtext') {
                            $field = [
                                'name' => $fld,
                                'label' => $this->_labels[$fld],
                                'value' => ($val ? $val : ERT_NOT_SET),
                                'mark' => false,
                            ];
                            if ($fld==ERT_HOSTER.'_country' || $fld==ERT_REGISTRAR.'_country') {
                                $field['mark'] = !ertGrade::isNL($val);
                            }
                            $info[] = $field;
                        }
                    }
                    $whoisraw = (isset($whoisfields[ERT_HOSTER.'_rawtext'])) ? $whoisfields[ERT_HOSTER.'_rawtext'] : '';
                    $whoisraw .= "\n\n";
                    $whoisraw .= (isset($whoisfields[ERT_REGISTRAR.'_rawtext'])) ? $whoisfields[ERT_REGISTRAR.'_rawtext'] : '';

                    /**
                     * 2019/8/1/Gs:
                     *
                     * if manual add an url then hash can be empty
                     * always fill for unique javascript reference
                     *
                    */
                    if (empty($notification->url_hash)) {
                        $hash = sprintf('%s%016d', md5($notification->url),strlen($notification->url));   // 48 tekens
                        $notification->url_hash = $hash;
                        ertLog::logLine("D-nextCall; notification with empty hash; id=$notification->id; url_hash=$hash");
                        $notification->save();
                    }

                    // set buttons on/off

                    $setbuttons = $this->setButtons($notification,$workuser_id);
                    $showresult = $this->makePartial('js_buttonresult',
                        ['hash' => $notification->url_hash,
                            'buttonsets' => $setbuttons['buttonsets'],
                            'class' => $setbuttons['class']
                        ]
                    );

                    // create partial for this notification (image)

                    $ignoretxt = (($notification->grade_code==ERT_GRADE_IGNORE)? 'on ignore (skip grading)' : 'NOT on ignore (anymore)');

                    $cssnote = ($notification->note!='') ? 'grade_button_notefilled' : '';

                    if ($imageshow < ERT_GRADE_LOAD_IMAGE_NUMBER) {
                        if ($notification->url_type == ERT_URL_TYPE_IMAGEURL) {
                            $src = ertBrowser::getImageBase64($notification->url,$notification->url_hash);
                        } else {
                            $src = ertBrowser::getImageBase64($notification->url,$notification->url_hash, false,ERT_IMAGE_IS_VIDEO);
                        }
                    } else {
                        if ($imagelastloaded == 0) $imagelastloaded = $notification->id;
                        $src = '';
                    }

                    $imagestxt .= $this->makePartial('show_grade_image',
                        [   'id' => $notification->id,
                            'input_id' => $next->input->id,
                            'workuser_id' => $workuser_id,
                            'base' => $notification->url_base,
                            'url' => $notification->url,
                            'src' => $src,
                            'tabtype' => (($notification->url_type==ERT_URL_TYPE_IMAGEURL) ? 'Image' : 'Video'),
                            'hash' => $notification->url_hash,
                            'imgsize' => $imgsize,
                            'imgbigsize' => $imgbigsize,
                            'boximgsize' => $colimgsize,
                            'boxheightsize' => $colboxheightsize,
                            'txtsize' => $coltxtsize,
                            'rowfirst' => $rowfirst,
                            'rowlast' => $rowlast,
                            'colspan' => $colspan,
                            'coladdnum' => $coladdnum,
                            'ignoretxt' => $ignoretxt,
                            'cssnote' => $cssnote,
                            'info' => $info,
                            'imagewhoisraw' => ertWhois::htmlOutputRaw($whoisraw),
                            'js_result' => $showresult,
                            'showignore' => $showignore,
                        ] );

                    $imageshow += 1;

                    //if ($imageshow > $imagemax) { break;}

                }

            }

            if ($colnum > 0) {

                $coladdnum = 12 - $colnum;

                if ($coladdnum > 0) {
                    $imagestxt .= $this->makePartial('show_grade_image',
                        [   'rowfirst' => false,
                            'rowlast' => true,
                            'coladdnum' => $coladdnum,
                        ] );
                }

            }

            if (count($next->notifications) == 0) {
                $imagestxt = '<h4>Input has NO notifications (images) to analyze</h4>';
            } elseif ($imageshow == 0) {
                $imagestxt = '<h4>Input notifications (images) are hidden (ignored)</h4>';
            //} elseif ($imageshow > $imagemax ) {
            //     $warning = "To much images to show on one screen - skip " . (count($next->notifications) - $imageshow) . " images";
            }

            $inputnextprev = ertGrade::inputToGrade($workuser_id,$next->input->id);
            $workuser = ertUsers::getFullName($workuser_id);

            $whoisfields = Abusecontact::getWhois($next->input);
            //$whoisraw = $whoisfields[ERT_HOSTER.'_rawtext'] . "\n\n" . $whoisfields[ERT_REGISTRAR.'_rawtext'];
            $whoisraw = (isset($whoisfields[ERT_HOSTER.'_rawtext'])) ? $whoisfields[ERT_HOSTER.'_rawtext'] : '';
            $whoisraw .= "\n\n";
            $whoisraw .= (isset($whoisfields[ERT_REGISTRAR.'_rawtext'])) ? $whoisfields[ERT_REGISTRAR.'_rawtext'] : '';

            $details = [
                [
                    'input link' => 'url|<a href="'.$next->input->url.'" target="_blank">'.$next->input->url.'</a>',
                    'hoster' => 'hoster|'.$whoisfields[ERT_HOSTER.'_owner'],
                    'registrar' => 'registrar|'.$whoisfields[ERT_REGISTRAR.'_owner'],
                ],
                [
                    'input host' => 'inputhost|'.$next->input->url_host,
                    '_abuse' => 'host_abuse|'.$whoisfields[ERT_HOSTER.'_abusecontact'],
                    '_abuse ' => 'registrar_abuse|'.$whoisfields[ERT_REGISTRAR.'_abusecontact'],
                ],
                [
                    'source' => 'source|'.$next->input->source_code,
                    '_abusecustom' => 'host_custom|'.($whoisfields[ERT_HOSTER.'_abusecustom'] ? $whoisfields[ERT_HOSTER.'_abusecustom'] : ERT_NOT_SET),
                    '_abusecustom ' => 'registrar_custom|'.($whoisfields[ERT_REGISTRAR.'_abusecustom'] ? $whoisfields[ERT_REGISTRAR.'_abusecustom'] : ERT_NOT_SET),
                ],
                [
                    'type' =>  'type|'.$next->input->type_code,
                    '_country' => 'host_country|'.$whoisfields[ERT_HOSTER.'_country'],
                    '_country ' => 'registrar_country|'.$whoisfields[ERT_REGISTRAR.'_country'],
                ],
                [
                    'analyst' => 'analyst|'.$workuser,
                    '_IP' => 'host_query|'.$whoisfields[ERT_HOSTER.'_lookup'],
                    '_domain' => 'registrar_query|'.$whoisfields[ERT_REGISTRAR.'_lookup'],
                ],
                [
                    'reference' => 'reference|'.$next->input->reference,
                    '#images' => 'images|'.count($next->notifications),
                    'whoIs raw' => 'whoisraw|<a data-toggle="modal" data-size="large" href="#InputWhoisRaw" class="">raw info</a>',
                ],

            ];

            $locked_workuser = '';
            $lock = ertGrade::getLock($next->input->id);
            if ($lock!='') {
                if ($lock->workuser_id!=$workuser_id) {
                    $locked_workuser = ertUsers::getFullName($lock->workuser_id);
                    ertLog::logLine("D-nextCall; input locked by=$lock->workuser_id, fullname=$locked_workuser");
                }
            } else {
                // set lock if work done
                if (ertGrade::countSelectedNotifications($workuser_id,$next->input->id) > 0 || ertGrade::countNotificationsWithGrade($next->input->id,ERT_GRADE_UNSET, '<>') > 0) {
                    ertGrade::setLock($next->input->id,$workuser_id);
                }
            }

            $classnotefilled = ($next->input->note!='') ? 'grade_button_notefilled' : '';

            $js_police = $this->makePartial('js_buttonresult',
                [
                    'hash' => 'input'.$next->input->id,
                    'buttonsets' => [
                        'POLICE' => (($next->input->status_code==ERT_STATUS_FIRST_POLICE) ? 'true':'false'),
                    ],
                ]
            );


            $inputtxt = $this->makePartial('show_grade_input',
                [   'id' => $next->input->id,
                    'workuser_id' => $workuser_id,
                    'workuser' => $workuser,
                    'locked_workuser' => $locked_workuser,
                    'details' => $details,
                    'screensize' => $screensize,
                    'columnsize' => $columnsize,
                    'inputcnt' => $inputnextprev['count'],
                    'inputnum' => $inputnextprev['number'],
                    'inputfirst' => ($inputnextprev['first'] == $next->input->id),
                    'inputlast' => ($inputnextprev['last'] == $next->input->id),
                    'filenumber' => $next->input->filenumber,
                    'grade_code' => $next->input->grade_code,
                    'classnotefilled' => $classnotefilled,

                    'inputwhoisraw' => ertWhois::htmlOutputRaw($whoisraw),
                    'url_host' => $next->input->url_host,

                    'js_showhide' => $this->jsIgnoreShowHide(),
                    'js_police' => $js_police,
                ] );


            $txt = $this->makePartial('show_grade_screen',
                [   'inputtxt' => $inputtxt,
                    'imagestxt' => $imagestxt,
                    'input_id' => $next->input->id,
                    'imagelastloaded' => $imagelastloaded,
                ] );


        } else {

            $warning = 'No (more) to analyze (with you as analyst)';

        }

        if ($warning) {
            Flash::warning($warning);
        }

        return ['id_grade_screen' => $txt];
    }

    public function onScrollNext() {

        $input_id = input('input_id');
        $lastLoading_id = input('lastLoading_id');
        ertLog::logLine("D-onScrollNext: input_id=$input_id, lastLoading_id=$lastLoading_id");

        //trace_sql();
        $notifications = ertGrade::getNotificationsFrom($input_id, $lastLoading_id,ERT_GRADE_LOAD_IMAGE_NUMBER);
        $showignore = $this->getIgnoreShowHide();
        $colimgsize = $this->getColimgsize();

        $showresult = '';
        $images = [];
        foreach ($notifications AS $notification) {
            if ($showignore || $notification->grade_code != ERT_GRADE_IGNORE) {
                $imgsize = $this->getImageSizeAttr($notification,$colimgsize);
                if ($notification->url_type == ERT_URL_TYPE_IMAGEURL) {
                    $src = ertBrowser::getImageBase64($notification->url,$notification->url_hash);
                } else {
                    $src = ertBrowser::getImageBase64($notification->url,$notification->url_hash, false,ERT_IMAGE_IS_VIDEO);
                }
                $images[] = [
                    'data' => $src,
                    'hash' => $notification->url_hash,
                    'imgsize' => $imgsize,
                ];
                $lastLoading_id = $notification->id;
            }
        }
        ertLog::logLine("D-onScrollNext: found lastLoading_id=$lastLoading_id, count=" . count($images) );

        if (count($images) > 0) {
            if (ERT_GRADE_LOAD_IMAGE_NUMBER > count($images)) {
                // last one -> move after last one
                $lastLoading_id += 1;
            }
            $showresult = $this->makePartial('js_load_images',
                [
                    'images' => $images,
                    'lastLoading_id' => $lastLoading_id,
                ]
            );
        }

        return ['show_result' => $showresult];
    }

    public function onUnlockInput() {

        $input_id = input('input_id');
        $workuser_id = input('workuser_id');

        if ($input_id && $workuser_id) {
            ertGrade::setLock($input_id,$workuser_id);
            Flash::info('Input unlocked');
        } else {
            ertLog::logLine("D-onUnlockInput; cannot unlock; input_id=$input_id, workuser_id=$workuser_id");
            Flash::warning('Cannot unlock input');
        }

        // refresh screen
        return $this->nextCall($input_id,$workuser_id,0,0,'',true);
    }

    public function onDone() {

        $input_id = input('input_id');
        $workuser_id = input('workuser_id');
        $screensize = input('screensize');
        $columnsize = input('columnsize');

        $ret = ertGrade::getNotificationsWithGrade($input_id,ERT_GRADE_UNSET);
        $cnt = count($ret);
        ertLog::logLine("D-onDone; input_id=" . $input_id . ", workuser_id=$workuser_id, screensize=$screensize, columnsize=$columnsize, cnt=$cnt"  );

        if ($cnt > 0) {

            $warning = (($cnt==1) ? "$cnt notification (image)" : "$cnt notifications (images) are") .  " NOT CLASSIFIED - please classify first";
            $current = true;

        } else {

            $input = Input::find($input_id);

            // check if illegal and NO host/registrar set
            if ($input->grade_code==ERT_GRADE_ILLEGAL && ($input->registrar_abusecontact_id==0 || $input->host_abusecontact_id==0) ) {

                $warning = "Input illegal classified - but no registrar or hoster is filled";
                $current = true;

            } elseif (ertGrade::getIllegalNotificationsWithNoRegistrarHosterSet($input_id) > 0) {

                $warning = "Image(s) illegal classified - but no registrar or hoster is filled";
                $current = true;

            } else {

                // start CHECKONLINE

                // first look if ILLEGAL -> then checkonline
                if ($input->grade_code==ERT_GRADE_ILLEGAL) {
                    $input->online_counter = 0;
                    if ($input->status_code != ERT_STATUS_FIRST_POLICE) {
                        $input->status_code = ERT_STATUS_SCHEDULER_CHECKONLINE;
                        $input->logText("Classification done; status is '$input->status_code'; checkonline (NTD) started");
                    } else {
                        $input->logText("Classification done; status is '$input->status_code'; wait for police handling");
                    }
                } else {

                    // if ICCAM reportID set then export action NI
                    if ($input->grade_code == ERT_GRADE_NOT_ILLEGAL && $input->reference != '' && (ertICCAM2ERT::isActive()) ) {
                        ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                            'record_type' => class_basename($input),
                            'record_id' => $input->id,
                            'object_id' => $input->reference,
                            'action_id' => ERT_ICCAM_ACTION_NI,     // NOT_EILLEGAL
                            'country' => 'NL',
                            'reason' => 'ERT reported NI',
                        ]);
                    }

                    $input->status_code = ERT_STATUS_CLOSE;
                    $input->logText("Classification done; not illegal; status set on '$input->status_code' ");
                }
                $input->save();

                // check linked notifications and start also CHECKONLINE for these
                $nots = ertGrade::getNotificationsWithGrade($input_id,ERT_GRADE_ILLEGAL);
                foreach ($nots AS $not) {
                    $not->online_counter = 0;   // start first NTD
                    if ($not->status_code != ERT_STATUS_FIRST_POLICE) {
                        $not->status_code = ERT_STATUS_SCHEDULER_CHECKONLINE;
                        $not->logText("Classification done; status is '$not->status_code'; checkonline (NTD) started");
                    } else {
                        $not->logText("Classification done; status is '$not->status_code'; wait for police handling");
                    }
                    $not->save();
                }

                if (ertICCAM2ERT::isActive()) {
                    // if ICCAM reportID set then export action NI
                    $nots = ertGrade::getNotificationsWithGrade($input_id,ERT_GRADE_NOT_ILLEGAL);
                    foreach ($nots AS $not) {
                        if ($not->reference != '') {
                            // ICCAM set ERT_ICCAM_ACTION_NI
                            ertExportICCAM::addExportAction(ERT_INTERFACE_ICCAM_ACTION_EXPORTACTION,[
                                'record_type' => class_basename($not),
                                'record_id' => $not->id,
                                'object_id' => $not->reference,
                                'action_id' => ERT_ICCAM_ACTION_NI,     // NOT_EILLEGAL
                                'country' => 'NL',
                                'reason' => 'ERT reported NI',
                            ]);
                        }
                    }
                }

                $warning = '';
                $input_id = 0;
                $current = false;

                ertGrade::resetLock($input_id);

                //Flash::info('Input done - moved to next');

            }

        }

        if ($current) {
            return $this->nextCall($input_id,$workuser_id,$screensize,$columnsize,$warning,$current);
        } else {
            return Redirect::to('/backend/reportertool/eokm/Grade')->with('message', 'Report done');
        }
    }

    /**
     * POLICE button
     *
     * Return question screen
     *
     * @return bool|mixed
     */
    public function onImagePolice() {

        $workuser_id = input('workuser_id');
        $record_id = input('record_id');
        $recordtype = input('recordtype');
        ertLog::logLine("D-onImagePolice; record_id=$record_id, recordtype=$recordtype");

        if ($recordtype==ERT_NOTIFICATION_TYPE) {
            $rec = Notification::find($record_id);
        } elseif ($recordtype=='input') {
            $rec = Input::find($record_id);
        } else {
            $rec = '';
        }

        $showresult = '';
        if ($rec) {

            if ($rec->grade_code == ERT_GRADE_ILLEGAL) {

                $rec->online_counter = 1;
                $rec->status_code = ($rec->status_code==ERT_STATUS_FIRST_POLICE) ? ERT_STATUS_GRADE: ERT_STATUS_FIRST_POLICE;
                $rec->logText("Set status on: " . $rec->status_code );
                $rec->save();

                $showresult = $this->makePartial('js_buttonresult',
                    [
                        'hash' => ( ($recordtype==ERT_NOTIFICATION_TYPE) ? $rec->url_hash : 'input'.$rec->id),
                        'buttonsets' => [
                            'POLICE' => (($rec->status_code==ERT_STATUS_FIRST_POLICE) ? 'true':'false'),
                        ],
                    ]
                );
                Flash::info('FIRST POLICE '.(($rec->status_code==ERT_STATUS_FIRST_POLICE)?'set':'reset') );

            } else {
                Flash::warning('Is not set on ILLEGAL' );
            }
        }

        return ['show_result' => $showresult ];
    }

    /**
     * ILLEGAL button
     *
     * Return question screen
     *
     * @return bool|mixed
     */
    public function onImageIllegal() {

        $workuser_id = input('workuser_id');
        $record_id = input('record_id');
        $recordtype = input('recordtype');
        ertLog::logLine("D-onImageIllegal; record_id=$record_id, recordtype=$recordtype");
        $single = true;
        if ($recordtype=='notification') {
            $rec = Notification::find($record_id);
        } elseif ($recordtype=='input') {
            $rec = Input::find($record_id);
        } else {
            $rec = '';
        }
        if ($rec) {
            $show_questions = $this->show_grade_questions(ERT_GRADE_QUESTION_GROUP_ILLEGAL,$workuser_id,$single, $rec);
        } else {
            Flash::error("Unknown $recordtype (image)!?");
            $show_questions = false;
        }

        return $show_questions;
    }

    /**
     * IGNORE button
     *
     * @return array
     */
    public function onImageIgnore() {

        $workuser_id = input('workuser_id');
        $input_id = input('input_id');
        $record_id = input('record_id');
        $recordtype = input('recordtype');
        ertLog::logLine("D-onImageIgnore; input_id=$input_id, record_id=$record_id, recordtype=$recordtype ");

        // set select
        if ($recordtype=='notification') {

            ertGrade::setGradeSelected($workuser_id, $record_id, false);

            // zet status on ERT_GRADE_IGNORE
            $notification = Notification::find($record_id);
            $notification->status_code = ERT_STATUS_CLOSE;
            $notification->grade_code = ERT_GRADE_IGNORE;
            $notification->logText("Set grade_code=$notification->grade_code, set status_code=$notification->status_code");
            $notification->save();

            if (!$this->getIgnoreShowHide()) {

                // fadeOut image -> refresh screen

                // refresh screen
                return $this->nextCall($input_id,$workuser_id,0,0,'',true);

            } else {

                $setbuttons = $this->setButtons($notification,$workuser_id);
                $showresult = $this->makePartial('js_buttonresult',
                    ['hash' => $notification->url_hash,
                        'buttonsets' => $setbuttons['buttonsets'],
                        'class' => $setbuttons['class']
                    ]
                );

                Flash::info('Notification (image) status set on ignore');

            }

        } elseif ($recordtype=='Input') {
            // not yet
            $showresult = '';
        }

        return ['show_result' => $showresult ];
    }

    /**
     * NOT ILLEGAL button
     *
     * @return bool|mixed
     */
    public function onImageNotIllegal() {

        $workuser_id = input('workuser_id');
        $record_id = input('record_id');
        $recordtype = input('recordtype');
        ertLog::logLine("D-onImageNotIllegal; record_id=$record_id, recordtype=$recordtype");
        if ($recordtype=='notification') {
            $rec = Notification::find($record_id);
        } elseif ($recordtype=='input') {
            $rec = Input::find($record_id);
        } else {
            $rec = '';
        }

        if ($rec) {
            $show_questions = $this->show_grade_questions(ERT_GRADE_QUESTION_GROUP_NOT_ILLEGAL,$workuser_id,true,$rec);
        } else {
            Flash::error("Unknown $recordtype(image)!?");
            $show_questions = false;
        }

        return $show_questions;
    }


    /**
     * INPUT EDIT button
     *
     * @return bool|mixed
     */
    public function onInputEdit() {

        $record_id = input('record_id');
        ertLog::logLine("D-onInputEdit; record_id=$record_id ");

        $rec = Input::find($record_id);

        if ($rec) {

            // config (reuse) widget
            $config = $this->makeConfig('$/reportertool/eokm/models/input/fields_grade.yaml');
            $config->model = $rec;
            $this->inputWidget = $this->makeWidget('Backend\Widgets\Form', $config);

            $show_whois = $this->makePartial('show_input_edit',[
                'record_id' => $record_id,
                'inputWidget' => $this->inputWidget,
            ]);

        } else {
            ertLog::logLine("W-onInputEdit; unknown record (id=$record_id)");
            Flash::error("Unknown record!?");
            $show_whois = false;
        }

        return $show_whois;
    }

    function onInputEditSave() {

        // note: specific for input
        //trace_log(post());
        $record_id = input('record_id');
        $note = input('note');
        $source_code = input('source_code');
        $type_code = input('type_code');

        ertLog::logLine("D-onInputEditSave; record_id=$record_id");
        $rec = Input::find($record_id);

        if ($rec) {

            $note = trim($note);

            $rec->note = $note;
            $rec->source_code = $source_code;
            $rec->type_code = $type_code;
            $rec->save();

            $cssfield = 'inputeditbutton';
            $cssremove = ($note=='') ? 'grade_button_notefilled' : '';
            $cssadd = ($note!='') ? 'grade_button_notefilled' : '';
            //ertLog::logLine("D-onInputEditSave; $cssfield, note=>$note<, cssremove=$cssremove, cssadd=$cssadd");
            $showresult = $this->makePartial('js_update_css',
                ['cssfield' => $cssfield,
                    'cssremove' => $cssremove,
                    'cssadd' => $cssadd]);

            // update WHOIS

            $whoisfields = Abusecontact::getWhois($rec);
            $whoisraw = $whoisfields[ERT_HOSTER.'_rawtext'] . "\n\n" . $whoisfields[ERT_REGISTRAR.'_rawtext'];

            $detailfields = [
                'hoster' => $whoisfields[ERT_HOSTER.'_owner'],
                'host_abuse' => $whoisfields[ERT_HOSTER.'_abusecontact'],
                'host_custom' => ($whoisfields[ERT_HOSTER.'_abusecustom'] ? $whoisfields[ERT_HOSTER.'_abusecustom'] : ERT_NOT_SET),
                'host_country' => $whoisfields[ERT_HOSTER.'_country'],
                'host_query' => $whoisfields[ERT_HOSTER.'_lookup'],
                'registrar' => $whoisfields[ERT_REGISTRAR.'_owner'],
                'registrar_abuse ' => $whoisfields[ERT_REGISTRAR.'_abusecontact'],
                'registrar_custom ' => ($whoisfields[ERT_REGISTRAR.'_abusecustom'] ? $whoisfields[ERT_REGISTRAR.'_abusecustom'] : ERT_NOT_SET),
                'registrar_country ' => $whoisfields[ERT_REGISTRAR.'_country'],
                'registrar_query ' => $whoisfields[ERT_REGISTRAR.'_lookup'],
                'inputwhoisrawdata' => ertWhois::jsOutputRaw($whoisraw),
            ];
            $showresult .= $this->makePartial('js_update_detail', ['detailfields' => $detailfields]);

        } else {
            ertLog::logLine("W-onInputEditSave; record not found (record_id=$record_id) ");
            Flash::error("Unknown record!?");
            $showresult = false;
        }

        return ['show_result' => $showresult ];
    }


    public function onDomainruleEdit() {

        $record_id = input('record_id');
        ertLog::logLine("D-onDomainruleEdit; record_id=$record_id ");

        $domainrule = new \ReporterTool\EOKM\Models\Domainrule();
        $domainrule->setInputrecord($record_id);
        $domainrule->setRuleExclode([ERT_RULE_TYPE_NONOTSCRAPE]);

        // config (reuse) widget
        $config = $this->makeConfig('$/reportertool/eokm/models/domainrule/fields_domainselect.yaml');
        //$config = $this->makeConfig('$/reportertool/eokm/models/domainrule/columns.yaml');
        $config->model = $domainrule;
        $this->inputWidget = $this->makeWidget('Backend\Widgets\Form', $config);
        //$this->inputWidget = $this->makeWidget('Backend\Widgets\Lists', $config);

        $show_domainrule = $this->makePartial('show_domainrule_edit',[
            'input_id' => $record_id,
            'inputWidget' => $this->inputWidget,
        ]);

        return $show_domainrule;
    }

    public function onDomainruleEditSave() {

        $input_id = input('input_id');
        ertLog::logLine("D-onDomainruleEditSave; input_id=$input_id");
        //trace_log(\Input::all());

        // validate input
        $validator = Validator::make(
            \Input::all(),
            [
                'domain_select' => 'required|string',
                'type_code' => 'required',
                'ip' => 'required_if:type_code,proxy_service|ip',
                'host_abusecontact_id' => 'required_if:type_code,proxy_service,site_owner,host_whois,registrar_whois'
            ],
            [
                'required' => 'The :attribute field is required',
            ]
        );

        if ($validator->passes()) {

            $domain = input('domain_select');
            $type_code = input('type_code');

            $domainrule = Domainrule::where('domain',$domain)->where('type_code',$type_code)->first();
            if ($domainrule) {
                //Flash::error('Domain rule already exists - change this within the RULES function');
                $domainrule->ip = input('ip', '');
                $domainrule->abusecontact_id = input('host_abusecontact_id', null);
            } else {
                // make domainrule
                $domainrule = new Domainrule();
                $domainrule->domain = $domain;
                $domainrule->type_code = $type_code;
                $domainrule->ip = input('ip', '');
                $domainrule->abusecontact_id = input('host_abusecontact_id', null);
            }
            $domainrule->save();

            // proces on input

            $input = Input::find($input_id);
            if ($input) {

                // verify WhoIs
                $whois = ertWhois::verifyWhoIs($input, false);
                if ($whois['status_success']) {
                    $input->save();
                }

                // notifictions
                $notifications = ertGrade::getNotifications($input_id);
                foreach ($notifications AS $notification) {
                    // verify WhoIs
                    $whois = ertWhois::verifyWhoIs($notification, false);
                    if ($whois['status_success']) {
                        $notification->save();
                    }
                }

            }

            // refresh
            return Redirect::refresh();

        } else {
            throw new ValidationException($validator);
        }

    }

    public function onDomainruleRunSave() {

        $input_id = input('input_id');
        ertLog::logLine("D-onDomainruleRunSave; input_id=$input_id");
        //trace_log(\Input::all());

        // proces on input

        $input = Input::find($input_id);
        if ($input) {

            // verify WhoIs
            $whois = ertWhois::verifyWhoIs($input, false);
            if ($whois['status_success']) {
                $input->save();
            }

            // notifictions
            $notifications = ertGrade::getNotifications($input_id);
            foreach ($notifications AS $notification) {
                // verify WhoIs
                $whois = ertWhois::verifyWhoIs($notification, false);
                if ($whois['status_success']) {
                    $notification->save();
                }
            }

        }

        // refresh
        return Redirect::refresh();
    }
    /**
     * NOTIFICATION EDIT button
     *
     * @return bool|mixed
     */
    public function onNotificationEdit() {

        $record_id = input('record_id');
        ertLog::logLine("D-onNotificationEdit; record_id=$record_id ");

        $rec = Notification::find($record_id);
        if ($rec) {

            // config (reuse) widget
            $config = $this->makeConfig('$/reportertool/eokm/models/notification/fields_grade.yaml');
            $config->model = $rec;
            $this->inputWidget = $this->makeWidget('Backend\Widgets\Form', $config);

            $show_notification = $this->makePartial('show_notification_edit',[
                'record_id' => $record_id,
                'inputWidget' => $this->inputWidget,
            ]);

        } else {
            ertLog::logLine("W-onNotificationEdit; unknown record (id=$record_id)");
            Flash::error("Unknown record !?");
            $show_notification = false;
        }

        return $show_notification;
    }

    function onNotificationEditSave() {

        // note: specific for notification
        //trace_log(post());
        $record_id = input('record_id');
        $note = input('note');
        $type_code = input('type_code');

        ertLog::logLine("D-onNotificationEditSave; record_id=$record_id");
        $rec = Notification::find($record_id);

        if ($rec) {

            $note = trim($note);

            $rec->note = $note;
            $rec->type_code = $type_code;
            $rec->save();

            $cssfield = 'idButtonNote' . $rec->url_hash;
            $cssremove = ($note=='') ? 'grade_button_notefilled' : '';
            $cssadd = ($note!='') ? 'grade_button_notefilled' : '';
            $showresult = $this->makePartial('js_update_css',
                ['cssfield' => $cssfield,
                    'cssremove' => $cssremove,
                    'cssadd' => $cssadd]);

            // update INFO

            $fields = [];
            foreach ($this->_infofields AS $fld => $label) {
                $field = [
                    'name' => $fld,
                    'label' => $label,
                    'value' => (($rec->$fld)?$rec->$fld:'(unknown)'),
                    'mark' => false,
                ];
                $fields[] = $field;
            }
            $whoisfields = Abusecontact::getWhois($rec);
            foreach ($whoisfields as $fld => $val) {
                if ($fld!=ERT_HOSTER.'_rawtext' && $fld!=ERT_REGISTRAR.'_rawtext') {
                    $field = [
                        'name' => $fld,
                        'value' => ($val ? $val : ERT_NOT_SET),
                    ];
                    $fields[] = $field;
                }
            }
            $field = [
                'name' => 'whoisraw',
                'value' => ertWhois::jsOutputRaw($whoisfields[ERT_HOSTER.'_rawtext'] . "\n\n" . $whoisfields[ERT_REGISTRAR.'_rawtext']),
            ];
            $fields[] = $field;

            $showresult .= $this->makePartial('js_update_fields',
                ['hashes' => [$rec->url_hash],
                 'fields' => $fields
                ]);

        } else {
            ertLog::logLine("W-onNotificationEditSave; record not found (record_id=$record_id) ");
            Flash::error("Unknown record!?");
            $showresult = false;
        }

        return ['show_result' => $showresult ];
    }

    /**
     * SELECTED set WhoIs (hoster/regisrar)
     *
     */

    public function onSelectedSetWhois() {

        //trace_log(post());

        $input_id = input('input_id');
        $workuser_id = input('workuser_id');
        ertLog::logLine("D-onSelectedSetWhois; input_id=$input_id, workuser_id=$workuser_id ");

        $showresult = $this->makePartial('show_close_popup');

        $nots = ertGrade::getSelectedNotifications($workuser_id,$input_id);
        if (count($nots) > 0) {

            $input = Input::find($input_id);

            // config (reuse) widget
            $config = $this->makeConfig('$/reportertool/eokm/models/notification/fields_whois.yaml');
            $config->model = $input;
            $this->inputWidget = $this->makeWidget('Backend\Widgets\Form', $config);

            $showresult = $this->makePartial('show_set_whois',[
                'input_id' => $input_id,
                'workuser_id' => $workuser_id,
                'inputWidget' => $this->inputWidget,
            ]);

        } else {
            Flash::error('No one SELECTED');
        }

        return $showresult;
    }

    public function onSelectedWhoisSave() {

        $input_id = input('input_id');
        $workuser_id = input('workuser_id');
        $host_abusecontact_id = input('host_abusecontact_id');
        $registrar_abusecontact_id = input('registrar_abusecontact_id');

        ertLog::logLine("D-onSelectedWhoisSave; input_id=$input_id, workuser_id=$workuser_id ");

        $showresult = '';
        $nots = ertGrade::getSelectedNotifications($workuser_id,$input_id);
        if (count($nots) > 0) {

            $fields = [];
            $hashes = [];

            ertLog::logLine("D-onSelectedWhoisSave; SET ON host_abusecontact_id=$host_abusecontact_id, registrar_abusecontact_id=$registrar_abusecontact_id");

            foreach ($nots as $not_input) {

                // set new hoster/registrar

                $not = Notification::find($not_input->notification_id);
                $not->host_abusecontact_id = $host_abusecontact_id;
                $not->registrar_abusecontact_id = $registrar_abusecontact_id;
                $not->save();

                if (count($fields)==0) {
                    // eenmalig aanmaken
                    $whoisfields = Abusecontact::getWhois($not);
                    foreach ($whoisfields as $fld => $val) {
                        if ($fld!=ERT_HOSTER.'_rawtext' && $fld!=ERT_REGISTRAR.'_rawtext') {
                            $field = [
                                'name' => $fld,
                                'value' => ($val ? $val : ERT_NOT_SET),
                            ];
                            $fields[] = $field;
                        }
                    }
                    $field = [
                        'name' => 'whoisraw',
                        'value' => ertWhois::jsOutputRaw($whoisfields[ERT_HOSTER.'_rawtext'] . "\n\n" . $whoisfields[ERT_REGISTRAR.'_rawtext']),
                    ];
                    $fields[] = $field;
                }

                $hashes[] = $not->url_hash;

            }

            // each notification (image)
            $showresult .= $this->makePartial('js_update_fields',
                ['hashes' => $hashes,
                 'fields' => $fields
                ]);

            Flash::info('Selected filled with hoster/registrar');

        } else {
            Flash::error('No one SELECTED');
        }

        return ['show_result' => $showresult];
    }

    /**
     * SELECTED ILLEGAL button
     *
     * @return mixed
     */
    public function onSelectedImageIllegal() {

        $workuser_id = input('workuser_id');
        $input_id = input('input_id');
        ertLog::logLine("D-onSelectedImageIllegal; input_id=$input_id ");

        $nots = ertGrade::getSelectedNotifications($workuser_id,$input_id);
        if (count($nots) > 0) {
            $inp = Input::find($input_id);
            $show_questions = $this->show_grade_questions(ERT_GRADE_QUESTION_GROUP_ILLEGAL,$workuser_id,false,$inp);
        } else {
            Flash::error('No one SELECTED');
            $show_questions = $this->makePartial('show_close_popup');
        }

        return $show_questions;
    }

    /**
     * SELECTED NOT ILLEGAL button
     *
     * @return mixed
     */
    public function onSelectedImageNotIllegal() {

        $workuser_id = input('workuser_id');
        $input_id = input('input_id');
        ertLog::logLine("D-onSelectedImageIllegal; input_id=$input_id ");

        $nots = ertGrade::getSelectedNotifications($workuser_id,$input_id);
        if (count($nots) > 0) {
            $inp = Input::find($input_id);
            $show_questions = $this->show_grade_questions(ERT_GRADE_QUESTION_GROUP_NOT_ILLEGAL,$workuser_id,false,$inp);
        } else {
            Flash::error('No one SELECTED');
            $show_questions = $this->makePartial('show_close_popup');
        }

        return $show_questions;
    }

    /**
     * SELECTED IGNORE
     *
     * @return array
     */
    public function onSelectedImageIgnore() {

        $workuser_id = input('workuser_id');
        $input_id = input('input_id');
        ertLog::logLine("D-onSelectedImageIgnore; input_id=$input_id, workuser_id=$workuser_id ");

        $showresult = '';
        $showignore = $this->getIgnoreShowHide();
        $notifications = ertGrade::getSelectedNotifications($workuser_id,$input_id);

        if (count($notifications) > 0) {

            $setbuts = [];

            $cntdone = 0;
            foreach ($notifications AS $notification) {

                $notification = Notification::find($notification->notification_id);

                if ($showignore || $notification->grade_code!=ERT_GRADE_IGNORE) {

                    // set select off
                    ertGrade::setGradeSelected($workuser_id, $notification->id, false);

                    // zet status on ERT_GRADE_IGNORE
                    $notification->status_code = ERT_STATUS_CLOSE;
                    $notification->grade_code = ERT_GRADE_IGNORE;
                    $notification->logText("Set status_code on: " . $notification->status_code);
                    $notification->save();

                    $setbutton = $this->setButtons($notification,$workuser_id,false);
                    $setbutton['hash'] = $notification->url_hash;
                    $setbuts[] = $setbutton;

                    $cntdone += 1;
                }

            }

            $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts]);
            Flash::info("Notifications ($cntdone) set on ignore");

        } else {

            Flash::error('No one SELECTED');

        }

        return ['show_result' => $showresult ];
    }

    /**
     * SELECTED FIRST POLICE
     *
     * @return array
     */
    public function onSelectedPolice() {

        $workuser_id = input('workuser_id');
        $input_id = input('input_id');
        ertLog::logLine("D-onSelectedPolice; input_id=$input_id, workuser_id=$workuser_id ");

        $showresult = '';
        $notifications = ertGrade::getSelectedNotifications($workuser_id,$input_id);

        if (count($notifications) > 0) {

            $setbuts = [];

            $cntdone = 0;
            foreach ($notifications AS $notification) {

                $notification = Notification::find($notification->notification_id);

                if ($notification->grade_code == ERT_GRADE_ILLEGAL) {

                    // set select off
                    ertGrade::setGradeSelected($workuser_id, $notification->id, false);

                    // zet status on ERT_GRADE_IGNORE
                    $notification->status_code = ERT_STATUS_FIRST_POLICE;
                    $notification->online_counter = 1;  // force NOT 0
                    $notification->logText("Set status_code on: " . $notification->status_code);
                    $notification->save();

                    $setbutton = $this->setButtons($notification,$workuser_id,false);
                    $setbutton['hash'] = $notification->url_hash;
                    $setbuts[] = $setbutton;

                    $cntdone += 1;
                }

            }

            if ($cntdone> 0) {
                $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts]);
                Flash::info("$cntdone ILLEGAL marked notifications (images) set on FIRST POLICE");
            } else {
                Flash::warning("No ILLEGAL marked notifications (images) selected");
            }

        } else {

            Flash::error('No one SELECTED');

        }

        return ['show_result' => $showresult ];
    }

    /** SUB FUNCTIONS **/

    /**
     * Set button according status
     *
     * @param $notification
     * @return array
     */
    public function setButtons($notification,$workuser_id,$select='') {

        $class = '';
        $buttonsets = [];

        // selected
        if ($select=='') $select = ertGrade::getGradeSelected($workuser_id, $notification->id);

        // default set
        $buttonsets = [
            'SELECT' => ($select) ? 'true' : 'false',
            'YES' => 'false',
            'IGNORE' => 'false',
            'NO' => 'false',
        ];

        // specific set
        if ($notification->grade_code == ERT_GRADE_ILLEGAL) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'true',
                'IGNORE' => 'false',
                'NO' => 'false',
            ];
            $class = 'grade_button_illegal';

        } elseif ($notification->grade_code == ERT_GRADE_IGNORE) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'false',
                'IGNORE' => 'true',
                'NO' => 'false',
            ];
            $class = 'grade_button_ignore';

        } elseif ($notification->grade_code == ERT_GRADE_NOT_ILLEGAL) {

            $buttonsets = [
                'SELECT' =>($select) ? 'true' : 'false',
                'YES' => 'false',
                'IGNORE' => 'false',
                'NO' => 'true',
            ];
            $class = 'grade_button_notillegal';

        }

        $buttonsets['POLICE'] = ( ($notification->status_code==ERT_STATUS_FIRST_POLICE) ? 'true' : 'false');

        return [
            'class' => $class,
            'buttonsets' => $buttonsets,
        ];
    }


    /**
     * Get img size (html) string based on size (db) from record
     *
     * @param $notification
     * @param int $imgsize
     * @return string
     */
    function getImageSizeAttr($record,$imgsize=250,$resizebig=false) {

        // 2px padding
        $imgreal = $imgsize - 4; $size = '';

        // check width and height and place in ratio
        if ($record->url_image_width > $imgsize && $record->url_image_height < $imgsize) {
            $size = 'width="'.$imgreal.'" ';
        } elseif ($record->url_image_width < $imgsize && $record->url_image_height > $imgsize) {
            $size = 'height="'.$imgreal.'" ';
        } elseif ($record->url_image_width > $imgsize && $record->url_image_height > $imgsize) {
            $size = 'height="'.$imgreal.'" width="'.$imgreal.'" ';
        } else {
            if ($resizebig) {
                // resize (zoom) on bigest dimension
                if ( $record->url_image_width > $record->url_image_height) {
                    $size = 'width="'.$imgreal.'" ';
                } else {
                    $size = 'height="'.$imgreal.'" ';
                }
            }
        }

        return $size;
    }

    /**
     * Setup screen with questions
     *
     * single=true
     * - record=notification; show image
     *
     * single=false
     * - record=input; show no image;
     *
     * @param $questiongroup
     * @param $workuser_id
     * @param $single
     * @param $rec
     * @return mixed
     */
    public function show_grade_questions($questiongroup,$workuser_id,$single,$rec) {

        if ($single) {
            $imgsize = $this->getImageSizeAttr($rec,250);
        }

        $answer_record_id = '';
        if (!$single) {
            // get first record from notification selected
            $nots = ertGrade::getSelectedNotifications($workuser_id,$rec->id);
            if (count($nots) > 0) $answer_record_id = $nots[0]->notification_id;
        } else {
            $answer_record_id = $rec->id;
        }
        if ($single) {
            $recordtype = strtolower(class_basename($rec));
        } else {
            // multiply records
            $recordtype = ERT_NOTIFICATION_TYPE;
        }
        ertLog::logLine("D-show_grade_questions; answer_record_id=$answer_record_id, single=$single, recordtype=$recordtype");

        $gradeitems = ($recordtype==ERT_NOTIFICATION_TYPE) ? 'IMAGE' : 'INPUT';
        $gradeitems .= (!$single) ? 'S' : '';

        if ($answer_record_id) {

            $questions = [];
            $grades  = Grade_question::where('questiongroup',$questiongroup)->orderBy('sortnr')->get();

            $toggle = true;
            foreach ($grades AS $grade) {

                $value = Grade_answer::where('record_type',$recordtype)->where('record_id',$answer_record_id)->where('grade_question_id',$grade->id)->first();
                $values = ($value) ? unserialize($value->answer) : '';
                if ($values=='') $values = array();
                //ertLog::logLine("D-show_grade_questions; question=$grade->name, type=$grade->type, values=" . implode(',', $values));

                $question = new \stdClass();
                $question->type = $grade->type;
                $question->label = $grade->label;
                $question->name = $grade->name;
                $question->leftright = $grade->span;
                //$question->leftright = ($toggle) ? 'left' : 'right';
                $toggle = !$toggle;

                if ($question->type == 'select' || $question->type == 'checkbox' || $question->type == 'radio') {

                    $options = [];
                    $opts = Grade_question_option::where('grade_question_id',$grade->id)->orderBy('sortnr')->get();
                    foreach ($opts AS $opt) {
                        $option = new \stdClass();
                        $option->sortnr = $opt->sortnr;
                        $option->value = $opt->value;
                        $option->label = $opt->label;
                        $option->selected =  (in_array($option->value,$values) ? 'selected' : '');
                        $options[] = $option;
                    }
                    $question->options = $options;

                } else {

                    $question->value = $values;

                }

                $questions[] = $question;
            }

            // if single and url set then show image
            $src = ($single) ? ertBrowser::getImageBase64($rec->url,$rec->url_hash) : '';

            $params = [
                'gradeitems' => $gradeitems,
                'gradeheader' => ( ($questiongroup==ERT_GRADE_QUESTION_GROUP_ILLEGAL) ? 'ILLEGAL' : 'NOT ILLEGAL'),
                'single' => $single,
                'input_id' => $rec->id,
                'record_id' => $rec->id,
                'recordtype' => $recordtype,
                'workuser_id' => $workuser_id,
                'questiongroup' => $questiongroup,
                'src' => ( ($recordtype==ERT_NOTIFICATION_TYPE) ? $src : ''),
                'imgsize' => (($single) ? $imgsize : ''),
                'questions' => $questions,
            ];

            $show_questions = $this->makePartial('show_grade_questions',$params);

        } else {

            ertLog::logLine("D-show_grade_questions; NO NOTIFICATION (MORE) SELECTED!?");
            $show_questions = false;
        }

        return $show_questions;
    }

    /**
     * Main function receiving SAVE question (answers)
     *
     * single=true
     * - set answer for notification or input
     *
     * single=false
     * - set answer for selected notifications
     *
     * @return array
     */
    public function onQuestionsSave() {

        $single = input('single');

        $workuser_id = input('workuser_id');
        $record_id = input('record_id');
        $questiongroup = input('questiongroup');
        if ($single) {
            $recordtype = input('recordtype');
            if ($recordtype=='') $recordtype = 'notification';
        } else {
            // always notification type
            $recordtype = ERT_NOTIFICATION_TYPE;
        }
        ertLog::logLine("D-onQuestionsSave; single=$single, record_id=$record_id, recordtype=$recordtype ");

        // set buttons
        $setbuts = []; $showresult = '';

        $showignore = $this->getIgnoreShowHide();

        // questions
        $grades  = Grade_question::where('questiongroup',$questiongroup)->orderBy('sortnr')->get();

        if ($recordtype=='notification') {

            if ($single) {
                $rec = new \stdClass();
                $rec->notification_id = $record_id;
                $recs = array($rec);
            } else {
                $recs = ertGrade::getSelectedNotifications($workuser_id,$record_id);
            }

            foreach ($recs AS $rec) {

                // set select off
                ertGrade::setGradeSelected($workuser_id, $rec->notification_id, false);

                $notification = Notification::find($rec->notification_id);

                if ($showignore || $notification->grade_code != ERT_GRADE_IGNORE) {

                    foreach ($grades AS $grade) {
                        $inp = input($grade->name, '');
                        $ans = Grade_answer::where('record_type',$recordtype)
                            ->where('record_id', $rec->notification_id)
                            ->where('grade_question_id', $grade->id)
                            ->first();
                        if ($ans == '') {
                            $ans = new Grade_answer();
                            $ans->record_type = $recordtype;
                            $ans->record_id = $rec->notification_id;
                            $ans->grade_question_id = $grade->id;
                        }
                        // serialize -> multiselect values also
                        $ans->answer = serialize($inp);
                        $ans->save();
                    }

                    if ($questiongroup == ERT_GRADE_QUESTION_GROUP_ILLEGAL) {
                        $notification->grade_code = ERT_GRADE_ILLEGAL;
                        $notification->firstseen_at = date('Y-m-d H:i:s');
                        $notification->online_counter = 0;
                    } else {
                        // $questiongroup==ERT_GRADE_QUESTION_GROUP_NOT_ILLEGAL
                        $notification->status_code = ERT_STATUS_CLOSE;
                        $notification->grade_code = ERT_GRADE_NOT_ILLEGAL;
                        $notification->firstseen_at = date('Y-m-d H:i:s');
                        $notification->online_counter = 1;
                    }
                    $notification->logText("Set status_code on: " . $notification->status_code . ", grade_code=" . $notification->grade_code);
                    $notification->save();

                    $setbutton = $this->setButtons($notification, $workuser_id, false);
                    $setbutton['hash'] = $notification->url_hash;
                    $setbuts[] = $setbutton;

                    if (!$single) {
                        Flash::info('Notifications classified');
                    }

                }

            }

            $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts]);

        } elseif ($recordtype=='input') {

            // always single

            $input = Input::find($record_id);

            foreach ($grades AS $grade) {
                $inp = input($grade->name, '');
                $ans = Grade_answer::where('record_type',$recordtype)
                    ->where('record_id', $record_id)
                    ->where('grade_question_id', $grade->id)
                    ->first();
                if ($ans == '') {
                    $ans = new Grade_answer();
                    $ans->record_type = $recordtype;
                    $ans->record_id = $record_id;
                    $ans->grade_question_id = $grade->id;
                }
                // serialize -> multiselect values also
                $ans->answer = serialize($inp);
                $ans->save();
            }

            if ($questiongroup == ERT_GRADE_QUESTION_GROUP_ILLEGAL) {
                $input->grade_code = ERT_GRADE_ILLEGAL;
            } else {
                // $questiongroup==ERT_GRADE_QUESTION_GROUP_NOT_ILLEGAL
                $input->grade_code = ERT_GRADE_NOT_ILLEGAL;
            }
            $input->firstseen_at = date('Y-m-d H:i:s');
            $input->online_counter = 0;
            $input->logText("Set status_code on: " . $input->status_code . ", grade_code=" . $input->grade_code);
            $input->save();

            //$setbutton = $this->setButtons($notification, $workuser_id, false);
            //$setbutton['hash'] = $notification->url_hash;
            //$setbuts[] = $setbutton;

            //Flash::info('Input classified');

            $detailfields = [
                'input_grade_code' => $input->grade_code,
            ];
            $showresult = $this->makePartial('js_update_detail', ['detailfields' => $detailfields]);

        }

        return ['show_result' => $showresult ];
    }

    /** BUTTONS **/

    public function onIgnoreShowHide() {

        $input_id = input('input_id');
        $workuser_id = input('workuser_id');
        //$show = input('show');
        //if ($show=='') $show = '1';
        // toggle
        $show = $this->getIgnoreShowHide();
        $show = ($show=='1') ? '0' : '1';
        $this->setIgnoreShowHide($show);
        ertLog::logLine("D-onIgnoreShowHide; show=$show");

        return $this->nextCall($input_id,$workuser_id,0,0,'',true);
    }

    public function onColumnSet() {

        $input_id = input('input_id');
        $workuser_id = input('workuser_id');
        $columnsize = input('columnsize');
        if ($columnsize=='') $columnsize = '3';
        $this->setColumnsize($columnsize);
        ertLog::logLine("D-onColumnSet; columnsize=$columnsize");
        return $this->nextCall($input_id,$workuser_id,0,$columnsize,'',true);
    }

    public function onSelect() {

        $workuser_id = input('workuser_id');
        $input_id = input('input_id');
        $select = input('select');
        $showignore = $this->getIgnoreShowHide();

        ertLog::logLine("D-onSelect; workuser_id=$workuser_id, input_id=$input_id, select=$select, showignore=$showignore ");

        $showresult = '';
        $notifications = ertGrade::getNotifications($input_id);
        $cnt = count($notifications);

        //ertLog::logLine("D-onSelect; stap=1 ");

        if ($cnt > 0) {

            $setbuts = [];

            $cntdone = 0;
            foreach ($notifications AS $notification) {

                if ($showignore || $notification->grade_code!=ERT_GRADE_IGNORE) {

                    switch ($select) {
                        case 'all':
                            $sel = true;
                            break;

                        case 'none':
                            $sel = false;
                            break;

                        case 'invers':
                            $sel = !ertGrade::getGradeSelected($workuser_id, $notification->id);
                            break;

                        case 'unset':
                            $sel = ($notification->grade_code==ERT_GRADE_UNSET);
                            break;
                    }

                    ertGrade::setGradeSelected($workuser_id, $notification->id, $sel);

                    $setbutton = $this->setButtons($notification,$workuser_id,$sel);
                    $setbutton['hash'] = $notification->url_hash;
                    $setbuts[] = $setbutton;

                    $cntdone += 1;

                }

            }

            //ertLog::logLine("D-onSelect; stap=2 ");

            switch ($select) {
                case 'all':
                    $seltxt = 'All selected';
                    break;

                case 'none':
                    $seltxt = 'None selected';
                    break;

                case 'invers':
                    $seltxt = 'Inverted selection';
                    break;

                case 'unset':
                    $seltxt = 'selected not classified';
                    break;

            }

            $showresult = $this->makePartial('js_buttonsresult', ['setbuts' => $setbuts]);

            Flash::info("$seltxt ($cntdone)");

        } else {

            ertLog::logLine("D-onSelectALL; workuser_id=$workuser_id, input_id=$input_id, NO NOTIFICATIONS FOUND!?");
            Flash::error('No notifications?');

        }

        //ertLog::logLine("D-onSelect; done ");

        return ['show_result' => $showresult];
    }

    // on ImageSelect
    public function onImageSelect () {

        $workuser_id = input('workuser_id');
        $notification_id = input('notification_id');
        $hash = input('hash');
        $toggle = false;

        if ($workuser_id && $notification_id) {

            $toggle = !ertGrade::getGradeSelected($workuser_id, $notification_id);
            ertGrade::setGradeSelected($workuser_id, $notification_id, $toggle);
            ertLog::logLine("D-onImageSelect; workuser_id=" . $workuser_id . ", notification_id=" . $notification_id . ", toggle=$toggle");

            $notification = Notification::find($notification_id);
            $setbuttons = $this->setButtons($notification,$workuser_id);
            $showresult = $this->makePartial('js_buttonresult',
                ['hash' => $notification->url_hash,
                    'buttonsets' => $setbuttons['buttonsets'],
                    'class' => $setbuttons['class']
                ]
            );
            //Flash::info('Image ' . (($toggle) ? 'selected' : 'deselected') );

        } else {
            Flash::error('Unknown workuser/notification!?');
            $showresult = false;
        }

        return ['show_result' => $showresult ];
    }

    /**
     * onNext
     *
     * Next record; negative number then down, postive then up
     *
     * @return screen
     */
    public function onNext() {

        $input_id = input('input_id');
        $workuser_id = input('workuser_id');
        $screensize = input('screensize');
        $columnsize = input('columnsize');
        ertLog::logLine("D-onNext; input_id=$input_id , workuser_id=$workuser_id, screensize=$screensize, columnsize=$columnsize");

        return $this->nextCall($input_id,$workuser_id,$screensize,$columnsize);
    }


}
