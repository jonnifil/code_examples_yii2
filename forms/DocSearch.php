<?php

namespace app\modules\admin\forms\search;

use app\models\Order;
use app\models\Payment;
use app\models\SysCompany;
use Exception;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

class DocSearch extends Order
{
    public $start_date;
    public $end_date;
    public $from_client_payment;
    public $to_carrier_payment;
    public $to_client_docs;
    public $to_carrier_docs;
    public $export_excel;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id'], 'integer'],
            [['id', 'start_date', 'end_date', 'user', 'dispatcher', 'to_carrier_payment', 'from_client_payment','to_client_docs','to_carrier_docs','export_excel'], 'safe']
        ];
    }

    public function getUniqueUser()
    {
        $response = [];
        $query = Order::find()
            ->select("user")
            ->distinct()->andWhere(['IN', 'state', Order::$viewDocOrderStates])
            ->andWhere(['>', 'order.created_at', strtotime(DATE_START_PAD)]);
        $query = $query->all();
        if (!empty($query))
            foreach ($query as $item) {
                $response[$item->userCompany->id] = $item->userCompany->company->organization . " / " . $item->userCompany->company->inn;
            }

        return $response;
    }

    public function getUniqueDispatcher()
    {
        $response = [];
        $query = Order::find()
            ->select("dispatcher")
            ->distinct()
            ->where([
                "IS NOT", "dispatcher", null
            ])->andWhere(['IN', 'state', Order::$viewDocOrderStates])
            ->andWhere(['>', 'order.created_at', strtotime(DATE_START_PAD)]);
        $query = $query->all();
        if (!empty($query))
            foreach ($query as $item) {
                $response[$item->dispatcherCompany->id] = $item->dispatcherCompany->company->organization . " / " . $item->dispatcherCompany->company->inn;
            }

        return $response;
    }

    /**
     * Creates data provider instance with search query applied
     *
     * @param array $params
     *
     * @return ActiveDataProvider
     */
    public function search($params)
    {

        $query = Order::find()->select('`order`.*')
            ->leftJoin(['carrier_payment'=>Payment::tableName()],'`carrier_payment`.`id_order`=`order`.`id` AND `carrier_payment`.`id_user`=`order`.`dispatcher`')
            ->leftJoin(['client_payment'=>Payment::tableName()],'`client_payment`.`id_order`=`order`.`id` AND `client_payment`.`id_user`=`order`.`user`')
            ->andWhere(['IN', 'state', Order::$viewDocOrderStates])
            ->andWhere(['>', 'order.created_at', strtotime(DATE_START_PAD)])->groupBy('order.id');

        // add conditions that should always apply here

        $dataProvider = new ActiveDataProvider([
            'query' => $query,

            'sort' => [
                'attributes' => [
                    'order.realization' => [
                        'asc' => [new Expression('order.realization IS NOT NULL ASC')],
                        'desc' => [new Expression('order.realization IS NULL DESC')],
                    ],
                    'from_date'
                ],
                'defaultOrder' => [
                    'order.realization' => SORT_DESC,
                    'from_date' => SORT_DESC
                ]
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            // uncomment the following line if you do not want to return any records when validation fails
            // $query->where('0=1');
            return $dataProvider;
        }

        if (!empty($this->id)) {
            $query->andWhere([Order::tableName() . '.id' => $this->id]);
        }


        if (!empty($this->user)) {
            $companyUser = SysCompany::findOne($this->user);
            if (is_object($companyUser)) {
                $ids = $companyUser->getCompanyUserIds();
                $query->andWhere([Order::tableName() . '.user' => $ids]);
            }
        }

        if (!empty($this->dispatcher)) {
            $companyUser = SysCompany::findOne($this->dispatcher);
            if (is_object($companyUser)) {
                $ids = $companyUser->getCompanyUserIds();
                $query->andWhere([Order::tableName() . '.dispatcher' => $ids]);
            }
        }

        if (!empty($this->start_date)) {
            $start_date = getDateFromString($this->start_date, true);
            $query->andFilterWhere(['>=', 'from_date', $start_date]);
        }

        if (!empty($this->end_date)) {
            $end_date = getDateFromString($this->end_date, false);
            $query->andFilterWhere(['<=', 'from_date', $end_date]);
        }

        if (!empty($this->from_client_payment)) {
            switch ($this->from_client_payment) {
                case '1':
                    $query->andWhere('client_payment.id IS NULL');
                    break;
                case '2':
                    $query->andWhere('client_payment.id IS NOT NULL');
                    break;
                default:
            }
        }

        if (!empty($this->to_carrier_payment)) {
            switch ($this->to_carrier_payment) {
                case '1':
                    $query->andWhere('carrier_payment.id IS NULL');
                    break;
                case '2':
                    $query->andWhere('carrier_payment.id IS NOT NULL');
                    break;
                default:
            }
        }

        if (!empty($this->to_carrier_docs)) {
            $query->joinWith('carrierTracks as carrier_track');
            $query->joinWith('orderDocument as od');
            switch ($this->to_carrier_docs) {
                case '1':
                    $query->andWhere('carrier_track.id IS NULL');
                    $query->andWhere(['not in','od.carrier_status',[ORDER_DOC_STATE_ACCEPT, ORDER_DOC_STATE_REJECT]]);
                    break;
                case '2':
                    $query->andWhere('carrier_track.id IS NOT NULL');
                    $query->andWhere(['not in','od.carrier_status',[ORDER_DOC_STATE_ACCEPT, ORDER_DOC_STATE_REJECT]]);
                    break;
                case '3':
                    $query->andWhere('od.carrier_status=:status', [':status'=>ORDER_DOC_STATE_ACCEPT]);
                    break;
                case '4':
                    $query->andWhere('od.carrier_status=:status', [':status'=>ORDER_DOC_STATE_REJECT]);
                    break;
                default:
            }
        }

        if (!empty($this->to_client_docs)) {
            $query->joinWith('clientTracks as client_track');
            $query->joinWith('realizationData as rd');
            switch ($this->to_client_docs) {
                case '1':
                    $query->andWhere('client_track.id IS NULL');
                    $query->andWhere('rd.id IS NULL OR (rd.client_status<>:status AND rd.client_status<>:status2)', [':status'=>ORDER_DOC_STATE_ACCEPT, ':status2'=>ORDER_DOC_STATE_REJECT]);
                    break;
                case '2':
                    $query->andWhere('client_track.id IS NOT NULL');
                    $query->andWhere('rd.id IS NOT NULL');
                    $query->andWhere(['not in','rd.client_status',[ORDER_DOC_STATE_ACCEPT, ORDER_DOC_STATE_REJECT]]);
                    break;
                case '3':
                    $query->andWhere('rd.client_status=:status', [':status'=>ORDER_DOC_STATE_ACCEPT]);
                    break;
                case '4':
                    $query->andWhere('rd.client_status=:status', [':status'=>ORDER_DOC_STATE_REJECT]);
                    break;
                default:
            }
        }
        //Если запрошен вывод в эксель - формируем файл на основе выборки по фильтрам
        if ($this->export_excel == 1) {
            $file = \Yii::createObject([
                'class' => 'codemix\excelexport\ExcelFile',
                'fileOptions' => [
                    'directory' => __DIR__.'/../../../../../web/upload/xls',
                ],
                'sheets' => [
                    'GroozGo Order Docs' => self::getData($query)
                ],
            ]);
            $file->send($this->getReportName());
        }
        //Или отдаём в отрисовку грида
        else return $dataProvider;
    }

    protected static function getId($model)
    {
        return $model->id;
    }

    protected static function getFromDate($model)
    {

        $date = (!empty($model->from_date) ? Yii::$app->formatter->asDate($model->from_date) : "");
        return $date;
    }

    protected static function getSum($model)
    {
        $summary = empty($model->getBrokerBonus()) ? $model->summary - $model->insurance_price : $model->summary - $model->getBrokerBonus() - $model->insurance_price;
        //$sum = number_format($summary, 0, ".", " ") ;
        return $summary;
    }

    protected static function getUser($model)
    {
        return $model->userCompany->company->organization;
    }

    protected static function getRealization($model)
    {
        return $model->realization;
    }

    protected static function getRealizationDate($model)
    {
        return !empty($model->realizationData->date_realization) ? Yii::$app->formatter->asDate($model->realizationData->date_realization) : "";
    }

    protected static function getClientPayDate($model)
    {
        if (!empty($model->realizationData) && !empty($model->realizationData->client_payment_date)) {
            return Yii::$app->formatter->asDate($model->realizationData->client_payment_date);
        }
        return '';
    }

    protected static function getClientDocDate($model)
    {
        if (!empty($model->realizationData) && !empty($model->realizationData->client_doc_date_accept)) {
            return Yii::$app->formatter->asDate($model->realizationData->client_doc_date_accept);
        }
        return '';
    }

    protected static function getClientPayed($model)
    {
        $payments = $model->getPayments($model->user)->all();
        if (!empty($payments)) {
            $sum = 0;
            foreach ($payments as $payment) {
                $sum = $sum + $payment->sum_operation;
            }
            return $sum;
        }
        return '';
    }

    protected static function getClientPayedDate($model)
    {
        $payment = $model->getPayments($model->user)->orderBy(['date_operation' => SORT_DESC])->limit(1)->one();
        return is_object($payment) ? Yii::$app->formatter->asDate($payment->date_operation) : '';
    }

    protected static function getClientDocStatus($model)
    {
        if (!empty($model->realizationData)) {
            $realization = $model->realizationData;
            if ($realization->client_status) {
                if ($realization->client_status == ORDER_DOC_STATE_ACCEPT) {
                    return 'Документы доставлены';
                } else {
                    $track = $model->getTracks(TYPE_CLIENT)->orderBy(['id' => SORT_DESC])->one();
                    $last_state = $track->getStatuses()->one();
                    if ($track){
                        if (empty($last_state)) {
                            return 'Документы отправлены';
                        } else {
                            if ($last_state->status == ORDER_DOC_STATE_PROCESS) {
                                return $last_state->message;
                            } else {
                                $message = (empty($last_state->message)) ? "" : " - " . $last_state->message;
                                return \app\models\OrderDocument::getDocStatusName($last_state->status) . $message;
                            }
                        }
                    }
                }
            } else return 'Документы пока не отправлены';
        }
        return "Нет реализации";
    }

    protected static function getCarrier($model)
    {
        $organization = isset($model->dispatcherCompany->company->organization) ? $model->dispatcherCompany->company->organization : "";
        return $organization;
    }

    protected static function getCarrierDocDate($model)
    {
        $date = "";
        if (isset($model->orderDocument->carrier_tsd_date)) {
            $date = Yii::$app->formatter->asDate($model->orderDocument->carrier_tsd_date);
        }
        return $date;
    }

    protected static function getCarrierPayDate($model)
    {
        if (!empty($model->orderDocument) && !empty($model->orderDocument->carrier_payment_date)) {
            return Yii::$app->formatter->asDate($model->orderDocument->carrier_payment_date);
        }
        return "";
    }

    protected static function getSumToCarrier($model)
    {
        $sum = $model->carrier_price;
        return $sum;
    }

    protected static function getCarrierPayed($model)
    {
        $payments = $model->getPayments($model->dispatcher)->all();
        if (!empty($payments)) {
            $sum = 0;
            foreach ($payments as $payment) {
                $sum = $sum + $payment->sum_operation;
            }
            return $sum;
        }
        return '';
    }

    protected static function getCarrierPayedDate($model)
    {
        $payment = $model->getPayments($model->dispatcher)->orderBy(['date_operation' => SORT_DESC])->limit(1)->one();
        return is_object($payment) ? Yii::$app->formatter->asDate($payment->date_operation) : '';
    }

    protected static function getCarrierDocStatus($model)
    {
        if (!empty($model->orderDocument) && $model->orderDocument->carrier_status) {
            if ($model->orderDocument->carrier_status == ORDER_DOC_STATE_ACCEPT) {
                $status = \app\models\OrderDocument::getDocStatusName($model->orderDocument->carrier_status);
                return $status;
            }
            elseif ($model->orderDocument->carrier_status == ORDER_DOC_STATE_REJECT) {
                $status = \app\models\OrderDocument::getDocStatusName($model->orderDocument->carrier_status);
                $reason = $model->orderDocument->doc_cancel_reason;
                return $status . $reason;
            } else {
                $track = $model->getTracks(TYPE_DRIVER)->orderBy(['id' => SORT_DESC])->one();
                $last_state = $track->getStatuses()->one();
                if (empty($last_state)) {
                    return 'Документы отправлены';
                } else {
                    if ($last_state->status == ORDER_DOC_STATE_PROCESS) {
                        return $last_state->message;
                    } else {
                        $message = (empty($last_state->message)) ? "" : " - " . $last_state->message;
                        return \app\models\OrderDocument::getDocStatusName($last_state->status) . $message;
                    }
                }
            }
        } else return 'Не введен трек-код';
    }

    protected static function getCarrierDocStatusDate($model)
    {
        $date = '';
        if (!empty($model->orderDocument) && $model->orderDocument->carrier_status) {
            if ($model->orderDocument->carrier_status == ORDER_DOC_STATE_ACCEPT) {
                $date = (!empty($model->orderDocument->carrier_doc_date_status)) ? Yii::$app->formatter->asDate($model->orderDocument->carrier_doc_date_status) : "";
            }
        }
        return $date;
    }

    public static function getData($query){
        $settings = [
            [
                'name' => 'id',
                'size' => 30,
                'label' => Yii::t("app", "№ заказа"),
            ],
            [
                'name' => 'fromDate',
                'size' => 40,
                'label' => Yii::t("app", "Дата загрузки"),
            ],
            [
                'name' => 'sum',
                'size' => 40,
                'label' => Yii::t("app", "Сумма руб."),
            ],
            [
                'name' => 'user',
                'size' => 40,
                'label' => Yii::t("app", "Заказчик"),
            ],
            [
                'name' => 'realization',
                'size' => 40,
                'label' => Yii::t("app", "№ счета и УПД"),
            ],
            [
                'name' => 'realizationDate',
                'size' => 40,
                'label' => Yii::t("app", "Дата счета и УПД"),
            ],
            [
                'name' => 'clientPayDate',
                'size' => 40,
                'label' => Yii::t("app", "Срок оплаты заказчиком по договору"),
            ],
            [
                'name' => 'clientPayed',
                'size' => 40,
                'label' => Yii::t("app", "Оплачено заказчиком (сумма)"),
            ],
            [
                'name' => 'clientPayedDate',
                'size' => 40,
                'label' => Yii::t("app", "Оплачено заказчиком (дата)"),
            ],
            [
                'name' => 'clientDocStatus',
                'size' => 40,
                'label' => Yii::t("app", "Статус доставки пакета документов заказчику"),
            ],
            [
                'name' => 'clientDocDate',
                'size' => 40,
                'label' => Yii::t("app", "Дата получения заказчиком"),
            ],
            [
                'name' => 'carrier',
                'size' => 40,
                'label' => Yii::t("app", "Перевозчик"),
            ],
            [
                'name' => 'sumToCarrier',
                'size' => 40,
                'label' => Yii::t("app", "Сумма перевозчику"),
            ],
            [
                'name' => 'carrierDocDate',
                'size' => 40,
                'label' => Yii::t("app", "Дата предоставления пакета документов по договору"),
            ],
            [
                'name' => 'carrierPayDate',
                'size' => 40,
                'label' => Yii::t("app", "Срок оплаты перевозчику по договору"),
            ],
            [
                'name' => 'carrierPayed',
                'size' => 40,
                'label' => Yii::t("app", "Оплачено перевозчику (сумма)"),
            ],
            [
                'name' => 'carrierPayedDate',
                'size' => 40,
                'label' => Yii::t("app", "Оплачено перевозчику (дата)"),
            ],
            [
                'name' => 'carrierDocStatus',
                'size' => 40,
                'label' => Yii::t("app", "Статус доставки пакета документов от перевозчика"),
            ],
            [
                'name' => 'carrierDocStatusDate',
                'size' => 40,
                'label' => Yii::t("app", "Дата доставки пакета документов от перевозчика"),
            ],
        ];

        $orders = $query->all();
        $data = [];
        foreach ($orders as $order)
        {
            $item = [];
            foreach ($settings as $setting){
                $item[] = self::getValue($setting['name'], $order);
            }
            $data[] = $item;
        }

        $sheet = [];
        $sheet['data'] = $data;
        $titles = [];
        foreach ($settings as $setting){
            $titles[] = $setting['label'];
        }
        $sheet['titles'] = $titles;
        return $sheet;
    }

    public static function getValue($name, $model)
    {
        try {
            $function = "get" . ucfirst($name);
            return self::$function($model);
        }
        catch (Exception $e)
        {
            return "";
        }
    }

    private function getReportName()
    {
        return 'orders_docs_report_' . date('d-m-Y_H:i') . '.xlsx';
    }


}
