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

        if (empty($data['status'])) {
            // Make sure to delete all routes of a private hub
            $this->removeRoutes($hub_id);
        } else {
            $this->saveNewRoute($hub_id);
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

    /** Remove all frontend routes for selected hub */
    protected function removeRoutes($hub_id)
    {
        $path = $this->getConfig()->getPath('config', 'routing');
        if (!file_exists($path) || !is_writable($path)) {
            return;
        }

        $something_changed = false;
        $route_config = include($path);
        if (empty($route_config)) {
            return;
        }

        foreach($route_config as $domain => $routes) {
            if (!is_array($routes)) {
                continue;
            }
            foreach($routes as $k => $route) {
                if (!empty($route['app']) && ($route['app'] == 'hub') && !empty($route['hub_id']) && ($route['hub_id'] == $hub_id)) {
                    unset($route_config[$domain][$k]);
                    $something_changed = true;
                }
            }
        }

        if ($something_changed) {
            waUtils::varExportToFile($route_config, $path);
        }
    }

    /** Create new route for this hub if data came via POST */
    protected function saveNewRoute($hub_id)
    {
        // User asked to create new route?
        if (!waRequest::request('route_enabled')) {
            return true;
        }

        // Make sure routing config is writable, and load existing routes
        $path = $this->getConfig()->getPath('config', 'routing');
        if (file_exists($path)) {
            if (!is_writable($path)) {
                return false;
            }
            $routes = include($path);
        } else {
            $routes = array();
        }

        // Route domain
        $domain = waRequest::post('route_domain', '', 'string');
        if (!isset($routes[$domain])) {
            return false;
        }

        // Route URL
        $url = waRequest::post('route_url', '', 'string');
        $url = rtrim($url, '/*');
        $url .= ($url?'/':'').'*';

        // Determine new numeric route ID
        $route_ids = array_filter(array_keys($routes[$domain]), 'intval');
        $new_route_id = $route_ids ? max($route_ids) + 1 : 1;

        $new_route = array(
            'url' => $url,
            'app' => $this->getAppId(),
            'hub_id' => $hub_id,
            'theme' => 'default', // !!! add theme selector? use existing theme on this domain?..
            'theme_mobile' => 'default',
        );

        if ($new_route['url'] == '*') {
            // Add as the last rule
            $routes[$domain][$new_route_id] = $new_route;
        } else {
            // Add as the first rule
            $routes[$domain] = array($new_route_id => $new_route) + $routes[$domain];
        }

        waUtils::varExportToFile($routes, $path);
        return true;
    }
}

