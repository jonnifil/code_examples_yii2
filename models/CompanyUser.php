<?php

namespace app\models;

use app\services\DefaultNotif;
use app\services\Template;
use Yii;

/**
 * This is the model class for table "company_user".
 *
 * @property integer $id
 * @property integer $sys_company_id
 * @property integer $user_id
 * @property integer $role_id
 * @property integer $active
 * @property integer $amo_link
 *
 * @property Role $role
 * @property Orders[] $order
 * @property SysCompany $sysCompany
 * @property UserNew $userNew
 * @property NotifBase $notifBase
 * @property CompanyNew $company
 * @property CustomRate[] $crates
 * @property Notif[] $notif
 * @property string $fullName
 * @property string $phone
 * @property string $email
 * @property string $info
 * @property integer $type
 */
class CompanyUser extends \yii\db\ActiveRecord
{

    const ACTIVE_BLOCK    = -1;
    const ACTIVE_WORK     = 1;
    const ACTIVE_INACTIVE = 2;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'company_user';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['sys_company_id', 'user_id'], 'required'],
            [['sys_company_id', 'user_id', 'role_id', 'active', 'amo_link'], 'integer'],
            [['role_id'], 'exist', 'skipOnError' => true, 'targetClass' => Role::className(), 'targetAttribute' => ['role_id' => 'id']],
            [['sys_company_id'], 'exist', 'skipOnError' => true, 'targetClass' => SysCompany::className(), 'targetAttribute' => ['sys_company_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => UserNew::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'sys_company_id' => 'Компания в системе',
            'user_id' => 'Пользователь',
            'role_id' => 'Роль',
            'active' => 'Активность',
        ];
    }

    /**
     * Возвращает название статуса активности
     * @return string
     */
    public function getActiveName()
    {
        switch ($this->active) {
            case self::ACTIVE_BLOCK:
                return 'Заблокирован';
                break;
            case self::ACTIVE_WORK:
                return 'Активирован';
                break;
            case self::ACTIVE_INACTIVE:
                return 'Не активирован';
                break;
            default: return 'Неизвестный статус активности';
        }
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getRole()
    {
        return $this->hasOne(Role::className(), ['id' => 'role_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSysCompany()
    {
        return $this->hasOne(SysCompany::className(), ['id' => 'sys_company_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getCompany()
    {
        return $this->hasOne(CompanyNew::className(), ['id' => 'company_id'])->via('sysCompany');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUserNew()
    {
        return $this->hasOne(UserNew::className(), ['id' => 'user_id']);
    }

    /**
     * Все пользователи компании
     * @return \yii\db\ActiveQuery
     */
    public function getCompanyUsers()
    {
        return self::find()->where(['sys_company_id'=>$this->sys_company_id]);
    }

    /**
     * Массив идентификаторов пользователей
     * @return array
     */
    public function getCompanyUserIds()
    {
        return $this->getCompanyUsers()->select('id')->column();
    }

    /**
     * Массив основных почтовых адресов пользователей
     * @return array
     */
    public function getCompanyUserEmails()
    {
        $mailList = [];
        foreach ($this->getCompanyUsers()->all() as $companyUser) {
            $mailList[] = $companyUser->userNew->email;
        }
        array_unique($mailList);
        return $mailList;
    }

    /**
     * Массив идентификаторов пользователей компании в таблице авторизации
     * @return array
     */
    public function getCompanyUserNewIds()
    {
        $idList = [];
        foreach ($this->getCompanyUsers()->all() as $companyUser) {
            $idList[] = $companyUser->userNew->id;
        }
        array_unique($idList);
        return $idList;
    }

    /**
     * Адреса, созданные пользователем
     * @return \yii\db\ActiveQuery
     */
    public function getAddresses()
    {
        return $this->hasMany(Address::className(), ['user' => 'id']);
    }

    /**
     * Текущий бонус пользователя
     * @return int
     */
    public function getBonus(){
        $sum = 0;
        foreach ($this->promo as $promo)
        {
            $sum  = $sum + $promo->promo->sum;
        }
        return $sum;
    }

    /**
     * Получение промокодов пользователя
     * @return \yii\db\ActiveQuery
     */
    public function getPromo(){
        return $this->hasMany(PromoUser::className(), ["id_user" => "id"])->andWhere(['status'=>0]);
    }

    /**
     * Генерация токена для пользователя
     * @return UserToken
     */
    public function generateToken(){
        $token = new UserToken();
        $token->user = $this->id;
        $token->generateToken(time() + 3600 * 1000000);   //Уточнить время действия токена
        return $token->save() ? $token : $token;
    }

    /**
     * Удаление токена
     * @return bool
     */
    public function deleteToken(){
        $token = $this->userToken;
        return $token->delete() ? true : false;
    }

    /**
     * Получение документов пользователя
     * @return Document[]|array|\yii\db\ActiveRecord[]|boolean
     */
    public function getDocuments()
    {
        $docs = Document::find()
            ->andWhere(["type" => DOC_TYPE_CONTRACT])
            ->andWhere(["driver" => $this->id])
            ->andWhere([
                "AND",
                ["IS NOT", "file", null],
                ["<>", "file", ""],
            ])->all();
        $result = [];
        if (!empty($docs)) {
            if (count($docs) > 1) {
                foreach ($docs as $file) {
                    $result[] = $file->name;
                }
            } else {
                $result[] = $docs[0]->name;
            }
        }

        return  empty($result) ? false : $result;
    }


    /**
     * Настройки компании
     * @return mixed
     */
    public function getCompanySettings()
    {
        return $this->sysCompany->companySettings;
    }

    /**
     * Плательщик НДС
     * @return bool
     */
    public function isNDS()
    {
        return $this->sysCompany->isNDS();
    }

    /**
     * Тип компании пользователя
     * @return int
     */
    public function getType()
    {
        return $this->sysCompany->type;
    }

    /**
     * Получение массива пользователей компании(синоним)
     * @return array|int
     */
    public function getIds()
    {
        return $this->getCompanyUserIds();
    }

    /**
     * Идентификаторы клиентов перевозчика
     * @return array
     */
    public function getClientIds()
    {
        $ids = ClientCarrier::find()->select('sys_company_client_id')->distinct()->where(['sys_company_carrier_id' =>$this->sys_company_id])->column();
        $ids = is_array($ids) ? $ids : (is_null($ids) ? [] : [$ids]);
        if (empty($ids))
            return $ids;
        else return self::find()->select('id')->where(['sys_company_id' => $ids])->column();
    }

    /**
     * Полное имя (ФИО)
     * @return string
     */
    public function getFullName()
    {
        return $this->userNew->getFullname();
    }


    /**
     * Телефон
     * @return string
     */
    public function getPhone()
    {
        return $this->userNew->phone;
    }

    /**
     * Почта
     * @return string
     */
    public function getEmail()
    {
        return $this->userNew->email;
    }

    /**
     * check is user activated and checks daily order limit
     * @return bool
     */
    public static function checkDailyOrderLimit()
    {
        $user = IDENTITY()->currentUserCompany;
        if (is_object($user)):
            $userIds = $user->getCompanyUserIds();
            $todayOrder = Order::find()->where('FROM_UNIXTIME(`created_at`, \'%Y-%m-%d\') = CURDATE() AND state NOT IN ('.implode(',',Order::$archiveOrderStates).','.ORDER_STATE_MODERATE.')')->andWhere(['user' => $userIds])->count();
            return ($user->sysCompany->status != SysCompany::STATE_ACTIVE && $todayOrder >= Setting::getValue('client_order_limit',0)) ? false : true;
        else:
            return true;
        endif;
    }


    /**
     * Доступно ли оповещение по смс о новом заказе
     * @return bool
     */
    public function availableNewOrderSMS()
    {
        if ($this->sysCompany->type != SysCompany::TYPE_CARRIER)
            return false;

        $block = SmsBlockInfo::find()
            ->where([
                'user_id'=>$this->id,
                'event_id'=>SmsBlockInfo::BLOCK_NEW_ORDER_SMS_EVENT,
                'active'=>1
            ])
            ->limit(1)
            ->one();
        if (is_object($block))
        {
            return false;
        } else {
            return true;
        }
    }


    /** включение смс о новых заказах
     * @return bool|int
     */
    public function restartSmsNewOrder()
    {
        $blocks = SmsBlockInfo::find()
            ->where([
                'user_id'=>$this->id,
                'event_id'=>SmsBlockInfo::BLOCK_NEW_ORDER_SMS_EVENT,
                'active'=>1
            ])
            ->all();
        if ($blocks) {
            foreach ($blocks as $obj) {
                $obj->active = 0;
                $obj->save();
            }
            $user = IDENTITY();
            $creator = isset($user) ? $user->currentUserCompany : null;
            $startObj = new SmsBlockInfo();
            $startObj->user_id = $this->id;
            $startObj->event_id = SmsBlockInfo::START_NEW_ORDER_SMS_EVENT;
            $startObj->creator_id = isset($creator) ? $creator->id : 1;
            $startObj->save();
            return $startObj->id;
        }
        return false;
    }

    /**
     * @return array|null|\yii\db\ActiveRecord
     */
    public function lastSmsRestart()
    {
        return SmsBlockInfo::find()
            ->where([
                'user_id'=>$this->id,
                'event_id'=>SmsBlockInfo::START_NEW_ORDER_SMS_EVENT
            ])
            ->orderBy(['created_at'=>SORT_DESC])
            ->limit(1)
            ->one();
    }

    /**
     *
     */
    public function stopSmsNewOrder()
    {
        $startObj = new SmsBlockInfo();
        $startObj->user_id = $this->id;
        $startObj->event_id = SmsBlockInfo::BLOCK_NEW_ORDER_SMS_EVENT;
        $startObj->creator_id = 1;
        $startObj->save();
    }

    /**
     * Массив заказов пользователя
     * @return null|\yii\db\ActiveQuery
     */
    public function getOrders()
    {
        if ($this->sysCompany->type == SysCompany::TYPE_CLIENT)
            return $this->hasMany(Order::className(), ['user' => 'id']);
        elseif ($this->sysCompany->type == SysCompany::TYPE_CARRIER)
            return $this->hasMany(Order::className(), ['dispatcher' => 'id']);
        else return null;
    }

    /**
     * Фиксированные тарифы пользователя
     * @return \yii\db\ActiveQuery
     */
    public function getCrates()
    {
        return $this->hasMany(CustomRate::className(), ["id_user" => "id"]);
    }

    /**
     * Является ли пользователь Менеджером Оператора
     * @return bool
     */
    public function isManager()
    {
        return is_object(Manager::find()->where(['user_id' => $this->id])->limit(1)->one());
    }

    /**
     * Получение настроек пользователя
     * @return \yii\db\ActiveQuery
     */
    public function getNotifBase(){
        return $this->hasOne(NotifBase::className(), ["id_user" => "id"]);
    }

    /**
     * @return string
     */
    public function getTypeName()
    {
        return $this->sysCompany->getTypeName();
    }

    /**
     * Получение настроенных оповещений
     * @return \yii\db\ActiveQuery
     */
    public function getNotif()
    {
        return $this->hasMany(Notif::className(),['id_user'=>'id']);
    }




    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if ($insert) {
            $this->sendRegMail();
            switch ($this->sysCompany->type) {
                case SysCompany::TYPE_CLIENT :
                    DefaultNotif::saveForClientStart($this->id);
                    break;
                case SysCompany::TYPE_CARRIER :
                    DefaultNotif::saveForCarrierStart($this->id);
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Вывод информации о пользователе
     * @return int
     */
    public function getInfo(){
        $text = $this->company->organization;
        $text .= " (" . $this->getFullName() . ")";
        return $text;
    }

    /**
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert) {
                $isset = self::find()
                    ->where(['user_id' => $this->user_id, 'sys_company_id' => $this->sys_company_id])
                    ->limit(1)
                    ->one();
                if (is_object($isset))//дополнительно обеспечмваем уникальность связки пользователь-компания
                    return false;
            }
            return true;
        }
        return false;
    }

}
