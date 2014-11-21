<?php

class hubCommentsChangeStatusController extends waJsonController
{
    public function execute()
    {
        $comment_id = waRequest::post('comment_id', null, waRequest::TYPE_INT);
        if (!$comment_id) {
            throw new waException("Unknown comment id");
        }

        // Check access rights
        $comment_model = new hubCommentModel();
        $comment = $comment_model->getById($comment_id);
        if ($comment) {
            $topic_model = new hubTopicModel();
            $topic = $topic_model->getById($comment['topic_id']);

            if (!$topic) {
                // Comment without a topic? Nobody seen nothing.
                $comment_model->deleteById($comment_id);
            } else {
                $access_level = wa()->getUser()->getRights('hub', 'hub.'.$topic['hub_id']);
                if ($access_level >= hubRightConfig::RIGHT_FULL || $comment['contact_id'] == wa()->getUser()->getId()) {
                    $status = waRequest::post('status', '', waRequest::TYPE_STRING_TRIM);
                    if (
                        $status == hubCommentModel::STATUS_DELETED ||
                        $status == hubCommentModel::STATUS_PUBLISHED
                    ) {
                        $comment_model->changeStatus($comment_id, $status);
                    }
                }
            }
        }
    }
}
