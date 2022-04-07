<?php
/**
 * Default backend action
 * Read more about actions and controllers at
 * http://www.webasyst.com/framework/docs/dev/controllers/
 *
 * Processes requests in backend at URL hub/
 * Read more about request routing in backend at
 * http://www.webasyst.com/framework/docs/dev/backend-routing/
 *
 * Экшен бекенда по умолчанию
 * Подробнее о экшенах и контроллерах:
 * http://www.webasyst.com/ru/framework/docs/dev/controllers/
 *
 * Доступен в бэкенде по урлу hub/
 * Подробнее о маршрутизации в бэкенде:
 * http://www.webasyst.com/ru/framework/docs/dev/backend-routing/
 */
class hubBackendAction extends waViewAction
{
    /**
     * This is the action's entry point
     * Here all business logic should be implemented and data for templates should be prepared
     *
     * Это "входная точка" экшена
     * Здесь должна быть реализована вся бизнес-логика и подготовлены данные для шаблона
     */
    public function execute()
    {
        $app_settings_model = new waAppSettingsModel();
        if ($app_settings_model->get('hub', 'welcome')) {
            if (waRequest::get('skipwelcome')) {
                $app_settings_model->del('hub', 'welcome');
            } else {
                $this->redirect(wa()->getConfig()->getBackendUrl(true).'hub/?action=welcome');
            }
        }


        $config = wa()->getConfig();
        /**
         * @var hubConfig $config
         */
        $hubs = $config->getAvailableHubs(hubRightConfig::RIGHT_READ);
        foreach($hubs as &$h) {
            $h['urls'] = array();
            foreach(hubHelper::getUrls($h['id']) as $r) {
                $h['urls'][] = $r['url'];
            }
        }
        unset($h);
        $this->view->assign('hubs', $hubs);

        $categories = $this->getCategories();
        $tag_model = new hubTagModel();
        $tags = array();
        foreach ($hubs as $h) {
            if (!isset($categories[$h['id']])) {
                $categories[$h['id']] = array();
            }
            $tags[$h['id']] = $tag_model->getCloud($h['id']);
        }
        $this->view->assign('categories', $categories);
        $this->view->assign('tags', $tags);

        $filter_model = new hubFilterModel();
        $this->view->assign('filters', $filter_model->getFilters());

        $comment_model = new hubCommentModel();
        $this->view->assign('count_comments', array(
            'all' => $comment_model->countAll(),
            'new' => $comment_model->countNew(true)
        ));

        $author_model = new hubAuthorModel();
        $this->view->assign('authors_count', $author_model->countAuthors());

        $topic_model = new hubTopicModel();
        $this->view->assign('count_topics', array(
            'all' => $topic_model->countAll(),
            'new' => $topic_model->countNew()
        ));

        $this->view->assign('types', hubHelper::getTypes());

        $hub_following_model = new hubFollowingModel();
        $this->view->assign('following_comments_count', $hub_following_model->countNewComments());
        $this->view->assign('following_count', $hub_following_model->countByField('contact_id', $this->getUserId()));

        $d = new hubTopicsCollection('drafts');
        $this->view->assign('drafts', $d->getTopics('*,contact'));

        $this->view->assign('is_admin', wa()->getUser()->isAdmin('hub'));
        $this->view->assign('can_create_topics', $config->getAvailableHubs(hubRightConfig::RIGHT_READ_WRITE));

        if (waRequest::request('sidebar')) {
            $this->setTemplate('backend/include.sidebar.html', true);
        }

        $this->view->assign('backend_event', $this->backendEvent());
    }

    protected function backendEvent()
    {
        /**
         * @return array[string]array $return[%plugin_id%]
         * @return array[string][string]string $return[%plugin_id%]['head']
         */
        return wa('hub')->event('backend');
    }

    public function getCategories()
    {
        $model = new hubCategoryModel();
        $categories = array();
        $cats_flat = $model->getFullTree();
        $model->checkForNew($cats_flat);
        foreach ($cats_flat as $item) {
            $categories[$item['hub_id']][$item['id']] = $item;
        }

        return $categories;
    }
}
