<?php

wa('hub');
$hub_model = new hubHubModel();
$hubs = $hub_model->getNames(true);

return array(
    'params' => array(
        'hub_id' => array(
            'name' => _w('Hub'),
            'type' => 'select',
            'items' => $hubs
        ),
        'home_sort' => array(
            'name' => _w('Homepage default topic sort order'),
            'type' => 'select',
            'items' => array(
                'popular' => _w('Popular'),
                'recent' => _w('Newest'),
                'updated' => _w('Updated'),
            )
        ),
        'title' => array(
            'name' => _w('Homepage title <title>'),
            'type' => 'input',
        ),
        'meta_keywords' => array(
            'name' => _w('Homepage META Keywords'),
            'type' => 'input'
        ),
        'meta_description' => array(
            'name' => _w('Homepage META Description'),
            'type' => 'textarea'
        ),
    ),
    'vars'   => array(
        '$wa' => array(
            '$wa->hub->topic($id)' => _w(''),
            '$wa->hub->topics($hash[, $offset[, $limit[, $hub_id]]])' => _w(''),
            '$wa->hub->comments($limit[, $hub_id])' => _w(''),
            '$wa->hub->staff([$hub_id])' => _w('Stuff as set up in hub settings.'),
            '$wa->hub->categories([bool $priority_topics[, $hub_id]])' => _w('Categories of given hub. When $priority_topics is <em>true</em>, list of high-priority topics is returned for each category.'),
            '$wa->hub->tags([$limit[, $hub_id]])' => _w(''),
            '$wa->hub->authors([$limit[, $hub_id]])' => _w(''),
        ),
    ),
);

