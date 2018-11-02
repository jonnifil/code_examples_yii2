<?php
/**
 * Created by PhpStorm.
 * User: jonni
 * Date: 21.08.18
 * Time: 16:42
 */

namespace app\helpers;


use app\models\Order;
use app\modules\driver\forms\ExportExcelForms;

trait DocPrintFormsTrait
{

    /**
     * Печать Акта
     * @param $id
     */
    public function actionActPrint($id)
    {
        $order = Order::findOne($id);
        $excelOutput = ExportExcelForms::carrierAct($order);
        \Yii::$app->response->sendContentAsFile(
            $excelOutput,
            'carrier_act_'.$id.'_'.time().'.xlsx',
            ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Печать Счёта
     * @param $id
     */
    public function actionInvoicePrint($id)
    {
        $order = Order::findOne($id);
        $excelOutput = ExportExcelForms::carrierInvoice($order);
        \Yii::$app->response->sendContentAsFile(
            $excelOutput,
            'carrier_invoice_'.$id.'_'.time().'.xlsx',
            ['mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /**
     * Проверка полноты данных для печати
     * @param $id
     * @return string
     */
    public function actionCheckPrint($id)
    {
        $order = Order::findOne($id);
        $carrierCompany = $order->dispatcherCompany;
        $errors = [];
        if (!$carrierCompany->isIp() && empty($carrierCompany->company->director))
            $errors[] = 'ФИО руководителя';
        if (empty($carrierCompany->company->bik))
            $errors[] = 'БИК';
        if (empty($carrierCompany->company->bank_title))
            $errors[] = 'Наименование банка';
        if (empty($carrierCompany->company->corr_schet))
            $errors[] = 'Корреспондентский счет';
        if (empty($carrierCompany->company->schet))
            $errors[] = 'Номер банковского счета';
        return json_encode(['errors'=>$errors], JSON_UNESCAPED_UNICODE);
    }
}