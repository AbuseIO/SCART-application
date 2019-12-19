<?php namespace ReporterTool\EOKM\Controllers;

use Flash;
use BackendMenu;
use ReporterTool\EOKM\Models\Abusecontact;
use reportertool\eokm\classes\ertController;
use ReporterTool\EOKM\Models\Input;
use ReporterTool\EOKM\Models\Notification;
use ReporterTool\EOKM\Models\Whois;
use ReporterTool\EOKM\Models\Ntd;

class Abusecontacts extends ertController
{
    public $implement = [
        'Backend\Behaviors\ListController',
        'Backend\Behaviors\RelationController',
        'Backend\Behaviors\FormController'
    ];
    
    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct() {
        parent::__construct();
        BackendMenu::setContext('ReporterTool.EOKM', '', 'Abusecontacts');
    }

    public function onMerge() {

        $checked = input('checked');

        if (count($checked) < 2) {
            Flash::warning('You have to check 2 or more to merge');
        } else {

            $first = '';
            foreach ($checked AS $id) {

                if ($first=='') {
                    $first = Abusecontact::find($id);
                } else {

                    $add = Abusecontact::find($id);
                    $alias1 = is_array($first->aliases) ? $first->aliases : [];
                    $alias1[] = $add->owner;
                    $alias2 = is_array($add->aliases) ? $add->aliases : [];
                    $amerge = array_unique(array_merge($alias1,$alias2));
                    $first->aliases = $amerge;

                    $domain1 = is_array($first->domains) ? $first->domains : [];
                    $domain2 = is_array($add->domains) ? $add->domains : [];
                    $dmerge = array_unique(array_merge($domain1,$domain2));
                    $first->domains = $dmerge;
                    $first->save();

                    // move Whois
                    Whois::where('abusecontact_id',$add->id)->update(['abusecontact_id' => $first->id]);

                    // move input and notification and ntd
                    Input::where('registrar_abusecontact_id',$add->id)->update(['registrar_abusecontact_id' => $first->id]);
                    Input::where('host_abusecontact_id',$add->id)->update(['host_abusecontact_id' => $first->id]);
                    Notification::where('registrar_abusecontact_id',$add->id)->update(['registrar_abusecontact_id' => $first->id]);
                    Notification::where('host_abusecontact_id',$add->id)->update(['host_abusecontact_id' => $first->id]);
                    Ntd::where('abusecontact_id',$add->id)->update(['abusecontact_id' => $first->id]);

                    $first->logText("Merge aliases/whois from $add->owner with $first->owner; remove $add->owner");

                    $add->delete();

                }

            }

            Flash::info('Checked abusecontacts merged');

            return $this->listRefresh();
        }

    }


}
