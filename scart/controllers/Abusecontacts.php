<?php namespace abuseio\scart\Controllers;

use Flash;
use BackendMenu;
use abuseio\scart\models\Abusecontact;
use abuseio\scart\classes\base\scartController;
use abuseio\scart\models\Input;
use abuseio\scart\models\Whois;
use abuseio\scart\models\Ntd;
use abuseio\scart\models\Whois_cache;
use abuseio\scart\models\Domainrule;

class Abusecontacts extends scartController
{
    public $requiredPermissions = ['abuseio.scart.abusecontact_manage'];

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
        BackendMenu::setContext('abuseio.scart', 'Abusecontacts');
    }

    /**
     * @description  Delete abusecontact(s)
     * @return mixed
     */
    public function onDelete()
    {
        $checked = input('checked', false);
        $cnt = 0;
        $cntException = 0;
        if ($checked) {
            foreach ($checked AS $id) {

                $cntact = Abusecontact::find($id);
                if ($cntact) {
                    $cntid = $cntact->id;
                    // only delete the abuse contact when theres no data (input, ntd) present
                    try {
                        if ($cntact->delete()) {
                            $cnt++;
                            // disable the domainrules.
                            $rules = Domainrule::where('abusecontact_id', $cntid)->get();
                            if ($rules) {
                                foreach ($rules as $rule) {
                                    $rule->abusecontact_id = 0;
                                    $rule->enabled = 0;
                                    $rule->save();
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        $cntException++;
                    }
                }
            }  // end foreach
        }

        // show info popup
        if ( $cntException == 0 ) {
            Flash::success($cnt . ' abusecontacts are removed');
        } else {
            Flash::warning("{$cnt} abusecontact(s) are removed, {$cntException} abuscontact(s) are not removed");
        }

        // refresh the abusecontactlist
        return $this->listRefresh();
    }

    public function onMerge() {

        $checked = input('checked');

        if (count($checked) < 2) {
            Flash::warning('You have to check 2 or more to merge');
            $warning = true;
        } else {

            $first = ''; $warning = false;
            foreach ($checked AS $id) {

                if ($first=='') {
                    $first = Abusecontact::find($id);
                } else {

                    $add = Abusecontact::find($id);

                    if ($add) {

                        $alias1 = is_array($first->aliases) ? $first->aliases : [];
                        $alias1[] = $add->owner;
                        $alias2 = is_array($add->aliases) ? $add->aliases : [];
                        $amerge = array_unique(array_merge($alias1,$alias2));
                        $first->aliases = $amerge;
                        $first->save();

                        // log old/new for history
                        $oldies = Input::where('host_abusecontact_id',$add->id)->get();
                        foreach ($oldies AS $oldy) {
                            $oldy->logHistory(SCART_INPUT_HISTORY_HOSTER,$add->id,$first->id,"Merge abusecontacts");
                        }

                        // move Whois, input and ntd
                        Whois::where('abusecontact_id',$add->id)->update(['abusecontact_id' => $first->id]);
                        Input::where('registrar_abusecontact_id',$add->id)->update(['registrar_abusecontact_id' => $first->id]);
                        Input::where('host_abusecontact_id',$add->id)->update(['host_abusecontact_id' => $first->id]);
                        Ntd::where('abusecontact_id',$add->id)->update(['abusecontact_id' => $first->id]);

                        // clear whois cache
                        Whois_cache::where('abusecontact_id',$add->id)->delete();

                        $first->logText("Merge aliases/whois from $add->owner with $first->owner; remove $add->owner");

                        $add->delete();

                    } else {

                        Flash::warning('Checked abusecontact is deleted/removed!?');
                        $warning = true;
                        break;

                    }

                }

            }

            if (!$warning) {
                Flash::info('Checked abusecontacts merged');
            }

            return $this->listRefresh();
        }

    }


}
