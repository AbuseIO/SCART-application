<?php namespace abuseio\scart\models;

use abuseio\scart\classes\base\scartModel;
use Backend\Models\UserGroup;
use Hash;
use BackendAuth;
use Validator;
use ValidationException;
use abuseio\scart\classes\helpers\scartUsers;

/**
 * Model
 */
class User extends scartModel {

    public $requiredPermissions = ['abuseio.scart.user_write'];

    use \October\Rain\Database\Traits\Validation;

    protected $dates = ['deleted_at'];
    protected $jsonable = ['workschedule'];


    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_user';

    /**
     * @var array Validation rules
     */
    public $rules = [
        'be_first_name' => 'required',
        'be_email' => 'email',
        'be_password' => 'min:10',
        'be_role_id' => 'required',
    ];

    public $hasOne = [
        'be_user' => [
            'Backend\Models\User',
            'key' => 'id',
            'otherKey' => 'be_user_id',
        ],
        'be_role' => [
            'Backend\Models\UserRole',
            'key' => 'id',
            'otherKey' => 'be_role_id',
        ],
    ];

//    public $belongsToMany = [
//        'groups' => [
//            'Backend\Models\UserGroup',
//            'table' => 'backend_users_groups',
//            'key' => 'user_id',
//            'otherKey' => 'user_group_id',
//        ],
//
//    ];

    public function getBeRoleIdOptions($value,$formData) {
        $recs = scartUsers::getBackendRoles();
        $ret = array();
        foreach ($recs AS $rec) {
            $ret[$rec->id] = $rec->description;
        }
        return $ret;
    }

//    public function getGroupsOptions()
//    {
//        $result = [];
//        foreach (UserGroup::all() as $group) {
//            $result[$group->id] = [$group->name, $group->description];
//        }
//        return $result;
//    }


    public function afterFetch() {

        //trace_log('afterFetch: id='.$this->be_user_id);
        if (!empty($this->be_user_id)) {
            $be_user = scartUsers::getBackendUser($this->be_user_id);
            $this->be_first_name = $be_user->first_name;
            $this->be_last_name = $be_user->last_name;
            $this->be_email = $be_user->email;
            $this->be_role_id = $be_user->role_id;
        }
    }

    public function beforeCreate() {

        // create
        //trace_log('beforeCreate: email=' . $this->be_email);

        if (scartUsers::getWorkuserId($this->be_email) == 0) {

            $user = BackendAuth::register([
                'first_name' => $this->be_first_name,
                'last_name' => $this->be_last_name,
                'login' => $this->be_email,
                'email' => $this->be_email,
                'password' => $this->be_password,
                'password_confirmation' => $this->be_password,
            ]);
            $this->be_user_id = $user->id;
            scartUsers::setBackendWorkuser($this->be_user_id);
            scartUsers::setBackendRole($this->be_user_id,$this->be_role_id);

            // no save in own table
            $this->be_password = '';

            // default workschedule; ma t/m vr; 08:00 - 18:00
            $this->workschedule = array_fill(0,5, [480,1080]);

        } else {

            $validator = Validator::make(
                [$this->be_email],
                [
                    'be_email' => 'email|unique',
                ],
                [
                    'unique' => 'The :attribute field must be unique',
                ]
            );

            throw new ValidationException($validator);

        }

    }

    function getMinutes($time) {
        $elms = explode(':', $time);
        if (count($elms) == 1) {
            $min = $elms[0] * 60;
        } elseif (count($elms) == 2) {
            $min = $elms[0] * 60 + $elms[1];
        } elseif (count($elms) == 3) {
            $min = $elms[0] * 60 + $elms[1] + ($elms[2]/60);
        } else {
            $min = 0;
        }
        return ($min);
    }

    public function beforeUpdate() {

        //trace_log('beforeUpdate');

        //trace_log(post());
        $days = post('day');
        $start = post('start');
        $end = post('end');

        if ($days && $start && $end) {

            $workschedule = [];
            // checkbox; only set when checked
            foreach ($days AS $key => $i) {
                $workschedule[$i - 1] = [0,24 * 60];
            }
            // fill if checked
            for ($i=0;$i<7;$i++) {
                if (isset($workschedule[$i])) {
                    if ($start[$i]) {
                        $workschedule[$i][0] = $this->getMinutes($start[$i]);
                    }
                    if ($end[$i]) {
                        $workschedule[$i][1] = $this->getMinutes($end[$i]);
                    }
                } else {
                    $workschedule[$i] = [];
                }
            }

            $this->workschedule = $workschedule;
            //trace_log($workschedule);

        }

        $updatedata = [
            'first_name' => $this->be_first_name,
            'last_name' => $this->be_last_name,
            'email' => $this->be_email,
            'role_id' => $this->be_role_id,
        ];
        if ($this->be_password!='') $updatedata['password'] = Hash::make($this->be_password);
        //trace_log($updatedata);

        scartUsers::updBackendUser($this->be_user_id, $updatedata);

        // no save in own table
        $this->be_password = '';

    }

    public function beforeDelete() {

        //trace_log('beforeDelete');

        // remove other references
        User_options::where('user_id',$this->id)->delete();
        // reset workuser
        Input::where('workuser_id',$this->be_user_id)->withTrashed()->update(['workuser_id' => 0]);

        // also delete backenduser record
        scartUsers::delBackendUser($this->be_user_id);

    }



}
