<?php

class hubFrontendLayout extends waLayout
{
    public function execute()
    {
        /**
         * @event frontend_head
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_head', wa()->event('frontend_head'));

        /**
         * @event frontend_header
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_header', wa()->event('frontend_header'));

        if (!$this->view->getVars('frontend_nav')) {
            /**
             * @event frontend_nav
             * @return array[string]string $return[%plugin_id%] html output for navigation section
             */
            $this->view->assign('frontend_nav', wa()->event('frontend_nav'));
        }

        /**
         * @event frontend_footer
         * @return array[string]string $return[%plugin_id%] html output
         */
        $this->view->assign('frontend_footer', wa()->event('frontend_footer'));

        if ($this->getUser()->isAuth() && !$this->view->getVars('user')) {
            $this->view->assign('user', hubHelper::getAuthor($this->getUserId()));
        }

        $this->setThemeTemplate('index.html');
    }
}