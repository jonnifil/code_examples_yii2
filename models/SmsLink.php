<?php

namespace app\models;

use app\services\Notification;
use Yii;

/**
 * This is the model class for table "sms_link".
 *
 * @property integer $id
 * @property integer $type
 * @property string $hash
 * @property integer $obj_type
 * @property integer $obj_id
 * @property integer $user_id
 * @property integer $utm хранит id action из notif_template и забирает шаблон utm оттуда
 * @property integer $created_at
 */
class SmsLink extends \yii\db\ActiveRecord
{

    const TYPE_OBJECT_ORDER = 1;
    const TYPE_OBJECT_USER  = 2;
    const TYPE_OBJECT_DRIVER  = 3;
    const TYPE_OBJECT_CAR  = 4;

    const TYPE_SMS_DRIVER_ORDER_INFO = 1; //Смс водителю с информацией о заказе
    const TYPE_SMS_CARRIER_NEW_ORDER = 2; //Смс перевозчику о новом заказе
    const TYPE_SMS_CLIENT_MOVIZOR    = 3; //Смс со ссылкой на отслеживание
    const TYPE_SMS_NEW_OFFER         = 4; //Смс о новом ценовом предложении
    const TYPE_SMS_STOP_NOTIFICATION = 5; //Смс о прекращении оповещения о новых заказах
    const TYPE_SMS_MOBILE_LINK       = 6; //Смс на страницу линков моб. приложения
    const TYPE_SMS_ORDER_CLIENT       = 7; //Смс на заказа для заказчика
    const TYPE_SMS_ORDER_CARRIER       = 8; //Смс на заказа для перевозчика
    const TYPE_SMS_DRIVER_CARRIER       = 9; //Смс для просмотра карточки водителя
    const TYPE_SMS_CAR_CARRIER       = 10; //Смс для просмотра карточки авто
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'sms_link';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'hash', 'obj_id'], 'required'],
            [['type', 'obj_type', 'obj_id', 'user_id', 'created_at', 'utm'], 'integer'],
            [['hash'], 'string', 'max' => 6],
            [['hash'], 'unique'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('db', 'ID'),
            'type' => Yii::t('db', 'Тип смс'),
            'hash' => Yii::t('db', 'Код для ссылки'),
            'obj_type' => Yii::t('db', 'Тип объекта'),
            'obj_id' => Yii::t('db', 'Идентификатор объекта'),
            'user_id' => Yii::t('db', 'Идентификатор пользователя, которому отправлена смс'),
            'created_at' => Yii::t('db', 'Идентификатор пользователя, которому отправлена смс'),
        ];
    }

    /**
     * Поиск по хеш
     * @param $hash
     * @return mixed
     */
    public static function findByHash($hash)
    {
        return self::find()->where('hash = :hash',[':hash' => $hash])->one();
    }

    /**
     * Генерация уникального хеша для ссылки
     * @return string
     */
    protected static function generateHash()
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $length = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 6; $i++) {
            $randomString .= $characters[rand(0, $length - 1)];
        }
        if (is_object(self::findByHash($randomString))) {
            return self::generateHash();
        } else {
            return $randomString;
        }
    }

    public function beforeValidate()
    {
        $this->hash = self::generateHash();
        $this->created_at = time();
        return parent::beforeValidate();
    }
}
