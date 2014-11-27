<?php

class hubSettingsAction extends waViewAction
{

    protected $hubs = array();
    protected $types = array();

    public function __construct($params = null)
    {
        parent::__construct($params);
        $is_admin = $this->getUser()->isAdmin($this->getAppId());
        $this->view->assign('is_admin', $is_admin);
        if ($is_admin) {
            $this->types = hubHelper::getTypes();
            $this->view->assign('types', $this->types);

            $this->hubs = wa('hub')->getConfig()->getAvailableHubs();
            foreach($this->hubs as &$h) {
                $h['urls'] = array();
                foreach(hubHelper::getUrls($h['id']) as $r) {
                    $h['urls'][] = $r['url'];
                }
            }
            unset($h);
            $this->view->assign('hubs', $this->hubs);
        }
    }

    public function execute()
    {
        $settings = array(
            'email_following' => !!$this->getUser()->getSettings('hub', 'email_following'),
            'type_items_count' => array(),
        );
        foreach (explode(',', $this->getUser()->getSettings('hub', 'type_items_count')) as $v) {
            $settings['type_items_count'][$v] = true;
        }

        $settings['gravatar'] = $this->getUser()->getSettings('hub', 'gravatar');
        $settings['gravatar'] = $this->getUser()->getSettings('hub', 'gravatar');

        $app_settings_model = new waAppSettingsModel();
        $global_settings = $app_settings_model->get('hub');

        if (waRequest::getMethod() == 'post') {
            $this->save($settings, $global_settings);
            $this->view->assign('saved', 1);
        }

        $this->view->assign('is_admin', wa()->getUser()->isAdmin('hub'));
        $this->view->assign('settings', $settings);
        $this->view->assign('global_settings', $global_settings);
    }

    protected function save(&$settings, &$global_settings)
    {
        // Save user settings
        $settings['type_items_count'] = waRequest::post('type_items_count', array(), 'array');
        $user = wa()->getUser();
        $user->setSettings('hub', 'type_items_count', implode(',', $settings['type_items_count']));
        $settings['type_items_count'] = array_fill_keys($settings['type_items_count'], true);

        $settings['email_following'] = !!waRequest::request('email_following');
        $user->setSettings('hub', 'email_following', $settings['email_following']);

        // Save global settings
        if (wa()->getUser()->isAdmin('hub')) {
            $defaults = array(
                'gravatar'         => 0,
                'gravatar_default' => 'retro',
            );
            $app_settings_model = new waAppSettingsModel();
            $global_settings = waRequest::post('global_settings', array(), 'array') + $global_settings + $defaults;
            $global_settings = array_intersect_key($global_settings, $defaults);
            foreach ($global_settings as $k => $v) {
                $app_settings_model->set('hub', $k, $v);
            }
        }

    }
}
