<?php

class hubSettingsHubSaveController extends waJsonController
{
    public function execute()
    {
        $hub_id = waRequest::get('id', 0, waRequest::TYPE_INT);
        $data = waRequest::post('hub');

        $hub_model = new hubHubModel();
        if (!$hub_id) {
            $hub_id = $hub_model->add($data);
            $this->response['hash'] = sprintf('/settings/hub/%d/', $hub_id);
            $this->response['message'] = _w('Hub added');
        } else {
            $hub_model->updateById($hub_id, $data);
            $this->response['message'] = _w('Hub settings saved');
            $this->response['name'] = $data['name'];
            $this->response['id'] = $hub_id;
        }


        //Hub types
        $type_ids = waRequest::post('type_id');
        $hub_types_model = new hubHubTypesModel();
        $hub_types_model->setTypes($hub_id, $type_ids);


        //Hub params
        $hub_params = (array)waRequest::post('hub_params');
        if (empty($data['status'])) {
            $hub_params['kudos'] = 0;
        } else {
            $hub_params += array(
                'kudos' => 0,
            );
            if (!empty($hub_params['kudos'])) {
                $hub_params += array(
                    'kudos_per_topic'   => 1,
                    'kudos_per_comment' => 2,
                    'kudos_per_answer'  => 3,
                );
            }
        }

        $hub_params_model = new hubHubParamsModel();
        foreach ($hub_params as $name => $value) {
            $hub_params_model->insert(compact('hub_id', 'name', 'value'), 1);
        }


        //Hub staff
        $staff_model = new hubStaffModel();
        if (empty($data['status'])) {
            $staff = array();
        } else {
            $staff = (array)waRequest::post('staff');
            $sort = 0;
            foreach ($staff as $contact_id => $staff_member) {
                if ($contact_id > 0) {
                    $staff_member = array_merge(
                        $staff_member,
                        compact('contact_id', 'hub_id', 'sort')
                    );
                    $staff_model->insert($staff_member, 1);
                    ++$sort;
                } else {
                    unset($staff[$contact_id]);
                }
            }
        }
        $staff_model->deleteStaff($hub_id, array_keys($staff));
    }
}
