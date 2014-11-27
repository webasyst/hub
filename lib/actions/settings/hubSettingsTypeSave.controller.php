<?php

class hubSettingsTypeSaveController extends waJsonController
{
    public function execute()
    {
        $model = new hubTypeModel();
        $type = (array)waRequest::post('type');
        $type_id = max(0, waRequest::get('id', 0, waRequest::TYPE_INT));
        $type['settings'] = ifset($type['settings'], array());
        if (!isset($type['settings']['voting']['+']) && isset($type['settings']['voting']['-'])) {
            unset($type['settings']['voting']);
        }
        $type['settings'] = json_encode($type['settings']);
        if ($type_id) {
            $model->updateById($type_id, $type);
            $this->response['name'] = $type['name'];
            $this->response['glyph'] = $type['glyph'];
            $this->response['glyph_html'] = hubHelper::getGlyph($type['glyph'], 16, true);
            $this->response['id'] = $type_id;
        } else {
            $type_id = $model->insert($type);
            $hub_params_model = new hubHubParamsModel();
            $hub_types_model = new hubHubTypesModel();
            foreach ($hub_params_model->getByField(array('name' => 'all_types', 'value' => '1'), $hub_params_model->getTableId()) as $hub_id => $row) {
                $hub_types_model->insert(compact('hub_id', 'type_id'));
            }

            $this->response['hash'] = sprintf('/settings/type/%d/', $type_id);
        }
    }
}
