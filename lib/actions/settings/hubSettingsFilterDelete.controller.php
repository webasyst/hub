<?php

class hubSettingsFilterDeleteController extends waJsonController
{
    public function execute()
    {
        $id = (int)waRequest::post('id');
        $model = new hubFilterModel();
        try {
            $filter = $model->get($id, $this->getUser(), true);
            $model->deleteById($filter['id']);
        } catch (Exception $ex) {
            $this->errors = array($ex->getMessage());
        }
    }
}
