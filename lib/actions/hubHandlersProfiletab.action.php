<?php
/**
 * Used by event handler to show Hub tab in contacts.
 */
class hubHandlersProfiletabAction extends waViewAction
{
    protected $contact_id;
    protected $topics;
    protected $total_comments;

    public function getTab($params, $counter_inside = true)
    {
        $this->contact_id = $params;
        $c = new hubTopicsCollection('contact/'.$this->contact_id);
        $count = $c->count();
        if ($count) {
            return [
                'title' => _w('Hub').($counter_inside ? ' ('.$count.')' : ''),
                'html' => '',
                'count' => ($counter_inside ? 0 : $count),
                'url' => wa()->getAppUrl('hub').'?module=handlers&action=profiletab&id='.$this->contact_id,
            ];
        } else {
            return null;
        }
    }

    public function execute()
    {
        $this->contact_id = waRequest::get('id', 0, waRequest::TYPE_INT);
        
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

