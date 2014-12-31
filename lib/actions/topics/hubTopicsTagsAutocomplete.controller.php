<?php

class hubTopicsTagsAutocompleteController extends waController
{
    public function execute()
    {
        $limit = 10;
        $term = waRequest::get('term', '', waRequest::TYPE_STRING_TRIM);
        $hub_id = waRequest::get('hub_id', '', waRequest::TYPE_INT);

        $tag_model = new hubTagModel();
        $term = $tag_model->escape($term, 'like');

        $query = $tag_model->
                select('name,count')->
                where("name LIKE '$term%'".($hub_id ? " AND hub_id = {$hub_id}" : ""))->
                limit($limit);

        $tags = array();
        foreach ($query->fetchAll() as $tag) {
            if (empty($tags[$tag['name']])) {
                $count = $tag['count'];
            } else {
                $count = $tags[$tag['name']]['count'] + $tag['count'];
            }

            $tags[$tag['name']] = array(
                'label' => '<span class="count">'.$count.'</span>'.htmlspecialchars($tag['name']),
                'value' => $tag['name'],
                'count' => $count,
            );
        }
        usort($tags, array('hubTopicsTagsAutocompleteController', 'sortCmp'));
        echo json_encode(array_values($tags));
    }

    public static function sortCmp($a, $b)
    {
        return $b['count'] - $a['count'];
    }
}
