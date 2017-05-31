<?php

return array(
    'add/'    => 'frontend/add',
    'tag/<tag>/' => 'frontend/tag',
    'upload/image/' => array('module' => 'frontend', 'action' => 'uploadImage', 'secure' => true),
    'json/preview/' => array('module' => 'frontend', 'action' => 'preview', 'secure' => true),
    'json/follow/' => array('module' => 'frontend', 'action' => 'topicFollow', 'secure' => true),
    'json/vote/' => array('module' => 'frontend', 'action' => 'vote', 'secure' => true),
    'json/tag-autocomplete/' => 'frontend/tagAutocomplete',
    'search/' => 'frontend/search',
    'authors/' => 'frontend/authors',
    'author/<id:\d+>/following/' => 'frontend/authorFollowing',
    'author/<id:\d+>/replies/' => 'frontend/authorReplies',
    'author/<id:\d+>/' => 'frontend/author',
    'login/' => 'login/',
    'forgotpassword/' => 'forgotpassword/',
    'signup/' => 'signup/',
    'my/' => array('module' => 'frontend', 'action' => 'my', 'secure' => true),
    // topic comments
    '<id:\d+>/<topic_url>/comments/add/' => array('module' => 'frontend', 'action' => 'commentsAdd', 'secure' => true),
    '<id:\d+>/<topic_url>/comments/edit/' => array('module' => 'frontend', 'action' => 'commentsEdit', 'secure' => true),
    '<id:\d+>/<topic_url>/comments/delete/' => array('module' => 'frontend', 'action' => 'commentsDelete', 'secure' => true),
    '<id:\d+>/<topic_url>/comments/solution/' => array('module' => 'frontend', 'action' => 'commentsSolution', 'secure' => true),
    '<id:\d+>/<topic_url>/comments/order/' => 'frontend/commentsOrder',
    // topic actions
    '<id:\d+>/<topic_url>/edit/' => array('module' => 'frontend', 'action' => 'topicEdit', 'secure' => true),
    '<id:\d+>/<topic_url>/delete/' => array('module' => 'frontend', 'action' => 'topicDelete', 'secure' => true),
    // topic
    '<id:\d+>/<topic_url>/' => 'frontend/topic',
    // category
    '<category_url>/' => 'frontend/category',
    // main page
    '' => 'frontend/'
);
