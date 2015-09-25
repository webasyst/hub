<?php

class hubFrontendAuthorRepliesAction extends hubFrontendAction
{
    public function execute()
    {
        $id = (int) waRequest::param('id');

        $author = hubHelper::getAuthor($id);
        $author['name'] = htmlspecialchars($author['name']);
        $author['contact_id'] = $id;

        wa()->getResponse()->setTitle(sprintf_wp('%s’s replies', $author['name']));

        $page = waRequest::get('page', 1, 'int');
        $page >= 1 || $page = 1;
        $limit = $this->getConfig()->getOption('comments_per_page');
        $limit || $limit = 20;
        $offset = ($page - 1) * $limit;

        $comment_model = new hubCommentModel();
        $comments = $comment_model->getList('*,author,vote,my_vote,topic', array(
            'offset' => $offset,
            'limit' => $limit,
            'order' => 'id DESC',
            'where' => array(
                'contact_id' => $id,
                'status' => hubCommentModel::STATUS_PUBLISHED,
                'hub_id' => waRequest::param('hub_id'),
            )
        ), $count);

        $pages_count = max(1, ceil(($count - 1) / $limit) + 1);

        $this->view->assign(array(
            'author_replies' => true,
            'pages_count' => $pages_count,
            'comments' => $comments,
            'author' => $author,
        ));

        /**
         * @event frontend_author
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_author', wa()->event('frontend_author', $author));

        $this->setThemeTemplate('author.html');
    }
}

