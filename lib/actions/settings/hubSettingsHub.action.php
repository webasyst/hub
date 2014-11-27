<?php

class hubSettingsHubAction extends hubSettingsAction
{
    public function execute()
    {
        $hub_id = waRequest::get('id');
        $hub = ifset($this->hubs[$hub_id], array('id' => 0, 'status' => 1));
        $topic_model = new hubTopicModel();
        $hub['topics_count'] = $topic_model->countByField('hub_id', $hub_id);
        $this->view->assign('hub', $hub);


        $hub_types_model = new hubHubTypesModel();
        $this->view->assign('hub_types', $hub_types_model->getTypeIds($hub_id));

        $hub_params_model = new hubHubParamsModel();
        $this->view->assign('hub_params', $hub_params_model->getParams($hub_id));

        $staff_model = new hubStaffModel();
        $this->view->assign('staff', $staff_model->getStaff($hub_id));

        $frontend_urls = hubHelper::getUrls(intval($hub_id));

        $this->view->assign('routes', $frontend_urls);
        $this->view->assign('domains', wa()->getRouting()->getDomains());
    }
}
