<?php

class hubFrontendAuthorAction extends hubFrontendAction
{
    public function execute()
    {
        $id = (int) waRequest::param('id');

        $author = hubHelper::getAuthor($id);
        $author['name'] = htmlspecialchars($author['name']);
        $author['contact_id'] = $id;

        wa()->getResponse()->setTitle($author['name']);

        $c = new hubTopicsCollection('contact/'.$id, array('sort' => 'recent', 'hub_id' => waRequest::param('hub_id')));
        $this->setCollection($c);

        $this->view->assign(array(
            'author_topics' => true,
            'author'        => $author
        ));

        /**
         * @event frontend_author
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_author', wa()->event('frontend_author', $author));

        $this->setThemeTemplate('author.html');
    }
}
