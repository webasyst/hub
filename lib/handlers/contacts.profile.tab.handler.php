<?php

class hubContactsProfileTabHandler extends waEventHandler
{
    public function execute(&$params)
    {

        $old_app = wa()->getApp();
        wa('hub')->setActive('hub');
        try {
            $a = new hubHandlersProfiletabAction();
            $result = $a->getTabContent($params);
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
