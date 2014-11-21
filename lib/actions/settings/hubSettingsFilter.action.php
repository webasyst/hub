<?php

class hubSettingsFilterAction extends waViewAction
{
    public function execute()
    {
        $id = max(0, waRequest::get('id', 0, waRequest::TYPE_INT));
        $user = $this->getUser();
        if ($id) {
            $model = new hubFilterModel();
            $filter = $model->get($id, $user, true);

        } else {
            $filter = array(
                'name'       => '',
                'conditions' => array(),
                'icon'       => 'funnel',
                'contact_id' => -(int)$user->getId(),
            );
        }
        $config = $this->getConfig();
        /**
         * @var hubConfig $config
         */
        $types = hubHelper::getTypes();
        $hubs = $config->getAvailableHubs(hubRightConfig::RIGHT_READ);

        if ($is_admin = $user->isAdmin('hub')) {
            $groups = hubHelper::getGroups();
        }
        $this->view->assign(compact('filter', 'types', 'hubs', 'groups', 'is_admin'));
    }
}
