<?php
/**
 * Created by PhpStorm.
 * User: jonni
 * Date: 19.09.18
 * Time: 10:57
 */

namespace app\modules\client\controllers;


use app\component\web\UserGlobalController;
use app\models\CompanyUser;
use app\models\Invite;
use app\models\Role;
use app\modules\client\forms\InviteForm;
use app\modules\client\forms\search\InviteSearch;
use app\modules\client\forms\search\WorkerSearch;
use app\modules\client\forms\WorkerForm;
use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

class WorkerController extends UserGlobalController
{
    public $layout = "admin";


    /**
     * Ограничиваем доступ всем, кроме админа
     * @param $action
     * @return bool
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $companyUser = IDENTITY()->currentUserCompany;
            if (!is_object($companyUser) || $companyUser->role_id != Role::ROLE_ADMIN) {
                $this->redirect('/broker/setting/index');
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Список работников компании
     * @return mixed
     */
    public function actionIndex()
    {
        $companyUser = IDENTITY()->currentUserCompany;
        $searchModel = new WorkerSearch([
            "company" => $companyUser->sys_company_id
        ]);

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);

    }

    /**
     * Создание Приглашения
     * @return mixed
     */
    public function actionInviteCreate()
    {
        $companyUser = IDENTITY()->currentUserCompany;
        $model = new InviteForm();
        $model->company_user_id = $companyUser->id;
        if ($model->load(Yii::$app->request->post())) {
            if ($model->validate()) {
                $model->saveInvite();
                if (!$model->hasErrors()) {
                    return $this->redirect(['/broker/worker/invites']);
                }
            }
        }
        return $this->render('invite-create', ['model' => $model]);
    }

    /**
     * Список Приглашений
     * @return mixed
     */
    public function actionInvites()
    {
        $companyUser = IDENTITY()->currentUserCompany;
        $searchModel = new InviteSearch([
            "company_user" => $companyUser->getCompanyUserIds()
        ]);

        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('invite-index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Отмена Приглашения
     * @param $id
     * @return mixed
     */
    public function actionInviteCancel($id)
    {
        $invite = Invite::findOne($id);
        if (is_object($invite)) {
            $invite->status = Invite::STATUS_REMOVE;
            $invite->save(false);
        }
        return $this->redirect(['/broker/worker/invites']);
    }

    /**
     * Отправка письма приглашённому (повторно)
     * @param $id
     * @return mixed
     */
    public function actionInviteSend($id)
    {
        $invite = Invite::findOne($id);
        if (is_object($invite)) {
            $invite->generateMail();
        }
        return $this->redirect(['/broker/worker/invites']);
    }

    /**
     * Редактирование Пользователя
     * @param $id
     * @return mixed
     */
    public function actionWorkerUpdate($id)
    {
        $user = $this->findModel($id);
        $model = new WorkerForm([
            'id'=>$id,
            'role_id'=>$user->role_id,
            'active'=>$user->active
        ]);
        if (Yii::$app->request->isPost) {
            if ($model->load(Yii::$app->request->post())) {
                if ($model->validate()) {
                    $model->change();
                    if (!$model->hasErrors()) {
                        return $this->redirect(['/broker/worker/index']);
                    }
                }
            }
        }

        return $this->render('worker-update',
            ['model' => $model, 'user'=>$user]
        );
    }

    /**
     * Получение объекта пользователь-компания
     * @param $id
     * @return mixed
     */
    protected  function findModel($id)
    {
        $model = CompanyUser::findOne($id);
        if ($model !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Yii::t("error", 'Пользователь не найден'));
        }
    }
}