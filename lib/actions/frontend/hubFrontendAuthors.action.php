<?php

class hubFrontendAuthorsAction extends hubFrontendAction
{
    public function execute()
    {
        $limit = 50;
        $page = waRequest::get('page', 1, 'int');
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $limit;

        $author_model = new hubAuthorModel();
        $authors = $author_model->getList('*,badge', array(
            'hub_id' => $this->hub_id,
            'offset' => $offset,
            'limit' => $limit
        ), $count);

        if (!$count) {
            $pages_count = 1;
        } else {
            $pages_count = ceil((float)$count / $limit);
        }
        $this->view->assign('pages_count', $pages_count);

        $author_url = wa()->getRouteUrl('/frontend/author', array('id' => '%ID%'));
        foreach ($authors as &$a) {
            $a['url'] = str_replace('%ID%', $a['contact_id'], $author_url);
        }
        unset($a);

        $this->view->assign('authors', $authors);

        wa()->getResponse()->setTitle(_w('Authors'));
        $this->setThemeTemplate('authors.html');
    }
}