<?php

class hubSettingsFilterSaveController extends waJsonController
{
    public function execute()
    {
        $model = new hubFilterModel();
        $id = max(0, waRequest::post('id', 0, waRequest::TYPE_INT));
        if (!$id) {
            $id = $model->add($this->getData());
        } else {
            $model->get($id, $this->getUser(), true);
            $model->update($id, $this->getData());
        }

        if ($id) {
            $this->response = $model->getById($id);
            $this->response['icon_html'] = hubHelper::getIcon($this->response['icon']);
        }
    }

    private function getData()
    {
        $data = (array)waRequest::post('filter');
        $data += array(
            'contact_id' => -$this->getUserId(),
            'conditions' => array(),
        );

        $tags = waRequest::post('tags', '', 'string');
        if ($tags) {
            $data['conditions']['tag_name'] = array_keys(array_flip(explode(',', $tags)));
        }

        return $data;
    }
}
