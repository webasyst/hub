<?php

class hubCommentsAction extends waViewAction
{
    public function execute()
    {
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $comments_per_page = $this->getConfig()->getOption('comments_per_page');

        $comment_model = new hubCommentModel();
        $comments = $comment_model->getList('*,is_updated,contact,vote,topic,parent,can_delete,my_vote', array(
            'offset' => $offset,
            'limit' => $comments_per_page,
            'order' => 'datetime DESC'
        ), $total_count);

        $this->view->assign(array(
            'comments' => $comments,
            'total_count' => $total_count,
            'count' => count($comments),
            'offset' => $offset,
            'current_author' => hubHelper::getAuthor($this->getUserId()),
            'sidebar_counters' => array(
                'new' => $comment_model->countNew(!$offset)
            )
        ));
    }
}
