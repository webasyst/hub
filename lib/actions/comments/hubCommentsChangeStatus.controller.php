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

                    // When deleting a solution, remove badges from comment and topic
                    if ($comment['solution'] && $status == hubCommentModel::STATUS_DELETED) {
                        $comment_model->updateById($comment_id, array(
                            'solution' => 0,
                        ));
                        if ($topic['badge'] == 'answered') {
                            $other_solutions_exist = $comment_model->countByField(array(
                                'topic_id' => $topic['id'],
                                'solution' => 1,
                            ));
                            if (!$other_solutions_exist) {
                                $topic_model->updateById($topic['id'], array(
                                    'badge' => null,
                                ));
                            }
                        }
                    }
                }
            }
        }
    }
}
