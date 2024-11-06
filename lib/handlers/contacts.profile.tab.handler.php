<?php

class hubContactsProfileTabHandler extends waEventHandler
{
    public function execute(&$params)
    {
        $contact_id = (is_array($params) ? ifset($params, 'id', 0) : $params);
        $counter_inside = is_array($params) ? ifset($params, 'counter_inside', true) : waRequest::param('profile_tab_counter_inside', true);
        $old_app = wa()->getApp();
        wa('hub')->setActive('hub');
        try {
            $a = new hubHandlersProfiletabAction();
            $result = $a->getTab($contact_id, $counter_inside);
        } catch (Exception $e) {
            $result = array(
                'title' => wa()->getApp().' error',
                'html' => (string) $e,
                'count' => 0,
            );
        }

        waSystem::setActive($old_app);
        return $result;
    }
}
