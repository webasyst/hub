<?php

class hubCommentsWidget extends waWidget
{
    public function defaultAction()
    {
        $comment_model = new hubCommentModel();
        $comments = $comment_model->getList('*,is_updated,contact,vote,topic,parent,can_delete,my_vote', array(
            'limit' => wa('hub')->getConfig()->getOption('comments_per_page'),
            'order' => 'datetime DESC',
            'offset' => 0,
            'limit' => 6,
        ));

        $this->display(array(
            'comments' => $comments,
        ));
    }
}
