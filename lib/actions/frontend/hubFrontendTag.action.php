<?php

class hubFrontendTagAction extends hubFrontendAction
{
    public function execute()
    {
        $hub_id = waRequest::param('hub_id');
        if (!$hub_id) {
            throw new waException('No hub', 500);
        }

        $tag = waRequest::param('tag');
        if (!$tag) {
            throw new waException('Tag not found', 404);
        }

        // Ordering
        $possible_order = array(
            'popular' => array('votes_sum', 'DESC'),
            'recent' => array('id', 'DESC'),
            'unanswered' => array('votes_sum', 'DESC'),
        );
        $order = waRequest::request('sort', '', 'string_trim');
        if (empty($possible_order[$order])) {
            $order = key($possible_order);
        }

        // All topics of this tag
        $c = new hubTopicsCollection('tag/'.$tag);
        $this->setCollection($c);

        if (!$c->getInfo()) {
            throw new waException(_ws('Page not found', 404));
        } else {
            $tag_info = $c->getInfo();
            $tag = $tag_info['name'];
        }

        $this->getResponse()->setTitle($tag);

        /**
         * @event frontend_tag
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_tag', wa()->event('frontend_tag', $tag_info));

        $this->view->assign('tag', $tag);
        $this->view->assign('sort', $order);
        $this->setThemeTemplate('tag.html');
    }
}
