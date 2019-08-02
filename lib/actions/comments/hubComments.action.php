<?php

class hubCommentsAction extends waViewAction
{
    public function execute()
    {
        $offset = waRequest::get('offset', 0, waRequest::TYPE_INT);
        $comments_per_page = $this->getConfig()->getOption('comments_per_page');
        $contact_id = waRequest::request('contact_id', null, 'int');
        $where = array();
        if ($contact_id) {
            $where['contact_id'] = $contact_id;
        }

        $comment_model = new hubCommentModel();
        $comments = $comment_model->getList('*,is_updated,contact,vote,topic,parent,can_delete,my_vote', array(
            'offset' => $offset,
            'limit'  => $comments_per_page,
            'order'  => 'datetime DESC',
            'where'  => $where,
        ), $total_count);

        // Mark comments as read in session
        $visited_comments = array();

        $is_admin = wa()->getUser()->isAdmin('hub');
        foreach($comments as &$c) {

            $current_user_is_author = $c['contact_id'] == wa()->getUser()->getId();
            $edit_time_is_relevant = time() - strtotime($c['datetime']) < 15 * 60;
            $c['editable'] = $is_admin || ($current_user_is_author && $edit_time_is_relevant);

            if (!empty($c['is_updated']) || !empty($c['is_new'])) {
                $visited_comments[$c['id']] = $c['id'];
            }
        }
        unset($c);
        wa('hub')->getConfig()->markAsRead(array(), $visited_comments);

        $this->view->assign(array(
            'contact_id'       => $contact_id,
            'comments'         => $comments,
            'total_count'      => $total_count,
            'count'            => count($comments),
            'offset'           => $offset,
            'current_author'   => hubHelper::getAuthor($this->getUserId()),
            'sidebar_counters' => array(
                'new' => $comment_model->countNew(!$offset)
            )
        ));
    }
}
