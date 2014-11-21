<?php

return array(
    'name' => 'Debug',
    'description' => '',
    'version'=>'0.0.1',
    'handlers' => array(
        'frontend_head' => 'frontendHead',
        'frontend_homepage' => 'frontendHomepage',
        'frontend_header' => 'frontendHeader',
        'frontend_footer' => 'frontendFooter',
        'frontend_search' => 'frontendSearch',
        'frontend_nav' => 'frontendNav',
        'frontend_category' => 'frontendCategory',
        'frontend_topic_add' => 'frontendTopicAdd',
        'frontend_topic_edit' => 'frontendTopicEdit',
        'frontend_topic' => 'frontendTopic',
        'frontend_comments' => 'frontendComments',
        'frontend_author' => 'frontendAuthor',
    )
);