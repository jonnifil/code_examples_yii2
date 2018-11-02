<?php
//Примеры рефактора методов
class Examples
{
    /**
     * @param $order_id
     * @param integer $adminCarrier (for admin update order, where you can select any driver)
     * @return array
     */
    public function getCanCars_old($order_id, $adminCarrier = 0)
    {
        $arr = [];

        if ($adminCarrier == 0):
            $ids = IDENTITY()->currentUserCompany->getIds();
        else:
            $companyUser = CompanyUser::findOne($adminCarrier);
            $ids = $companyUser->getIds();
        endif;

        $order = Order::findOne($order_id);

        $cars = Car::find()->andWhere(['company'=> $ids])
            ->andWhere(['<>', 'car.state', DRIVER_STATE_ARCHIVED])->andWhere(['car.is_passport'=> IS_PASSPORT_YES])
            ->all();

        foreach ($cars as $car) {
            $bodies = ArrayHelper::getColumn($car->typeBodyModel->selectTypeBodies, 'select_type_body');
            $tscats = ArrayHelper::getColumn($car->tsCategory->selectTsCategories, 'select_ts_category');
            $loads = ArrayHelper::getColumn($car->typeLoads, 'id');

            if (in_array($order->ts_category, $tscats)) {
                //проверка типа кузова
                $canBody = false;
                foreach ($order->typeBodyData as $body) {
                    if (in_array($body->id, $bodies)) {
                        $canBody = true;
                    }
                }
                //проверка типа загрузки
                $canLoad = false;
                foreach ($order->typeLoadData as $load) {
                    if (in_array($load->id, $loads)) {
                        $canLoad = true;
                    }
                }

                if ($canBody && $canLoad) {
                    $arr[$car->id] = $car->uniqName;
                }
            }
        }

        return $arr;

    }

    /**
     * @param $order_id
     * @param integer $adminCarrier (for admin update order, where you can select any driver)
     * @return array
     */
    public function getCanCars($order_id, $adminCarrier = 0)
    {
        $arr = [];

        if ($adminCarrier == 0):
            $ids = IDENTITY()->currentUserCompany->getIds();
        else:
            $companyUser = CompanyUser::findOne($adminCarrier);
            $ids = $companyUser->getIds();
        endif;

        $order = Order::findOne($order_id);
        $types = (!empty($order->type_body)) ? explode(',', $order->type_body) : [];


        //Выбираем типы кузова, подходящие по заказу
        $allTypes = TypeBodySelect::find()
            ->select('type_body')
            ->distinct()
            ->where(['select_type_body' => $types])
            ->column();

        //Выбираем категории ТС, подходящие по заказу
        $allCategories = TsCategorySelect::find()
            ->select('ts_category')
            ->distinct()
            ->where(['select_ts_category' => $order->ts_category])
            ->column();

        $query = Car::find()
            ->andWhere(['company'=> $ids])
            ->andWhere(['<>', 'car.state', DRIVER_STATE_ARCHIVED])
            ->andWhere(['car.is_passport'=> IS_PASSPORT_YES])
            ->andWhere(['car.type_body' => $allTypes])
            ->andWhere(['car.ts_category' => $allCategories]);

        $cars = $query->all();

        foreach ($cars as $car) {
            $loads = ArrayHelper::getColumn($car->typeLoads, 'id');
            //проверка типа загрузки
            $canLoad = false;
            foreach ($order->typeLoadData as $load) {
                if (in_array($load->id, $loads)) {
                    $canLoad = true;
                }
            }

            if ($canLoad) {
                $arr[$car->id] = $car->uniqName;
            }

        }

        return $arr;

    }


    /**
     * return array of carriers match current order
     * @param int $orderId
     * @return array
     */
    public function getAllCarriersMatchOrder_old(int $orderId)
    {
        $order = Order::findOne($orderId);

        $arr = [];

        $cars = Car::find()->joinWith('sysCompany sc')
            ->andWhere(['=','company_user.active', CompanyUser::ACTIVE_WORK])
            ->andWhere(['=', 'sc.status', SysCompany::STATE_ACTIVE])
            ->andWhere(['<>', 'car.state', DRIVER_STATE_ARCHIVED])->andWhere(['car.is_passport'=> IS_PASSPORT_YES])
            ->all();
        foreach ($cars as $car)
        {
            $bodies = ArrayHelper::getColumn($car->typeBodyModel->selectTypeBodies, 'select_type_body');
            $tscats = ArrayHelper::getColumn($car->tsCategory->selectTsCategories, 'select_ts_category');
            $loads = ArrayHelper::getColumn($car->typeLoads, 'id');

            if (in_array($order->ts_category, $tscats))
            {
                //проверка типа кузова
                $canBody = false;
                foreach ($order->typeBodyData as $body)
                {
                    if (in_array($body->id,$bodies))
                    {
                        $canBody = true;
                    }
                }
                //проверка типа загрузки
                $canLoad = false;
                foreach ($order->typeLoadData as $load)
                {
                    if (in_array($load->id,$loads))
                    {
                        $canLoad = true;
                    }
                }

                if ($canBody && $canLoad)
                {
                    $arr[$car->company] = $car->companyUser->company->organization;
                }
            }
        }

        return $arr;

    }

    /**
     * return array of carriers match current order
     * @param int $orderId
     * @return array
     */
    public function getAllCarriersMatchOrder(int $orderId)
    {
        $order = Order::findOne($orderId);

        $companies = $this->getAvailableCarriersByDriver($order);
        $types = (!empty($order->type_body)) ? explode(',', $order->type_body) : [];

        //Выбираем типы кузова, подходящие по заказу
        $allTypes = TypeBodySelect::find()
            ->select('type_body')
            ->distinct()
            ->where(['select_type_body' => $types])
            ->column();

        //Выбираем категории ТС, подходящие по заказу
        $allCategories = TsCategorySelect::find()
            ->select('ts_category')
            ->distinct()
            ->where(['select_ts_category' => $order->ts_category])
            ->column();

        $arr = [];

        $query = Car::find()->joinWith('sysCompany sc')
            ->andWhere(['=','company_user.active', CompanyUser::ACTIVE_WORK])
            ->andWhere(['=', 'sc.status', SysCompany::STATE_ACTIVE])
            ->andWhere(['sc.id' => $companies])
            ->andWhere(['<>', 'car.state', DRIVER_STATE_ARCHIVED])
            ->andWhere(['car.is_passport'=> IS_PASSPORT_YES])
            ->andWhere(['car.type_body' => $allTypes])
            ->andWhere(['car.ts_category' => $allCategories]);

        $cars = $query->all();
        foreach ($cars as $car)
        {
            $loads = ArrayHelper::getColumn($car->typeLoads, 'id');
            //проверка типа загрузки
            $canLoad = false;
            foreach ($order->typeLoadData as $load)
            {
                if (in_array($load->id,$loads))
                {
                    $canLoad = true;
                }
            }

            if ($canLoad)
            {
                $arr[$car->company] = $car->companyUser->company->organization;
            }
        }

        return $arr;
    }

    /**
     * Заполняет поля статистики
     */
    private function getStatValuesBasedOnOrders_old()
    {
        $startTime = round(microtime(true) * 1000);
        //TODO лучше дублировать дистанцию и координаты в базу Order - что бы уменьшать выборку
        $orders = Order::find()
            ->andWhere(['>=', 'from_date', (time() - CLOSEST_PERIOD)])
            ->andWhere(['in_statistic' => 1])
            ->andWhere([
                'state' => ORDER_STATE_FINISH
            ])
            ->all();

        $ordersFound = 0;
//        $totalWeight = 0;
//        $totalPrice = 0;
        foreach ($orders as $order) {
            if (!empty($order->distance) && !empty($order->toData) && !empty($order->fromData)) {
                if (count($order->points) >= ($this->points / 2) && count($order->points) <= ($this->points * 3 / 2)) {

                    $orderFrom = $order->fromData;
                    $orderTo = $order->toData;
                    $orderDistance = $order->distance / 1000;
                    if ($orderDistance) {
                        //TODO Поменять в константах CLOSEST_DISTANCE на 0.1 = 10%
                        $delta = CLOSEST_DISTANCE * $orderDistance;
                        if ($this->isDeltaPoint($this->to_lng, $this->to_lat, $orderTo, $delta)
                            && $this->isDeltaPoint($this->from_lng, $this->from_lat, $orderFrom, $delta)) {
                            //Тут оказываемся только когда
                            // заказ Исполнен
                            // погрузка до 100 дней назад
                            // откуда и куда в диапазоне CLOSEST_DISTANCE от длины машрута
                            $orderWeight = $this->getOrderWeight($order->from_date);
                            $orderDistancePrice = $this->getOrderDistancePrice(
                                $order->summary,
                                self::getCategoryMultiple($order->ts_category), self::getTypeBodyMultiple($order->type_body),
                                $order->insurance_price);
                            $this->statTotalPrice += $orderWeight * $orderDistancePrice;
                            $this->statTotalWeight += $orderWeight;
                            $this->littleLog[] = '---------------------------' . $order->id . PHP_EOL;
                            $this->littleLog[] = 'Route: ' . $orderDistance . ' FromD: ' . $this->getDistance($this->from_lat, $this->from_lng, $orderFrom['lat'], $orderFrom['lng']) . ' ToD: ' . $this->getDistance($this->to_lat, $this->to_lng, $orderTo['lat'], $orderTo['lng']);
                            $this->littleLog[] = 'Weight: [' . date('d-m-Y', $order->from_date) . ']' . $orderWeight;
                            $this->littleLog[] = ' price S:' . $order->summary . ' TC:' . self::getCategoryMultiple($order->ts_category) . ' TB:' . self::getTypeBodyMultiple($order->type_body) . ' INS:' . $order->insurance_price . ' = ' . $orderDistancePrice;
                            $ordersFound++;
                        }
                    }
                }

            }
        }
        $this->littleLog[] = 'Find ' . $ordersFound . ' orders';
        $this->littleLog[] = 'Busy  ' . round(microtime(true) * 1000 - $startTime) . ' ms';


    }

    /**
     * Заполняет поля статистики
     * Выполнено через массивы для экономии памяти при циклических обращениях к расчёту
     */

    private function getStatValuesBasedOnOrders()
    {
        $startTime = round(microtime(true) * 1000);
        $longitude_length_list = $this->longitude_length_list;
        $closed_period = 24 * 60 * 60 * Setting::getValue('days_for_statistic', 100);
        $closed_distance = Setting::getValue('percent_square_for_statistic', 20);
        $percent_distance = Setting::getValue('percent_distance_for_statistic', 30);
        $distance_m = $this->distance * 1000;
        $between_distance = (int)$distance_m * $percent_distance / 100;
        $min_distance = (int)$distance_m - $between_distance;
        $max_distance = (int)$distance_m + $between_distance;
        $latitude_length_digr = 111.370;
        $longitude_length_from = $longitude_length_list[(int)$this->from_lat] / 1000;
        $longitude_length_to = $longitude_length_list[(int)$this->to_lat] / 1000;
        $delta_calc = (int)$this->distance * $closed_distance / 100;
        $delta_lat = $delta_calc / $latitude_length_digr;
        $delta_lng_from = $delta_calc / $longitude_length_from;
        $delta_lng_to = $delta_calc / $longitude_length_to;
        $between_lat_from = [($this->from_lat - $delta_lat) / 1000, ($this->from_lat + $delta_lat) / 1000];
        $between_lng_from = [($this->from_lng - $delta_lng_from) / 1000, ($this->from_lng + $delta_lng_from) / 1000];
        $between_lat_to = [($this->to_lat - $delta_lat) / 1000, ($this->to_lat + $delta_lat) / 1000];
        $between_lng_to = [($this->to_lng - $delta_lng_to) / 1000, ($this->to_lng + $delta_lng_to) / 1000];
        $orderTypeBody = $this->type_body ? $this->type_body : [TypeBody::TYPE_TENT];
        $orders = Order::find()
            ->select([
                'id' => 'order.id',
                'distance',
                'from_date',
                'summary',
                'ts_category',
                'insurance_price',
                'type_body',
                'to_lat' => 'to.lat',
                'to_lng' => 'to.lng',
                'from_lat' => 'from.lat',
                'from_lng' => 'from.lng',
                'count_point' => 'COUNT(point.id)'
            ])
            ->leftJoin(['to' => 'address'], 'to.id=`order`.`to`')
            ->leftJoin(['from' => 'address'], 'from.id=`order`.`from`')
            ->innerJoin(['point' => 'order_point'], 'point.id_order=`order`.`id`')
            ->groupBy('`order`.`id`')
            ->andWhere(['>=', 'from_date', (time() - $closed_period)])
            ->andWhere(['in_statistic' => 1])
            ->andWhere([
                'order.state' => ORDER_STATE_FINISH
            ])
            ->andWhere(['between', 'CAST(to.lat/1000 AS CHAR)', $between_lat_to[0], $between_lat_to[1]])
            ->andWhere(['between', 'CAST(to.lng/1000 AS CHAR)', $between_lng_to[0], $between_lng_to[1]])
            ->andWhere(['between', 'CAST(from.lat/1000 AS CHAR)', $between_lat_from[0], $between_lat_from[1]])
            ->andWhere(['between', 'CAST(from.lng/1000 AS CHAR)', $between_lng_from[0], $between_lng_from[1]])
            ->andWhere('ts_category=:ts_category', [':ts_category' => $this->ts_category])
            ->andWhere(['or like', 'type_body', $orderTypeBody])
            ->andWhere('order.distance >= :min AND order.distance <= :max', ['min' => $min_distance, 'max' => $max_distance])
            ->having('count_point/2<=:points', [':points' => $this->points])
            ->andHaving('count_point*3/2>=:points', [':points' => $this->points])
            ->asArray()
            ->all();
        if ($this->points > 3)
            $fixRates = [];
        else {
            $fixRates = FixRate::find()
                ->select([
                    'fix_rate.*',
                    'download_date' => 'file.download_date'
                ])
                ->joinWith('file as file')
                ->joinWith('from as from')
                ->joinWith('to as to')
                ->andWhere(['>=', 'file.download_date', (time() - $closed_period)])
                ->andWhere('category_id=:ts_category', [':ts_category' => $this->ts_category])
                ->andWhere(['IN', 'body_id', $orderTypeBody])
                ->andWhere(['between', 'CAST(to.lat/1000 AS CHAR)', $between_lat_to[0], $between_lat_to[1]])
                ->andWhere(['between', 'CAST(to.lng/1000 AS CHAR)', $between_lng_to[0], $between_lng_to[1]])
                ->andWhere(['between', 'CAST(from.lat/1000 AS CHAR)', $between_lat_from[0], $between_lat_from[1]])
                ->andWhere(['between', 'CAST(from.lng/1000 AS CHAR)', $between_lng_from[0], $between_lng_from[1]])
                ->andWhere('fix_rate.distance >= :min AND fix_rate.distance <= :max', ['min' => $min_distance, 'max' => $max_distance])
                ->asArray()
                ->all();
        }
        $sqlTime = round(microtime(true) * 1000 - $startTime) . ' ms';

        $ordersFound = 0;
        if (count($orders) >= Setting::getValue('min_stat_orders_count', 2)) {
            foreach ($orders as $order) {
                $orderDistance = $order['distance'] / 1000;
                $orderWeight = $this->getOrderWeight($order['from_date']);
                $this->statTotalPrice += $orderWeight * ($order['summary'] - $order['insurance_price']);
                $this->statTotalWeight += $orderWeight;
                $this->littleLog[] = '---------------------------' . $order['id'] . PHP_EOL;
                $this->littleLog[] = 'Route: ' . $orderDistance . ' FromD: ' . $this->getDistance($this->from_lat, $this->from_lng, $order['from_lat'], $order['from_lng']) . ' ToD: ' . $this->getDistance($this->to_lat, $this->to_lng, $order['to_lat'], $order['to_lng']);
                $this->littleLog[] = 'Weight: [' . date('d-m-Y', $order['from_date']) . ']' . $orderWeight;
                $this->littleLog[] = 'KM price S:' . $order['summary'] . ' TC:' . self::getCategoryMultiple($order['ts_category']) . ' TB:' . self::getTypeBodyMultiple($order['type_body']) . ' INS:' . $order['insurance_price'] . ' = ' . ($order['summary'] - $order['insurance_price']);
                $ordersFound++;
            }
        } else $ordersFound = count($orders);

        $fixRatesFound = 0;
        foreach ($fixRates as $fix) {
            $fixWeight = $this->getOrderWeight($fix['download_date']);
            $this->statTotalPrice += $fixWeight * ($fix['price']);
            $this->statTotalWeight += $fixWeight;
            $fixRatesFound++;
        }
        $this->statOrdersFound = $ordersFound;
        $this->statFixRatesFound = $fixRatesFound;
        $this->littleLog[] = 'Find ' . $ordersFound . ' orders';
        $this->littleLog[] = 'Find ' . $fixRatesFound . ' fix_rates';
        $this->littleLog[] = 'Busy  ' . round(microtime(true) * 1000 - $startTime) . ' ms';
        $this->littleLog[] = 'sqlTime  ' . $sqlTime;//


    }
}