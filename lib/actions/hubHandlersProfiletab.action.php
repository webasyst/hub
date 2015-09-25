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

        if ($this->topics || $this->total_comments) {
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
        $limit = 50;
        $c = new hubTopicsCollection('contact/'.$this->contact_id);
        $this->topics = $c->getTopics('*', 0, $limit);
        $link_tpl = wa()->getAppUrl('hub').'#/topic/%id%/';

        $total_topics = count($this->topics);
        if ($total_topics == $limit) {
            $total_topics = $c->count();
        }

        $comment_model = new hubCommentModel();
        $this->total_comments = $comment_model->countByField('contact_id', $this->contact_id);

        $this->view->assign('topics', $this->topics);
        $this->view->assign('link_tpl', $link_tpl);
        $this->view->assign('total_topics', $total_topics);
        $this->view->assign('total_comments', $this->total_comments);
        $this->view->assign('contact_id', $this->contact_id);
    }
}

