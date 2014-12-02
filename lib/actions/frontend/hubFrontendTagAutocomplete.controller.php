<?php

class hubFrontendTagAutocompleteController extends waJsonController
{
    public function execute()
    {
        $hub_id = waRequest::param('hub_id', 0, 'int');
        $term = waRequest::get('term', '', 'string_trim');
        if (!$term) {
            return;
        }

        $tm = new hubTagModel();
        $tags = $tm
            ->select('name AS value')
            ->limit(10)
            ->where("name LIKE ?".($hub_id ? " AND hub_id=?" : ""), $tm->escape($term, 'like').'%', $hub_id)
            ->fetchAll();

        foreach ($tags as &$tag) {
            $tag['label'] = htmlspecialchars($tag['value']);
        }
        unset($tag);

        $this->response = $tags;
    }
}
