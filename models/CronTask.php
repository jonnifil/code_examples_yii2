<?php

namespace app\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "cron_task".
 *
 * @property integer $id
 * @property integer $object_type_id
 * @property integer $object_id
 * @property integer $cron_state
 * @property integer $created_at
 * @property integer $updated_at
 */
class CronTask extends \yii\db\ActiveRecord
{
    const STATE_TASKED    = 0; //Задача поставлена
    const STATE_COMPLETED = 1; //Задача успешно выполнена
    const STATE_FAILURE   = 2; //Выполнение провалено

    const UPDATE_FINISHED_NOTIF       = 1; //Обновление уведомлений на основе выполненных заказов. Объект CompanyUser
    const SEND_INTERESTING_ORDER      = 2; //Отправка уведомлений об "интересных" грузах. Объект Order
    const SET_AVAILABLE_CARRIERS      = 3; //заполнение таблицы подходящих перевозчиков. Объект Order
    const SEND_APPROVE_ORDER          = 4;//Отправка уведомлений о закреплении заказа. Объект Order
    const SEND_APPROVE_ORDER_EXPIRED  = 5;//Отправка уведомления админу о просрочке закрепленного заказа. Объект Order
    const SEND_APPROVE_ORDER_CANCELED = 6;//Отправка уведомления об отмене закрепления заказа. Объект OrderCarrier
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'cron_task';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['object_type_id', 'object_id'], 'required'],
            [['object_type_id', 'object_id', 'cron_state', 'created_at', 'updated_at'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('db', 'ID'),
            'object_type_id' => Yii::t('db', 'Идентификатор типа (константа)'),
            'object_id' => Yii::t('db', 'Идентификатор объекта'),
            'cron_state' => Yii::t('db', 'Состояние задачи 0 - поставлена, 1 - выполнена, 2 - провалена'),
            'created_at' => Yii::t('db', 'Время создания'),
            'updated_at' => Yii::t('db', 'Время обновления'),
        ];
    }

    /**
     * @param int $objectTypeId
     * @param int $objectId
     * @param bool $unique
     */
    public static function setTask(int $objectTypeId, int $objectId, $unique=true)
    {
        $query = self::find()
            ->where([
                'object_type_id' => $objectTypeId,
                'object_id' => $objectId
            ])
            ->limit(1)
            ;
        if ($unique) {
            $query->andWhere(['<>', 'cron_state', self::STATE_COMPLETED]);
        }
        $task = $query->one();
        if (!is_object($task)) {
            $task = new self();
            $task->object_type_id = $objectTypeId;
            $task->object_id = $objectId;
        }
        $task->cron_state = self::STATE_TASKED;
        $task->save();
    }
}
