<?php

class hubFrontendTopicFollowController extends waJsonController
{
    public function execute()
    {
        $follow = waRequest::post('follow');
        $topic_id = waRequest::post('topic_id');

        $following_model = new hubFollowingModel();
        $contact_id = $this->getUser()->getId();

        if ($follow) {
            $following_model->insert(array(
                'topic_id' => $topic_id,
                'contact_id' => $contact_id,
                'datetime' => date('Y-m-d H:i:s')
            ), 2);
        } else {
            $following_model->deleteByField(array(
                'topic_id' => $topic_id,
                'contact_id' => $contact_id,
            ));
        }

        $topic_model = new hubTopicModel();
        $count = $following_model->countByField('topic_id', $topic_id);
        $this->response['count'] = $count;
        $this->response['followers'] = _w('%s follower', '%s followers', $count);
        $topic_model->updateById($topic_id, array('followers_count' => $count));
    }
}
