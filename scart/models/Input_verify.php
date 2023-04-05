<?php namespace abuseio\scart\Models;

use abuseio\scart\classes\base\scartModel;
use abuseio\scart\classes\helpers\scartLog;
use Model;
use BackendAuth;
use Exception;

/**
 * Model
 */
class Input_verify extends scartModel
{
    use \October\Rain\Database\Traits\Validation;

    use \October\Rain\Database\Traits\SoftDelete;

    protected $dates = ['deleted_at'];

    public $belongsTo = [
        'input' => [
            'abuseio\scart\models\Input',
            'key' => 'input_id' // this
        ],
    ];

    public $hasMany = [
        'answers' => [
            'abuseio\scart\models\Grade_answer',
            'table' => 'abuseio_scart_grade_answer',
            'conditions' => "record_type='input_verify'",
            'key' => 'record_id', // relation model
            'otherKey' => 'id', // current model
            'delete' => false,
        ],
    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'abuseio_scart_input_verify';

    /**
     * @var array Validation rules
     */
    public $rules = [];

    /**
     * @description Only import items with the label Illegal
     * This the  the first step of the verification module.
     * @param $data
     */
    public static function import($data) {

        if ($data->grade_code == SCART_GRADE_ILLEGAL) {

            // check (always) if not already verified

            $count = Input_verify::where('input_id',$data->id)
                ->whereIn('status',[SCART_VERIFICATION_COMPLETE,SCART_VERIFICATION_FAILED])
                ->count();

            if ($count == 0) {

                scartLog::logLine('D-Input_verify(import): add new verify-item; filenumber='.$data->filenumber);

                // workuser_id holds last analist

                try{
                    // add original, for the list, so the analist can compare the records. This is een better option than extendlist (because filtering)
                    self::addItem($data->id, $data->workuser_id, SCART_VERIFICATION_ORIGINAL, SCART_GRADE_ILLEGAL);
                    // add item to verify
                    self::addItem($data->id, 0, SCART_VERIFICATION_VERIFY);

                } catch (Exception $err) {
                    scartLog::logLine("E-Input_verify(import); exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
                }

            } else {

                scartLog::logLine('W-Input_verify(import): already done the Multiply Verification for filenumber='.$data->filenumber);

            }

        } else {
            scartLog::logLine("D-Input_verify(model): new item ($data->filenumber) is NOT illegal - skip");
        }
    }

    /**
     * @description Insert a Verify item
     */
     public function insertVerify()
    {
        try{
            scartLog::logLine('D-Input_verify(model): add a verify-item (again)');
            // update current
            self::changeItem($this, SCART_VERIFICATION_DONE);
            // add new one
            self::addItem($this->input_id, 0, SCART_VERIFICATION_VERIFY);
        } catch (Exception $err) {
            scartLog::logLine("E-Input_verify(insertVerify); exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
        }

    }

    /**
     * @description Set item om Complete/hash
     */
    public function setComplete()
    {
        try{
            scartLog::logLine('D-Input_verify(model): Multiply verification is successfull completed ');
            self::changeItem($this, SCART_VERIFICATION_COMPLETE, true);
        } catch (Exception $err) {
            scartLog::logLine("E-Input_verify(model); exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
        }
    }

    public function setFailed()
    {
        try{
            scartLog::logLine('D-Input_verify(model): Multiply verification failed');
            self::changeItem($this, SCART_VERIFICATION_FAILED, true);
        } catch (Exception $err) {
            scartLog::logLine("E-Input_verify(setFailed); exception on line " . $err->getLine() . " in " . $err->getFile() . "; message: " . $err->getMessage());
        }
    }
    /**
     * @param $workuser_id
     * @return mixed
     */
    public static function getAlreadyVerifiedItemsIdsByUser($workuser_id)
    {
        // make the list dynamic and smaller by searching on status
        $stats = [SCART_VERIFICATION_ORIGINAL,SCART_VERIFICATION_DONE];        // always original and done
        $input_verify = Input_verify::where('workuser_id', $workuser_id)->whereIn('status', $stats)->lists('input_id', 'id');
        scartLog::logLine("D-Verify: workuser_id {$workuser_id} has " . count($input_verify) . " already verified items with the label: ".implode(',',$stats));
        return $input_verify;
    }

    /**
     * @return mixed
     */
    public static function getInputIdsOfFinishedItemsByStatus()
    {
        return Input_verify::whereIn('status', [SCART_VERIFICATION_FAILED, SCART_VERIFICATION_COMPLETE])->lists('input_id', 'id');
    }

    /**
     * @return array
     */
    public function getStatusOptions() {
        return [SCART_VERIFICATION_FAILED => 'failed', SCART_VERIFICATION_COMPLETE => 'success'];
    }

    /**
     * @param $item
     * @param $statusCode
     * @param bool $multiple
     */
    private static function changeItem($item, $statusCode, $multiple = false)
    {
        $user = BackendAuth::getUser();
        scartLog::logLine("D-Verify: Change item ".$item->id." to status: " . $statusCode . " by workuser_id: " . $user->id);

        $item->workuser_id = $user->id;
        $item->status = $statusCode;
        $item->save();

        if ($multiple) {
            scartLog::logLine("D-Verify: Change multiple items to status: " . $statusCode);

            $inputs = Input_verify::where([['input_id', $item->input_id]])->whereIn('status', [SCART_VERIFICATION_DONE, SCART_VERIFICATION_ORIGINAL])->get();

            if($inputs->count() > 0) {
                foreach($inputs as $input1) {
                    $input1->status = $statusCode;
                    $input1->save();
                }
            }
        }

    }

    /**
     * @param $dataID
     * @param string $userID
     * @param $status
     * @param string $grade_code
     */
    private static function addItem($dataID, $userID = 0, $status, $grade_code = '')
    {

        $verify                 = new Input_verify();
        $verify->input_id       = $dataID;
        $verify->workuser_id    = $userID;// add userid, so this user cant grade this item anymore
        $verify->status         = $status;

        // enable userId=0 for new record
        /*
        if (!empty($userID)) {
            $verify->workuser_id = $userID;
        } else {
            $user = BackendAuth::getUser();
            $verify->workuser_id = $user->id;
        }
        */

        if (!empty($grade_code)) {
            $verify->grade_code = $grade_code;
        }

        $verify->save();

        scartLog::logLine("D-Verify: add  item ".$verify->id." to status: " . $verify->grade_code . "by workuser_id: " . $verify->workuser_id);

    }
}
