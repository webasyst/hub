<?php

class hubFrontendAuthorFollowingAction extends hubFrontendAction
{
    public function execute()
    {
        $id = (int) waRequest::param('id');
        if (wa()->getUser()->getId() != $id) {
            throw new waRightsException(_ws('Access denied'));
        }

        $author = hubHelper::getAuthor($id);
        $author['name'] = htmlspecialchars($author['name']);
        $author['contact_id'] = $id;

        wa()->getResponse()->setTitle(_w('Following'));

        $c = new hubTopicsCollection('following');
        $c->orderBy('update_datetime');
        $this->setCollection($c);

        $this->view->assign(array(
            'author_following' => true,
            'author' => $author
        ));

        /**
         * @event frontend_author
         * @return array[string]string $return[%plugin_id%] html output for search
         */
        $this->view->assign('frontend_author', wa()->event('frontend_author', $author));

        $this->setThemeTemplate('author.html');
    }
}

