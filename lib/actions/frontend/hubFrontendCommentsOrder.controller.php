<?php

class hubFrontendCommentsOrderController extends waJsonController
{
    public function execute()
    {
        $topic_id = waRequest::post('topic_id');
        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($topic_id);
        if (!$topic) {
            throw new waException(_w('Topic not found'));
        }
        
        if (waRequest::post('order') == 'votes') {
            $order = 'solution DESC, votes_sum DESC';
        } else {
            $order = 'datetime DESC';
        }
        
        
        $comment_model = new hubCommentModel();
        $comment_ids = array_keys($comment_model->select('id')->where(
                'topic_id = i:topic_id AND parent_id = 0', 
                array('topic_id' => $topic_id)
        )->order($order)->fetchAll('id'));
        
        $this->response['comment_ids'] = $comment_ids;
        
    }
}
