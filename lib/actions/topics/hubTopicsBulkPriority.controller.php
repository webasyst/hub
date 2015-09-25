<?php
class hubTopicsBulkPriorityController extends waJsonController
{
    public function execute()
    {

        $ids = waRequest::post('topic_ids');
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        if ( ( $ids = array_filter(array_map('intval', array_map('trim', $ids))))) {
            $topics = hubHelper::checkTopicRights($ids, $this->getUser());
            if ( ( $topics = array_filter($topics))) {
                $ids = array_keys($topics);
                $topic_model = new hubTopicModel();
                $topic_model->updateById($ids, array(
                    'priority' => waRequest::request('priority', 0, 'int'),
                ));
            }
        }
    }
}
