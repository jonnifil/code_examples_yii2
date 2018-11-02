<?php
/**
 * Created by PhpStorm.
 * User: jonni
 * Date: 20.09.18
 * Time: 12:29
 */

namespace app\forms;


use app\models\CompanyUser;
use app\models\Invite;
use app\models\Registration;
use app\models\SmsStock;
use app\models\SysCompany;
use app\models\UserNew;
use app\services\Notification;
use Yii;
use yii\base\Model;
use yii\db\Exception;

class InviteSignup extends Model
{
    const SCENARIO_TEMP = 'temp';
    const SCENARIO_CONFIRM = 'confirm';
    const SCENARIO_AUTH = 'auth';
    const SCENARIO_USER = 'user';


    public $uemail;
    public $upassword;
    public $phone;
    public $uname;
    public $usurname;
    public $smscode;
    public $hash;
    public $invite;



    public function attributeLabels()
    {
        return [
            'upassword' => Yii::t("db", "Пароль"),
            'uname' => Yii::t("db", "Имя"),
            'usurname' => Yii::t("db", "Фамилия"),
            'phone' => Yii::t("db", "Мобильный телефон"),
        ];
    }


    public function scenarios()
    {
        return [
            self::SCENARIO_TEMP => ['phone', 'hash'],
            self::SCENARIO_CONFIRM => ['hash', 'smscode'],
            self::SCENARIO_AUTH => ['phone', 'hash','upassword'],
            self::SCENARIO_USER => ['phone', 'hash','upassword', 'uemail', 'uname', 'usurname',],
        ];
    }

    public function rules()
    {
        return [
            [['phone'], 'required', 'on' => self::SCENARIO_TEMP],
            [['smscode'], 'required', 'on' => self::SCENARIO_CONFIRM],
            [['phone', 'hash','upassword', 'uemail', 'uname', 'usurname',], 'required', 'on' => self::SCENARIO_USER],
            [['phone', 'hash','upassword',], 'required', 'on' => self::SCENARIO_AUTH],
            [['uemail'], 'string', 'max' => 150],
            [['smscode'], 'string', 'min' => 4, 'max' => 4],
            [['phone'], 'string', 'max' => 20, 'message' => 'Введите корректный номер телефона.'],
            [['uname', 'usurname'], 'string', 'max' => 50],
            [['upassword'], 'string', 'min' => USER_MIN_PASSWORD],
            [['uemail'], 'email'],
            [['phone'], 'filter', 'filter' => function ($value) {
                $value = preg_replace('~\D+~', '', $value);
                $value = formatPhone($value);
                return $value;
            }, 'message' => 'Введите корректный номер телефона.'],
            [['is_accept_nds', 'phone', 'uname', 'usurname', 'upassword', 'uemail'], 'safe']
        ];
    }

    public function validate($attributeNames = null, $clearErrors = true)
    {
        if (parent::validate($attributeNames, $clearErrors)) {
            if ($this->scenario == self::SCENARIO_TEMP) {
                $current = UserNew::findByEmail($this->invite->email);
                if (is_object($current) && $current->phone != $this->phone) {
                    $this->addError("phone", Yii::t("error", "Телефон не соответствует e-mail из приглашения"));
                }
            }

            if ($this->scenario == self::SCENARIO_USER) {
                $current = UserNew::findByEmail($this->uemail);
                if (!empty($current)) {
                    $this->addError("uemail", Yii::t("error", "Пользователь с таким email уже зарегистрирован"));
                }
            }
            if ($this->scenario == self::SCENARIO_AUTH) {
                $user = UserNew::findByPhone($this->phone);
                if (!($user->validatePassword($this->upassword) || $this->upassword == "*****")) {
                    $this->addError("password", Yii::t("error", "Неверный логин/пароль"));
                }
            }
        }

        return !$this->hasErrors();
    }

    public function getResponse(Invite $invite, $params)
    {

        switch ($params['step_index']) {
            case 0:
                return $this->create_temp_user($invite, $params);
            case 1:
                return $this->validate_temp_user($invite, $params);
            case 2:
                return $this->validate_auth($invite, $params);
            case 3:
                return $this->validate_new_user($invite, $params);

        }
    }


    private static function delete_hash($hash)
    {
        $registrations = Registration::find()->where(['hash' => $hash])->all();
        foreach ($registrations as $registry) {
            $registry->hash = '';
            $registry->save();
        }
    }

    /**
     * Валидация шага запроса авторизации пользователя
     * @param Invite $invite
     * @param $params
     * @return array
     */
    private function create_temp_user(Invite $invite, $params)
    {
        $response = [];

        $this->scenario = self::SCENARIO_TEMP;
        $phone = substr(preg_replace('/[^0-9]/', '', $params['phone']), 1);

        $this->phone = $phone;
        $this->hash = $params['hash'];
        $this->invite = $invite;

        self::delete_hash($params['hash']);

        if ($this->validate() && isset($phone)) {
            $user = UserNew::findByPhone($phone);

            if ($user) {
                if ($user->admin_access == 1) {
                    $response['result'] = 'error';
                    $response['response'] = [];
                    $response['response']['name'] = "ADMIN_REGISTERED";
                    return $response;
                }
                $response['result'] = 'success';
                $response['phone'] = $phone;
                $response['phone_display'] = $params['phone'];
                $response['next_step'] = 3;

                return $response;
            }

            if (empty($user)) {
                $registry = Registration::findByPhone($phone);
                if (!is_object($registry)){
                    $registry = new Registration();
                    $registry->phone = $phone;
                    $registry->step = 0;
                }
                $registry->code = SmsStock::generate();
                $registry->hash = $params['hash'];
                $registry->save();
                SmsStock::add($registry->phone, $registry->code);
            }



            $response['result'] = 'success';
            $response['phone'] = $phone;
            $response['phone_display'] = $params['phone'];
            $response['next_step'] = '2';

            return $response;
        }

        $response['result'] = 'error';
        $response['response'] = [];
        foreach ($this->errors as $attr => $error) {
            $response['response'][$attr] = $error[0];
        }

        return $response;

    }

    private function validate_refresh_code(Invite $invite, $params)
    {
        $this->scenario = self::SCENARIO_TEMP;
        $this->phone = $phone = $params['phone'];
        $response = [];
        $this->hash = $params['hash'];
        $this->invite = $invite;
        if ($this->validate() && isset($phone)) {
            $user = UserNew::findByPhone($phone);

            if ($user) {
                if ($user->admin_access == 1) {
                    $response['result'] = 'error';
                    $response['response'] = [];
                    $response['response']['name'] = "ADMIN_REGISTERED";
                    return $response;
                }
                $response['result'] = 'success';
                $response['phone'] = $phone;
                $response['phone_display'] = viewPhone($phone);
                $response['next_step'] = 3;

                return $response;
            }

            if (empty($user)) {
                $registry = Registration::findByPhone($phone);
                if (!is_object($registry)){
                    $registry = new Registration();
                    $registry->phone = $phone;
                    $registry->step = 0;
                }
                $registry->code = SmsStock::generate();
                $registry->hash = $params['hash'];
                $registry->save();
                SmsStock::add($registry->phone, $registry->code);
            }



            $response['result'] = 'success';
            $response['phone'] = $phone;
            $response['phone_display'] = viewPhone($phone);
            $response['next_step'] = '2';

            return $response;
        }

        $response['result'] = 'error';
        $response['response'] = [];
        foreach ($this->errors as $attr => $error) {
            $response['response'][$attr] = $error[0];
        }

        return $response;
    }

    /**
     * Валидация шага проверки кода из смс
     * @param Invite $invite
     * @param $params
     * @return array
     */
    private function validate_temp_user(Invite $invite, $params)
    {
        if ($params['refresh_code'] == 1){
            return $this->validate_refresh_code($invite, $params);
        }
        $this->scenario = self::SCENARIO_CONFIRM;
        $response = [];

        $this->smscode = $params['smscode'];
        $this->hash = $params['hash'];

        if ($this->validate()) {
            $registry = Registration::findByHash($params['hash']);

            if ($registry->code == $this->smscode) {
                $response['result'] = 'success';
                $response['phone'] = $registry->phone;
                $response['next_step'] = 4;
            } else {
                $response['result'] = 'error';
                $response['response'] = [];
                $response['response']['smscode'] = 'Коды не совпадают.';
            }
            $registry->step = 1;
            $registry->save();

            return $response;
        }

        $response['result'] = 'error';
        $response['response'] = [];

        foreach ($this->errors as $attr => $error) {
            $response['response'][$attr] = $error[0];
        }

        return $response;
    }

    /**
     * Валидация шага авторизации пользователя, чей аккаунт уже есть в системе (по телефону)
     * @param Invite $invite
     * @param $params
     * @return array
     */
    private function validate_auth(Invite $invite, $params)
    {
        $response = [];
        $this->scenario = self::SCENARIO_AUTH;

        $phone = $params['phone'];

        $this->phone = $phone;
        $this->hash = $params['hash'];
        $this->upassword = $params['upassword'];
        if ($this->validate()) {
            $user = UserNew::findByPhone($phone);
            USER()->login($user, USER_SESSION_TIME);
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $company_user = new CompanyUser();
                $company_user->sys_company_id = $invite->companyUser->sys_company_id;
                $company_user->user_id = IDENTITY()->id;
                $company_user->role_id = $invite->role_id;
                $company_user->active = CompanyUser::ACTIVE_WORK;
                $company_user->save();
                $invite->status = Invite::STATUS_REGISTRY;
                $invite->save();
                $transaction->commit();
                $session = Yii::$app->session;
                $session->set('sys_company_id', $invite->companyUser->sys_company_id);

                $response['result'] = 'signup_success';
                $response['url'] = $invite->companyUser->type == SysCompany::TYPE_CLIENT ?
                    '/broker/order/index' :
                    '/carrier/order/index';
                Notification::SendInviteAccepted($invite);

                return $response;
            } catch (Exception $e) {
                if (!USER()->isGuest)
                    USER()->logout();
                $transaction->rollBack();
                $response['result'] = 'error';
                $response['response']['password'] = 'Ошибка сохранения. Попробуйте воспользоваться приглашением позже или обратитесь к команде GroozGo';

                return $response;
            }

        }

        $response['result'] = 'error';
        $response['response'] = [];
        foreach ($this->errors as $attr => $error) {
            $response['response'][$attr] = $error[0];
        }

        return $response;
    }

    /**
     * Валидация шага ввода данных нового пользователя
     * @param Invite $invite
     * @param $params
     * @return array
     */
    private function validate_new_user(Invite $invite, $params)
    {
        $response = [];
        $this->scenario = self::SCENARIO_USER;
        $this->hash = $params['hash'];

        $this->setAttributes($params);
        $this->uemail = $invite->email;
        $registry = Registration::findByHash($this->hash);
        $this->phone = $registry->phone;

        if ($this->validate()) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $user = new UserNew();
                $user->phone = $registry->phone;
                $user->email = $invite->email;
                $user->password = UserNew::generatePassword($this->upassword);
                $user->firstname = $this->uname;
                $user->surname = $this->usurname;
                if ($user->save()) {
                    $user->refresh();
                    USER()->login($user, USER_SESSION_TIME);
                    $registry->step = 2;
                    $registry->save();
                    $company_user = new CompanyUser();
                    $company_user->sys_company_id = $invite->companyUser->sys_company_id;
                    $company_user->user_id = IDENTITY()->id;
                    $company_user->role_id = $invite->role_id;
                    $company_user->active = CompanyUser::ACTIVE_WORK;
                    $company_user->save();
                    $invite->status = Invite::STATUS_REGISTRY;
                    $invite->save();
                    $transaction->commit();
                    $session = Yii::$app->session;
                    $session->set('sys_company_id', $invite->companyUser->sys_company_id);

                    $response['result'] = 'signup_success';
                    $response['url'] = $invite->companyUser->type == SysCompany::TYPE_CLIENT ? '/broker/setting/index' : '/carrier/order/index';
                    Notification::SendInviteAccepted($invite);

                    return $response;
                }
            } catch (Exception $e) {
                if (!USER()->isGuest)
                    USER()->logout();
                $transaction->rollBack();

                $response['result'] = 'error';
                $response['response']['password'] = 'Ошибка сохранения. Попробуйте воспользоваться приглашением позже или обратитесь к команде GroozGo';

                return $response;
            }

        }
        $response['result'] = 'error';
        $response['response'] = [];
        foreach ($this->errors as $attr => $error) {
            $response['response'][$attr] = $error[0];
        }

        return $response;
    }

}