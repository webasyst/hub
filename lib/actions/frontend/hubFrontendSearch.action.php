<?php

class hubFrontendSearchAction extends hubFrontendAction
{
    public function execute()
    {
        $q = waRequest::request('q');
        if (!$q) {
            $q = waRequest::request('query');
        }
        if (!$q) {
            $this->redirect(wa()->getRouteUrl('hub/frontend/'));
        }

        // !!! pagination?..
        $c = new hubTopicsCollection(
            'search/query='.str_replace('&', '\\&', $q),
            array('hub_id' => waRequest::param('hub_id'))
        );
        $this->setCollection($c);
        $this->view->assign('title', $q);

        /**
         * @event frontend_search
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_search', wa()->event('frontend_search'));

        $this->setThemeTemplate('search.html');
    }
}
