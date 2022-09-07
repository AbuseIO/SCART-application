<?php

namespace abuseio\scart\Controllers;

use abuseio\scart\classes\helpers\scartLog;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\models\Input_history;

use abuseio\scart\models\Input_parent;
use abuseio\scart\widgets\Timeline;
use Db;
use Flash;
use Config;
use Validator;
use ValidationException;
use BackendMenu;
use BackendAuth;
use function GuzzleHttp\Promise\all;
use abuseio\scart\classes\base\scartController;
use Input;

class Finder extends scartController
{
    public $requiredPermissions = [];
    public $implement = [];
    public $inputlistinputwidget;
    public $ntdlistinputwidget;
    public $loglistinputwidget;
    public $inputhistorylistinputwidget;
    public $logntdlistinputwidget;
    public $logmisclistinputwidget;
    public $imageslistinputwidget;
    public $urlslistinputwidget;
    public $logslistinputwidget;
    public $messagelistinputwidget;
    private $id;
    private $type;
    private $starttime;


    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'Finder';
    }


    /**
     * @param $type
     * @param $id
     * @return mixed
     */
     public function showResults($type, $id, $view = '/pagenotexists')
    {

        // starttime (online for develop)
        $this->starttime = microtime(true);

        $method = "set" . ucfirst($type) . 'Data';
        if (method_exists($this, $method)) {
            try {
                $this->id = $id;
                $this->type = $type;
                $bool = $this->$method();
                $view = ($bool) ? 'showresultslists/'.$type : $view;
            } catch (exception $e) {
                scartLog::logLine("E-Finder(controller): Error: ". $e->getMessage());
            }
        } else {
            scartLog::logLine("D-Finder(controller): No input found with the id: ". $this->id);
        }

        scartLog::logLine("D-Finder(controller): Execution time of loading the data is  ". (microtime(true) - $this->starttime));


        scartLog::logLine("D-Finder(controller): Load view:  ". $view);
        return $this->makePartial($view);

    }

    /**
     * @description Load NTD resultpage. This is made with Yaml-config and view
     */
    private function setNtdData()
    {
        // Maininfo
        $formntd = $this->widget->finder->makeForm(['name' => 'ntd', 'key' =>'id', 'id'=> $this->id, 'alias'=> 'formntd', 'model' => '\Ntd'], 'ntdscreen/',  true);
        if (!isset($formntd->model->id) || empty($formntd->model->id)) {
            scartLog::logLine("D-Finder(controller): No ntd-record found with id: ". $this->id);
            return false;
        }

        $formntd->bindToController();
        $this->vars['MainInfoNtd'] = $formntd->render();
        // tabs
        $this->InitFinderTabs('ntdscreen/');
        scartLog::logLine("D-Finder(controller): load page Finished: ". $this->type);

        return true;
    }

    /**
     * @description Load Domainrules resultpage. This is made with Yaml-config and view
     */
    private function setDomainruleData()
    {
        // maininfo
        $formdomainrule = $this->widget->finder->makeForm(['name' => 'domainrule', 'key' =>'id', 'id'=> $this->id, 'alias'=> 'forminput', 'model' => '\Domainrule'], 'domainrulescreen/',  true);
        // check if input exists
        if (!isset($formdomainrule->model->id) || empty($formdomainrule->model->id)) {
            scartLog::logLine("D-Finder(controller): No domainrule-record found with id: ". $this->id);
            return false;
        }

        $formdomainrule->bindToController();
        $this->vars['MainInfoDomainrules'] = $formdomainrule->render();
        // tabs
        $this->InitFinderTabs('domainrulescreen/');
        scartLog::logLine("D-Finder(controller): load page Finished: ". $this->type);
        return true;
    }

    /**
     * @description Load Input resultpage. This is made with Yaml-config and view
     */
    private function setInputData()
    {
        // main information

        // Input
        $forminput = $this->widget->finder->makeForm(['name' => 'input', 'key' =>'id', 'id'=> $this->id, 'alias'=> 'forminput', 'model' => '\Input'], 'inputscreen/',  true);
        $forminput->bindToController();

        // check if input exists
        if (!isset($forminput->model->id) || empty($forminput->model->id)) {
            scartLog::logLine("D-Finder(controller): No input-record found with id: ". $this->id);
            return false;
        }

        // set name of analist
        $nameAnalist = (isset($forminput->model->workuser)) ? $forminput->model->workuser->first_name . ' '. $forminput->model->workuser->last_name : '';
        // set url type
        $urlType = $forminput->model->url_type;
        // set id of host
        $host_abusecontact_id = $forminput->model->host_abusecontact_id;
        // set classificatie
        $grade_code = $forminput->model->grade_code;
        // set status_code
        $status_code = $forminput->model->status_code;

        // show gradecodes when mainurl
        $fields = [];
        if ($urlType == SCART_URL_TYPE_MAINURL) {
            $span = 'left';
            foreach ($forminput->model->getGradeCodesofInputGroup() as $key => $field) {
                $fields[$key] = ['label' => $key, 'type' => 'text', 'placeholder' => $field, 'span' => $span, 'readOnly' => true];
                $span = ($span == "left") ? "right" : "left";
            }
            $forminput = $this->widget->finder->changeFields($forminput, $fields);
        }
        // end Input

        // NTD
        $query = ['join' => ['table' => 'abuseio_scart_ntd', 'key' =>  'abuseio_scart_ntd_url.ntd_id', 'otherKey' => 'abuseio_scart_ntd.id'], 'where' => [
            ['column' => 'abuseio_scart_ntd.status_code', 'operator' => 'IN', 'value' => [SCART_SENT_FAILED, SCART_SENT_SUCCES, SCART_SENT_API_FAILED, SCART_SENT_API_SUCCES] ]]];
        $formNTD = $this->widget->finder->makeForm(['latest' => 'abuseio_scart_ntd_url.created_at', 'name' => 'ntdurl','id'=> $this->id, 'key' => 'abuseio_scart_ntd_url.record_id', 'alias'=> 'formNTD', 'model' => '\Ntd_url', 'query' => $query], 'inputscreen/', true);
        $formNTD->bindToController();
        //Add host to NTD fields
        $hoster = Abusecontact::find($host_abusecontact_id);
        if (!empty($hoster)) {
            $fieldsInputNTD['hoster'] = ['span' => 'right', 'label' => 'hoster', 'type' => 'text', 'readOnly' => true, 'value' =>  $hoster->owner];
            $formNTD = $this->widget->finder->changeFields($formNTD, $fieldsInputNTD);
        }
        // end NTD

        // checkonline                  abuseio_scart_ntd
        $query = ['join' => ['table' => 'abuseio_scart_input_history', 'key' =>  'abuseio_scart_input.id', 'otherKey' => 'abuseio_scart_input_history.input_id'],'where' => [['column' => 'abuseio_scart_input_history.new', 'operator' => 'IN', 'value' => [SCART_STATUS_SCHEDULER_CHECKONLINE, SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL] ]]];
        $formCheckOnline = $this->widget->finder->makeForm(['oldest' => 'abuseio_scart_input_history.id', 'name' => 'checkonline','id'=> $this->id, 'key' => 'abuseio_scart_input.id', 'alias'=> 'formCheckonline', 'model' => '\Input', 'query' => $query], 'inputscreen/', true);
        $formCheckOnline->bindToController();

        // Set inset and end date
        $fieldsCheckOnline['online_counter'] = ['span' => 'right', 'label' => 'online counter', 'type' => 'text', 'readOnly' => true, 'value' =>  $forminput->model->online_counter];

        $lastseen = Input_history::where([['input_id', $this->id]])->whereIn('old', [SCART_STATUS_SCHEDULER_CHECKONLINE, SCART_STATUS_SCHEDULER_CHECKONLINE_MANUAL])->max('created_at');
        if(!empty($lastseen)) {
            $fieldsCheckOnline['lastseen_at'] = ['span' => 'left', 'label' => 'last', 'type' => 'text', 'readOnly' => true, 'value' =>  $lastseen];
        }
        $formCheckOnline = $this->widget->finder->changeFields($formCheckOnline, $fieldsCheckOnline);
        // end checkonline

        // scrape
        if($urlType != SCART_URL_TYPE_MAINURL) {
            $inputParent = Input_parent::where('input_id',  $this->id)->first();
            $scrapeinputId = ($inputParent) ? $inputParent->parent_id : $this->id;
        } else {
            $scrapeinputId = $this->id;
        }
        $query = ['join' => ['table' => 'abuseio_scart_input_history', 'key' =>  'abuseio_scart_input.id', 'otherKey' => 'abuseio_scart_input_history.input_id'],'where' => [['column' => 'abuseio_scart_input_history.old', 'operator' => '=', 'value' => SCART_STATUS_SCHEDULER_SCRAPE ]]];
        $formScrape= $this->widget->finder->makeForm(['latest' => 'abuseio_scart_input_history.created_at', 'name' => 'scrape','id'=> $scrapeinputId, 'key' => 'abuseio_scart_input.id', 'alias'=> 'formScrape', 'model' => '\Input', 'query' => $query], 'inputscreen/', true);
        $formScrape->bindToController();
         // end scrape

        // classify
        $query = ['join' => ['table' => 'abuseio_scart_input_history', 'key' =>  'abuseio_scart_input.id', 'otherKey' => 'abuseio_scart_input_history.input_id'],'where' => [['column' => 'abuseio_scart_input_history.old', 'operator' => '=', 'value' => SCART_STATUS_GRADE ]]];
        $formClassify= $this->widget->finder->makeForm(['latest' => 'abuseio_scart_input_history.created_at', 'name' => 'classify','id'=> $this->id, 'key' => 'abuseio_scart_input.id', 'alias'=> 'formScrape', 'model' => '\Input', 'query' => $query],'inputscreen/', true);
        $formClassify->bindToController();
        // set analist name
        $fieldsClassify['Analist'] = ['span' => 'right', 'label' => 'Analist', 'type' => 'text', 'readOnly' => true, 'value' =>  $nameAnalist];

        $fieldsClassify['classification'] = ['span' => 'left', 'label' => 'classification', 'type' => 'text', 'readOnly' => true, 'value' =>  $grade_code];
        $fieldsClassify['status'] = ['span' => 'right', 'label' => 'status code', 'type' => 'text', 'readOnly' => true, 'value' =>  $status_code];

        $formClassify = $this->widget->finder->changeFields($formClassify, $fieldsClassify);
        // analist
        // end classify

        // pass the data to the view
        $this->vars['MainInfoClassify'] = $formClassify->render();
        $this->vars['MainInfoScrape'] = $formScrape->render();
        $this->vars['MainInfoCheckonline'] = $formCheckOnline->render();
        $this->vars['MainInfoNTD'] = $formNTD->render();
        $this->vars['MainInfoInput'] = $forminput->render();


        // Init tabs
        $this->InitFinderTabs('inputscreen/', ($urlType == SCART_URL_TYPE_IMAGEURL) ? ['imageslistinputwidget'] : []);
        scartLog::logLine("D-Finder(controller): load page Finished: ". $this->type);
        return true;
    }

    public function getTimeLineData(array $listdata) : array {

        //init return array
        $timelineReturndata = [];

        $array = [];

        // check if theres configuration
        if (count($listdata) > 0) {
            foreach ($listdata as $key => $config) {
                // call controller + method
                if (isset($config['model']) &&
                    class_exists($config['model']) &&
                    isset($config['method'])) {

                    $modelClass = new $config['model']();
                    $methodname = $config['method'];

                    // check if method exists
                    if (method_exists($modelClass, $config['method'])) {
                        $array = $modelClass->$methodname($this->id, $config['param'], $array);
                        // trans to format
                    } else {
                        scartLog::logLine("D-Finder(controller): cant find method: ". $config['method']);
                    }
                } else {
                    scartLog::logLine("D-Finder(controller): cant find controller: ". $config['model']);
                }
            } // end foreach
        } // end if : listdata count

        // build good index with the inner data on the correct positions, example
        /**
         * classify
         *      Item1 = illigal
         *      Item2  = illigal
         * classify
         */



        if (count($array) > 0) {
            foreach ($array as $key => $message) {
                if (strpos($message->type, 'Inner') !== false) {
                    $keybefore = $key-1;
                    [$array[$keybefore], $array[$key]] = [$array[$key], $array[$keybefore]];
                }
            }
        }



        // readconflig; get config types of data
//        $test = $this->finderConfig->pages[$this->type];






        return array_reverse($array);
    }



    /**
     * @param $typeScreen
     */
    private function InitFinderTabs($typeScreen, $exlude = [])
    {

        if (isset($this->finderConfig->pages[$this->type]) && count($this->finderConfig->pages[$this->type]) > 0) {
            foreach ($this->finderConfig->pages[$this->type] as $key => $list) {
                scartLog::logLine("D-Finder(controller): create tab {$key} for : ". $this->type);
                // check if there are type that must exclude
                if (in_array($key, $exlude)) continue;


                // timeline or default list
                if (isset($list['type']) && $list['type'] == 'timeline') {

                    // get data for the timeline
                    $TimelineData  = $this->getTimeLineData($list['workflow']);
                    // init timeline widget
                    $timeline = new Timeline($this);
                    $timeline->setData($TimelineData);
                    $renderedData = $timeline->render();
                } else {
                    // make list
                    $this->$key = $test =$this->widget->finder->makeList($list, $typeScreen, true, false);
                    // query on items
                    if (isset($list['query'])) {
                        scartLog::logLine("D-Finder(controller): queries tabinformation : ". $this->type);
                        $this->$key->bindEvent('list.extendQueryBefore', function ($query) use ($list)  {
                            if(isset($list['join'])) {
                                foreach ($list['join'] as $jn) {extract($jn);$query->join($table, $relation, $operator, $otherrelation);}
                            }
                            foreach ($list['query'] as $qry) {
                                if($qry['value'] == 'idvalue') {$qry['value'] = $this->id;}
                                if ($qry['operator'] == 'IN') {
                                    $query->whereIn($qry['column'], explode(",", $qry['value']));
                                } else {
                                    $query->where([$qry]);
                                }
                            }
                            return $query;
                        });
                    }
                    // bind list (tab) to controller
                    scartLog::logLine("D-Finder(controller): make alias with the name : ". $key);
                    $this->$key->alias = $key;
                    $this->$key->bindToController();
                    $renderedData  = $this->$key->render();
                }





                // send list widget to a view
                $this->vars['ScartFinderTab'][$list['name']] = $renderedData;
            }
        } else {
            // log no tabs available
            scartLog::logLine("D-Finder(controller): No Tabs available for the page: ". $this->type);
        }

    }



}
