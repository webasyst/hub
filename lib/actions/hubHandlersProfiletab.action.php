<?php
/**
 * Used by event handler to show Hub tab in contacts.
 */
class hubHandlersProfiletabAction extends waViewAction
{
    public function getTabContent($params)
    {
        $this->contact_id = $params;
        $html = $this->display();

        if ($this->topics) {
            return array(
                'title' => _w('Hub').' ('.count($this->topics).')',
                'html' => $html,
                'count' => 0,
            );
        } else {
            return null;
        }
    }

    public function execute()
    {
        // List of topics user created
        $c = new hubTopicsCollection('contact/'.$this->contact_id);
        $this->topics = $c->getTopics('*');
        $link_tpl = wa()->getAppUrl('hub').'#/topic/%id%/';
        $this->view->assign('topics', $this->topics);
        $this->view->assign('link_tpl', $link_tpl);
    }
}

