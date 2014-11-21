<?php

class hubFrontendCommentsSolutionController extends waJsonController
{
    public function execute()
    {
        $comment_id = waRequest::post('id');
        $model = new hubCommentModel();
        $comment = $model->getById($comment_id);
        if (!$comment) {
            throw new waException(_w("Unknown comment"));
        }

        $topic_model = new hubTopicModel();
        $topic = $topic_model->getById($comment['topic_id']);

        if ($topic['contact_id'] != $this->getUserId() && !$this->getUser()->isAdmin('hub')) {
            throw new waRightsException('Access denied');
        }

        if (!$model->changeSolution($comment_id, waRequest::post("solution"))) {
            $this->errors[] = _w("Error occurs");
        }
    }
}
