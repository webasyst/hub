<?php

class hubTopicsDeleteController extends waJsonController
{
    public function execute()
    {
        if ($id = max(0, waRequest::post('id', 0, waRequest::TYPE_INT))) {
            $ids = array($id);
        } else {
            $ids = waRequest::post('ids', array(), waRequest::TYPE_ARRAY_INT);
        }
        $this->response['deleted'] = array();
        if ($ids) {
            $model = new hubTopicModel();
            $user = wa()->getUser();
            foreach ($ids as $id) {
                $topic = $model->getById($id);
                if ($topic) {
                    $access_level = $user->getRights('hub', 'hub.'.$topic['hub_id']);
                    if (
                        //Full rights for hub
                        ($access_level >= hubRightConfig::RIGHT_FULL)
                        ||
                        //Topic owner and has write rights
                        (($access_level >= hubRightConfig::RIGHT_READ_WRITE) && ($topic['contact_id'] == wa()->getUser()->getId()))
                    ) {
                        $model->deleteById($id);
                        $this->response['deleted'][] = $id;
                    }
                }
            }
        }
    }
}
