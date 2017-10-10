<?php

class hubFrontendTagAction extends hubFrontendAction
{
    public function execute()
    {
        // Make sure hub and tag are set in request params
        $hub_id = $this->getHubId();
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

        // When tag is not found, show an empty list page, not 404
        $tag_info = $c->getInfo();
        if (!$tag_info) {
            $tag_model = new hubTagModel();
            $tag_info = $tag_model->getEmptyRow();
            $tag_info['name'] = $tag;
        }

        /**
         * @event frontend_tag
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_tag', wa()->event('frontend_tag', $tag_info));

        $this->getResponse()->setTitle(htmlspecialchars($tag_info['name']));
        $this->view->assign('tag', $tag_info['name']);
        $this->view->assign('sort', $order);
        $this->setThemeTemplate('tag.html');
    }
}

