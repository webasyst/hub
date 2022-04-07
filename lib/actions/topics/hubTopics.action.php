<?php
/**
 * HTML for all topic lists in backend, as well as lazy loading for them.
 */
class hubTopicsAction extends waViewAction
{
    public function execute()
    {
        unset($_GET['_']);

        // Prepare parameters for topics collection
        $hash = waRequest::get('hash');
        $order = waRequest::get('sort', '', 'string');
        $limit = $this->getConfig()->getOption('topics_per_page');
        if (!$limit) {
            $limit = 50;
        }
        $page = waRequest::get('page', 1, 'int');
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $limit;
        $options = array();
        if (waRequest::get('hub_id')) {
            $options['hub_id'] = waRequest::get('hub_id');
        }

        // Fetch topics and counts
        $collection = new hubTopicsCollection($hash, $options);
        $topics = $collection->getTopics('*,contact,tags,hub_color,follow', $offset, $limit);
        $collection_type = $collection->getType();
        $collection_count = $collection->count();
        if (!$collection_count) {
            $pages_count = 1;
        } else {
            $pages_count = ceil((float)$collection_count / $limit);
        }

        // Mark new topics
        $topic_model = new hubTopicModel();
        $topic_model->checkForNew($topics);
        $this->addCategories($topics);
        // Fetch new comments from DB
        if ($topics) {
            foreach ($topics as &$t) {
                $t['new_comments'] = array();
            }
            unset($t);

            $comment_model = new hubCommentModel();
            $comments = $comment_model->getList(
                '*,is_updated,contact,vote,parent,can_delete,my_vote',
                array(
                    'check_rights' => false,
                    'order'        => 'datetime DESC',
                    'where'        => array(
                        'status'       => 'approved',
                        'updated_only' => true,
                        'topic_id'     => array_keys($topics),
                    ),
                )
            );

            $comment_model->checkForNew($comments);
            foreach ($comments as $cm) {
                $topics[$cm['topic_id']]['new_comments'][$cm['id']] = $cm;
            }
        }

        // Mark comments as read in session
        $visited_comments = array();
        $user = wa()->getUser();
        foreach($topics as $topic_id => $t) {
            if (!empty($t['new_comments']) && $t['follow']) {
                foreach($t['new_comments'] as $c) {
                    if (!empty($c['is_updated']) || !empty($c['is_new'])) {
                        $visited_comments[$c['id']] = $c['id'];
                    }
                }
            }
            $topics[$topic_id]['editable'] = hubHelper::checkTopicRights($t, $user);
        }
        wa('hub')->getConfig()->markAsRead(array(), $visited_comments);

        // Prepare template vars depending on collection type (hub, category, tag, etc.)
        $hub_color = null;
        $hub_context = null;
        $collection_topic_types = hubHelper::getTypes();
        switch($collection_type) {
            case 'category':
                $category = $collection->getInfo();
                if (!$order && $category['sorting']) {
                    $order = $category['sorting'];
                }
                if (!$order) {
                    $order = $category['type'] ? 'recent' : 'manual';
                }
                $hub_context = $category['hub'] = hubHelper::getHub($category['hub_id']);
                $this->view->assign('category', $category);

                if (substr($category['conditions'], 0, 8) == 'type_id=') {
                    $collection_topic_types = array();
                }
                break;
            case 'tag':
                $tag = $collection->getInfo();
                $hub_context = $tag['hub'] = hubHelper::getHub($tag['hub_id']);
                $this->view->assign('tag', $tag);
                break;
            case 'search':
                $this->view->assign('query', is_array($collection->getInfo()) ? '' : $collection->getInfo());
                break;
            case 'filter':
                $filter = $collection->getInfo();
                $this->view->assign('filter', $filter);
                if (!empty($filter['conditions']['types'])) {
                    $collection_topic_types = array_intersect_key($collection_topic_types, $filter['conditions']['types']);
                }
                break;
            case 'hub':
                $hub = $collection->getInfo();
                $hub += hubHelper::getHub($hub['id']);
                $this->view->assign('hub', $hub);
                $tag_model = new hubTagModel();
                $this->view->assign('tags', $tag_model->getCloud($hub['id']));
                $hub_context = $hub;
                break;
        }
        if (!$order) {
            $order = 'updated';
        }

        $hub_full_access = false;
        $is_admin = wa()->getUser()->isAdmin('hub');

        // $hub_context contains hub info if all topics in current view belong to the same hub
        // (e.g. we know this for sure when we view a category)
        if (!$hub_context && !empty($options['hub_id'])) {
            $hub_context = hubHelper::getHub($options['hub_id']);
        }
        if ($hub_context) {
            if ($collection_topic_types) {
                $hub_types_model = new hubHubTypesModel();
                $collection_topic_types = array_intersect_key($collection_topic_types, $hub_types_model->getTypes($hub_context['id']));
            }

            $hub_context['urls'] = array();
            $hub_color = ifset($hub_context['params']['color']);
            $hub_full_access = $is_admin || wa()->getUser()->getRights('hub.'.$hub_context['id']);
            foreach(hubHelper::getUrls($hub_context['id']) as $r) {
                $hub_context['urls'][] = $r['url'];
            }
        }

        // No reason to show topic type filter if there's only one topic type
        if (count($collection_topic_types) <= 1) {
            $collection_topic_types = array();
        }

        $hubs_model = new hubHubModel();
        $this->view->assign(array(
            'hub_color' => $hub_color,
            'hub_context' => $hub_context,
            'hubs' => $hubs_model->getAll('id'),

            'types' => hubHelper::getTypes(),
            'collection_topic_types' => $collection_topic_types,

            'page' => $page,
            'order' => $order,
            'topics' => $topics,
            'pages_count' => $pages_count,
            'loaded_count' => $offset + count($topics),
            'topics_count' => $collection_count,
            'title' => $collection->getTitle(),
            'type' => $collection_type,
            'hash' => $hash,

            'is_admin' => $is_admin,
            'hub_full_access' => $hub_full_access,
            'current_author' => hubHelper::getAuthor($this->getUserId()),
        ));
    }

    /**
     * @param array $topics
     * @return void
     * @throws waException
     */
    protected function addCategories(&$topics)
    {
        $topic_categories_model = new hubTopicCategoriesModel();
        $category_model = new hubCategoryModel();
        $topic_ids = array_keys($topics);
        $category_ids = $topic_categories_model->getByField('topic_id', $topic_ids, 'category_id');
        $categories = $category_model->getById(array_keys($category_ids));
        foreach ($category_ids as $category_id => $category_params) {
            if (isset($topics[$category_params['topic_id']]) && isset($categories[$category_id])) {
                $topics[$category_params['topic_id']]['categories'][$category_id] = $categories[$category_id];
            }
        }
    }
}
