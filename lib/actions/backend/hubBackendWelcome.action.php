<?php

class hubBackendWelcomeAction extends waViewAction
{
    /**
     * @var array
     */
    private $types;
    private $hub_params = array();
    private $translate = array();

    private function loadConfig()
    {
        $welcome_path = $this->getConfig()->getConfigPath('data/welcome/', false);
        $path = $welcome_path.'welcome.php';

        $locale_path = $welcome_path.'locale/'.$this->getUser()->getLocale().'.php';
        if (file_exists($locale_path)) {
            $this->translate = include($locale_path);
            if (!is_array($this->translate)) {
                $this->translate = array();
            }
        }

        if (file_exists($path)) {
            $data = include($path);
            $this->types = ifset($data['topic_types'], array());
            foreach ($this->types as &$type) {
                $type['name'] = ifempty($this->translate[$type['name']], $type['name']);
                $type['category_name'] = ifempty($this->translate[$type['category_name']], $type['category_name']);
                $type['description'] = ifempty($this->translate[$type['description']], $type['description']);
                unset($type);
            }

            $this->hub_params = ifset($data['hub_params'], array());
        }
    }

    public function execute()
    {
        $this->loadConfig();
        if ($types = waRequest::post('types')) {
            if ($hub_id = $this->setup($types)) {
                $this->updateRouting($hub_id);
            }
            $app_settings_model = new waAppSettingsModel();

            $app_settings_model->del('hub', 'welcome');
            $this->redirect($this->getConfig()->getBackendUrl(true).$this->getAppId().'/#/settings/hub/'.$hub_id.'/');
        } else {
            $this->view->assign('types', $this->types);
        }

    }

    private function updateRouting($hub_id)
    {
        $path = $this->getConfig()->getPath('config', 'routing');

        if (file_exists($path)) {
            $routes = include($path);
            if (!is_writable($path)) {
                throw new waException(
                    sprintf(_ws('Settings could not be saved due to the insufficient file write permissions for the file "%s".'), 'wa-config/routing.php')
                );
            }
        } else {
            $routes = array();
        }

        $changed = false;

        foreach ($routes as $d => &$route) {
            if (is_array($route)) {
                foreach ($route as $r_id => &$r) {
                    if (isset($r['app']) && $r['app'] == $this->getAppId()) {
                        if (empty($r['hub_id'])) {
                            $r['hub_id'] = $hub_id;
                            $changed = true;
                            unset($r);
                            break 2;
                        }
                    }
                    unset($r);
                }
            }

        }
        unset($route);
        if ($changed) {
            waUtils::varExportToFile($routes, $path);
        }
        return $changed;
    }

    private function setup($types = array())
    {
        $type_model = new hubTypeModel();
        $filter_model = new hubFilterModel();
        $map = array();
        foreach ($types as $type_id) {
            if (!empty($this->types[$type_id])) {
                $type = $this->types[$type_id];
                $type['settings'] = json_encode((array)ifset($type['settings'], array()));
                $map[$type_id] = $id = $type_model->insert($type);
                if ($type['type'] == 'question') {
                    $filter_model->add(
                        array(
                            'name'       => _w('Unanswered'),
                            'contact_id' => 0,
                            'icon'       => 'comments',
                            'conditions' => array(
                                'types' => array(
                                    $id => array(
                                        'type_id'        => $id,
                                        'comments_count' => '0',
                                    ),
                                ),
                            ),
                        )
                    );
                }
            }
        }

        $hub_model = new hubHubModel();
        $hub_id = $hub_model->add(
            array(
                'name'   => wa()->accountName(),
                'status' => 1,
            )
        );
        $hub_model->add(
            array(
                'name'   => wa()->getLocale() === 'ru_RU' ? 'Приватный' : 'Private',
                'status' => 0,
            )
        );

        $hub_types_model = new hubHubTypesModel();
        $all_types = array_keys($type_model->getAll($type_model->getTableId()));
        $hub_types_model->setTypes($hub_id, $all_types);

        if ($this->hub_params) {
            $hub_params_model = new hubHubParamsModel();
            foreach ($this->hub_params as $name => $value) {
                $hub_params_model->insert(compact('hub_id', 'name', 'value'), 1);
            }
        }

        //Filter by topic type для каждого созданного типа
        $category_model = new hubCategoryModel();
        $map = array_reverse($map, true);
        foreach ($map as $type_id => $id) {
            $category_model->add(
                array(
                    'glyph'          => $this->types[$type_id]['glyph'],
                    'name'           => $this->types[$type_id]['category_name'],
                    'url'            => $this->types[$type_id]['category_url'],
                    'type'           => hubCategoryModel::TYPE_DYNAMIC,
                    'conditions'     => sprintf('type_id=%d', $id),
                    'hub_id'         => $hub_id,
                    'enable_sorting' => 1,

                )
            );
        }

        $staff_model = new hubStaffModel();

        $staff_member = array(
            'contact_id'  => $this->getUserId(),
            'name'        => $this->getUser()->getName(),
            'hub_id'      => $hub_id,
            'sort'        => 0,
            'badge'       => _w('support'),
            'badge_color' => '#ffffcc',
        );
        $staff_model->insert($staff_member, 1);


        return $hub_id;
    }
}
