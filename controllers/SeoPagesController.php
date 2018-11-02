<?php
/**
 * Created by PhpStorm.
 * User: jonni
 * Date: 11.06.18
 * Time: 10:29
 */

namespace app\modules\admin\controllers;


use app\models\SeoPagePriority;
use app\modules\admin\forms\search\SearchSeoPage;
use app\modules\admin\forms\search\SearchSeoPagePriority;
use app\modules\admin\forms\SeoPages;
use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

class SeoPagesController extends AdminController
{

    public $layout ="admin";



    public function actionIndex()
    {
        $searchModel = new SearchSeoPage();

        $params = REQUEST()->getQueryParams();

        $dataProvider = $searchModel->search($params);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new SeoPages();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['index']);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = SeoPages::findOne($id);

        if(!$model){
            throw new NotFoundHttpException('Запрашиваемый ресурс не найден');
        }


        if ($model->load(Yii::$app->request->post())) {
            $model->save();
            return $this->redirect(['index']);
        }

        return $this->render('update', [
            'model' => $model->adoptFields(),
        ]);
    }

    /**
     * @return mixed
     */
    public function actionPagePriority()
    {
        $searchModel = new SearchSeoPagePriority();

        $params = REQUEST()->getQueryParams();

        $dataProvider = $searchModel->search($params);

        return $this->render('priority-index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @return mixed
     */
    public function actionCreatePriority()
    {
        $model = new SeoPagePriority();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['page-priority']);
        } else {
            return $this->render('priority-create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public function actionUpdatePriority($id)
    {
        $model = SeoPagePriority::findOne($id);

        if(!$model){
            throw new NotFoundHttpException('Запрашиваемый ресурс не найден');
        }


        if ($model->load(Yii::$app->request->post())) {
            $model->save();
            return $this->redirect(['page-priority']);
        }

        return $this->render('priority-update', [
            'model' => $model,
        ]);
    }
}