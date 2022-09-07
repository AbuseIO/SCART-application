<?php
namespace abuseio\scart\widgets;

use abuseio\scart\classes\helpers\scartLog;
use Backend\Classes\WidgetBase;
use Illuminate\Support\Facades\Session;
use Input;


class Finder extends WidgetBase
{

    protected $defaultAlias = 'finder';
    private $starttime;

    /**
     * Tiles constructor.
     * @param $controller
     */
    public function __construct($controller)
    {
        parent::__construct($controller);
    }


    /**
     * @description searching with criteria for records.
     *
     * @return array|bool
     */
    public function onSearch() {
        $this->starttime = microtime(true);
        $filters = Input::all();
        \Session::forget('filter');
        if (is_array($filters) && count($filters) > 0) {
            scartLog::logLine("D-Finder(widget): begin process finder, with the term: " . $filters['criteria']);
            \Session::push('filter', $filters);
            // https://octobercms.com/forum/post/connecting-2-widgets-in-a-modal-popup

            $results = [];
            if (!isset($filters['typeTable']) || $filters['typeTable'] == 'input') {
                $results['input'] = $this->makeList(['name' => 'input', 'alias' => 'scartFinderInputListWidget', 'filters' => $filters], '', true);
                scartLog::logLine("D-Finder(widget): Execution time of loading the input-data is  ". (microtime(true) - $this->starttime));
            }

            if (!isset($filters['typeTable']) || $filters['typeTable'] == 'ntd') {
                $results['ntd'] = $this->makeList(['name' => 'ntd', 'alias' => 'scartFinderNtdListWidget', 'filters' => $filters], '', true);
                scartLog::logLine("D-Finder(widget): Execution time of loading the ntd-data is  ". (microtime(true) - $this->starttime));
            }

            if (!isset($filters['typeTable']) || $filters['typeTable'] == 'domainrule') {
                $results['domainrule'] = $this->makeList(['name' => 'domainrule', 'alias' => 'scartFinderDomainruleListWidget', 'filters' => $filters], '', true);
                scartLog::logLine("D-Finder(widget): Execution time of loading the domainrule-data is  ". (microtime(true) - $this->starttime));
            }


            scartLog::logLine("D-Finder(widget): Execution time of loading the data is  ". (microtime(true) - $this->starttime));

            return $results;
        } else {
            scartLog::logLine("D-Finder(widget): No search criteria is given, stop finder process");
        }

       return false;
    }

    /**
     * Renders the widget in the nav.
     */
    public function render()
    {
        return $this->makePartial('finder');
    }

    /**
     * @param $data
     * @param string $page
     * @param bool $return
     * @return \Backend\Widgets\Form
     */
    public function makeForm($data, $page = 'inputscreen/', $return = false) {
        $config = $this->makeConfig("$/abuseio/scart/widgets/finder/config/fields/{$page}{$data['name']}_fields_view.yaml");

        $prefix = '\abuseio\scart\models';
        $class = (isset($data['model'])) ? $prefix.$data['model'] : $prefix.'\\'.ucfirst($data['name']);
        $config->model = new $class();

        if (isset($data['id'])) {
            if(isset($data['key'])) {
                $config->model = $config->model->where($data['key'], $data['id']);

                // join functiontaliteit
                if(isset($data['query']['join'])) {
                    scartLog::logLine("D-Finder(widget): load form, searching in DB for record, a Join is given. ");
                    extract($data['query']['join']);
                    $config->model->join($table, $key, '=', $otherKey);
                }

                if(isset($data['query']['where'])) {
                    scartLog::logLine("D-Finder(widget): load form, searching in DB for record, where statements given. ");
                    foreach ($data['query']['where'] as $key => $query) {
                        if ($query['operator'] == 'IN') {
                            $config->model->whereIn($query['column'], $query['value']);
                        } else {
                            $config->model->where($query['column'], $query['operator'], $query['value']);
                        }
                    }
                }

               if (isset($data['latest']) && $data['query']) {
                   $config->model->latest($data['latest']);
               }

                if (isset($data['oldest']) && $data['query']) {
                    $config->model->orderBy($data['oldest'], 'ASC');
                }



               if ($config->model->exists()) {
                   $config->model = $config->model->first();
               } else {
                   $config->model = new $class();
               }

            }
        } else {
            scartLog::logLine("D-Finder(widget): load form, no id is given, load without value ");
        }

        $widget = new \Backend\Widgets\Form($this->getController(), $config);

        if (isset($data['alias'])) {
            scartLog::logLine("D-Finder(widget): load form, with alias: {$data['alias']} ");
            $widget->alias = $data['alias'];
        }

        if($return) {
            return $widget;
        }

    }

    /**
     * @param $data
     */
    public function makeList($data, $dir = '', $return = false, $setup = true, $filter = false)
    {

        $config = $this->makeConfig("$/abuseio/scart/widgets/finder/config/list/{$dir}{$data['name']}_columns_view.yaml");
        $config->showSorting = true;
        $config->showSearching = false;
        $config->showSetup = false;
        $config->showPageNumbers = true;
        $config->recordsPerPage = 10;
        $config->showCheckboxes = false;

        $config->recordUrl = (isset($data['redirect'])) ? $data['redirect'] : "abuseio/scart/finder/showResults/{$data['name']}/:id";

        $config->customViewPath = '$/abuseio/scart/widgets/finder/partials/list';

        if ($setup) {
            $config->showSearching = true;
            $config->showSetup = true;
            $config->showCheckboxes = true;
        }

        $prefix = '\abuseio\scart\models';
        if (isset($data['model'])) {
            $class = $prefix.$data['model'];
        } else {
            $class = $prefix.'\\'.ucfirst($data['name']);
        }


        $config->model = new $class();
        $widget = new \Backend\Widgets\Lists($this->getController(), $config);

        if (isset($data['alias'])) {
            //scartLog::logLine("D-Finder(widget): load list, with alias: {$data['alias']} ");
            $widget->alias = $data['alias'];
        }

        // query
        if (isset($data['filters']) && $data['filters']) {
            scartLog::logLine("D-Finder(widget): load list, search filter is given. ");
            $filters = $data['filters'];
            $widget->bindEvent('list.extendQueryBefore', function ($query) use ($filters, $data) {


                if($data['name'] == 'input' || ($data['name'] == 'ntd' && $filters['type'] == 'filenumber')) {
                    return $query->where($filters['type'], 'like', '%' . $filters['criteria'] . '%');
                } elseif($data['name'] == 'domainrule') {
                    return $query->where('domain', 'like', '%' . $filters['criteria'] . '%');
                } else
                {
                    return $query->join('abuseio_scart_ntd_url', 'abuseio_scart_ntd.id', '=', 'abuseio_scart_ntd_url.ntd_id')
                        ->where('abuseio_scart_ntd_url.url', 'like', '%' . $filters['criteria'] .'%');
                }
            });
        }

        if ($filter) {


            scartLog::logLine("D-Finder(widget): load list, future project:  Filters are loading. ");
            // make filter
            $filterConfig = $this->makeConfig("$/abuseio/scart/widgets/finder/config/input/config_filter.yaml");
            $filterConfig->scopes['url']['default'] = Session::pull('inputfield', '');
            $filterWidget = $this->makeWidget('Backend\Widgets\Filter', $filterConfig);
            $filterWidget->bindToController();

            // events
//            $listwidget = $this->inputlistinputwidget;
//            $filterWidget->bindEvent('filter.update', function () use ($listwidget, $filterWidget) {
//                return $listwidget->onRefresh();
//            });

            $this->$key->addFilter([$filterWidget, 'applyAllScopesToQuery']);
            $this->filterWidget = $filterWidget;
        }

        if($return) {
            return $widget;
        }

    }

    /**
     * @Description  Show finders result
     * @return mixed
     */
    public function showResults()
    {
        return $this->makePartial('table/foundresults');
    }

    /**
     * @description View the search form when clicking on the finder
     * @return mixed
     */
    public function LoadFinderForm()
    {
        $this->vars['finderconfig'] = $this->makeConfig(plugins_path('abuseio/scart/controllers/finder/config/config.yaml'));
        return $this->makePartial('form/search');
    }

    /**
     * @description manualy change (formwidget)fields
     * @param $model
     * @param $fields
     * @return mixed
     */
    public function changeFields($model, $fields)
    {

        scartLog::logLine("D-Finder(widget): Change existing fields, custom action is given:  more flexibility ");
        if (count($fields) > 0 && is_object($model) && method_exists($model, 'addFields')) {
            $rawAttributes = [];
            foreach ($fields as $key => $field) {
                if (isset($field['value'])) {
                    $rawAttributes[$key] = $field['value'];
                }
            }
            $model->model->setRawAttributes($rawAttributes, true);
            $model->addFields($fields);
        } else {
            scartLog::logLine("D-Finder(widget): Try to change the existing fields, but no fields are given or the function addField dont exists ");
        }

        return $model;
    }

}
