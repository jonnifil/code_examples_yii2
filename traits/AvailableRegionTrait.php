<?php
/**
 * Created by PhpStorm.
 * User: jonni
 * Date: 18.06.18
 * Time: 11:24
 */

namespace app\helpers;


use app\models\BlockedAddressLog;
use app\models\Region;

trait AvailableRegionTrait
{

    /**
     * Доступные регионы
     * @return mixed
     */
    public function actionAvailableRegionList()
    {
        $availableRegionList = Region::getActiveRegions()->column();
        return \Yii::createObject([
            'class' => 'yii\web\Response',
            'format' => \yii\web\Response::FORMAT_JSON,
            'data' => [
                'available_list' => $availableRegionList,
            ],
        ]);
    }

    /**
     * Отметка о блокировке адреса по региону
     * @return integer||bool
     */
    public function actionSaveBlockAddress()
    {
        $address = POST('blocked_address');
        $input_id = POST('input_id');
        if (isset($address) && isset($input_id)) {
            $type = explode('_', $input_id)[2];
            $blocked_address = new BlockedAddressLog();
            $blocked_address->block_time = date('Y-m-d H:i:s');
            $blocked_address->ip = $_SERVER['REMOTE_ADDR'];
            $blocked_address->user_id = isset(IDENTITY()->currentUserCompany->id) ? IDENTITY()->currentUserCompany->id : null;
            $blocked_address->address = $address;
            $blocked_address->type = $type;
            $blocked_address->save();
            return $blocked_address->id;
        }
        return false;
    }

}