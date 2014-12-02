<?php

class hubSettingsMoveController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::request('id', 0, 'int');
        $type = waRequest::request('type', '', 'string');
        $prev_id = waRequest::request('prev_id', 0, 'int');
        if (!$id || !$type) {
            return;
        }

        if ($type == 'hub') {
            $hub_model = new hubHubModel();
            $hub_model->sortMove($id, $prev_id);
        } else if ($type == 'type') {
            $type_model = new hubTypeModel();
            $type_model->sortMove($id, $prev_id);
        }
    }
}

