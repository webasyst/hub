<?php

class hubTopicsAction extends waViewAction
{
    public function execute()
    {
        $hash = waRequest::get('hash');
        $options = array();
        if (waRequest::get('hub_id')) {
            $options['hub_id'] = waRequest::get('hub_id');
        }
        $c = new hubTopicsCollection($hash, $options);

        $order = waRequest::get('sort', '', 'string');

        if (waRequest::get('_')) {
            unset($_GET['_']);
        }

        $limit = $this->getConfig()->getOption('topics_per_page');
        if (!$limit) {
            $limit = 50;
        }
        $page = waRequest::get('page', 1, 'int');
        if ($page < 1) {
            $page = 1;
        }
        $this->view->assign('page', $page);
        $offset = ($page - 1) * $limit;

        $topics = $c->getTopics('*,contact,tags,hub_color,follow', $offset, $limit);
        $count = $c->count();
        $this->view->assign('loaded_count', $offset + count($topics));
        $this->view->assign('topics_count', $count);

        if (!$count) {
            $pages_count = 1;
        } else {
            $pages_count = ceil((float)$count / $limit);
        }
        $this->view->assign('pages_count', $pages_count);

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

            $this->view->assign('current_author', hubHelper::getAuthor($this->getUserId()));
        }

        $m = new hubTopicModel();
        $m->checkForNew($topics);
        $this->view->assign('topics', $topics);
        $this->view->assign('type', $c->getType());
        $this->view->assign('title', $c->getTitle());
        $this->view->assign('hash', $hash);

        $hub_color = null;
        $hub_context = null;
        if ($c->getType() == 'category') {
            $category = $c->getInfo();
            if (!$order && $category['sorting']) {
                $order = $category['sorting'];
            }
            if (!$order) {
                $order = 'manual';
            }
            $hub_context = $category['hub'] = hubHelper::getHub($category['hub_id']);
            $this->view->assign('category', $category);
        } elseif ($c->getType() == 'tag') {
            $tag = $c->getInfo();
            $hub_context = $tag['hub'] = hubHelper::getHub($tag['hub_id']);
            $this->view->assign('tag', $tag);
        } elseif ($c->getType() == 'search') {
            $this->view->assign('query', is_array($c->getInfo()) ? '' : $c->getInfo());
        } elseif ($c->getType() == 'filter') {
            $this->view->assign('filter', $c->getInfo());
        } elseif ($c->getType() == 'hub') {
            $hub = $c->getInfo();
            $hub += hubHelper::getHub($hub['id']);
            $this->view->assign('hub', $hub);
            $tag_model = new hubTagModel();
            $this->view->assign('tags', $tag_model->getCloud($hub['id']));

            $hub_context = $hub;
        }

        if (!$order) {
            $order = 'updated';
        }

        $is_admin = wa()->getUser()->isAdmin('hub');
        $hub_full_access = false;

        if (!$hub_context && !empty($options['hub_id'])) {
            $hub_context = hubHelper::getHub($options['hub_id']);
        }
        if ($hub_context) {
            $hub_full_access = $is_admin || wa()->getUser()->getRights('hub.'.$hub_context['id']);
            $hub_context['urls'] = array();
            $hub_color = ifset($hub_context['params']['color']);
            foreach(hubHelper::getUrls($hub_context['id']) as $r) {
                $hub_context['urls'][] = $r['url'];
            }
        }

        $this->view->assign('hub_context', $hub_context);
        $this->view->assign('hub_color', $hub_color);

        $this->view->assign('order', $order);

        $hubs_model = new hubHubModel();
        $this->view->assign('hubs', $hubs_model->getAll('id'));

        $this->view->assign('types', hubHelper::getTypes());

        $this->view->assign('is_admin', $is_admin);
        $this->view->assign('hub_full_access', $hub_full_access);
    }
}
