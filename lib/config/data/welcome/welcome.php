<?php

return array(
    'topic_types' => array(
        'guides'    => array(
            'glyph'         => 'info',
            'category_name' => 'Articles', //name for topic category and to display on the welcome screen
            'name'          => 'Article', //name for the topic type
            'description'   => 'Guides, instructions, tutorials',
            'type'          => 'page',
            'settings'      => array(
                'voting'     => array('+' => '+', '-' => '-'),
                'commenting' => "1",
            ),
        ),
        'questions' => array(
            'glyph'         => 'question',
            'category_name' => 'Questions',
            'name'          => 'Question',
            'description'   => 'Q & A topics',
            'type'          => 'question',
            'settings'      => array(
                'voting'     => array('+' => '+', '-' => '-'),
                'commenting' => "1",
            ),
        ),
        'idea'      => array(
            'glyph'         => 'idea',
            'category_name' => 'Ideas',
            'name'          => 'Idea',
            'description'   => 'Collect ideas and suggestions from your customers',
            'type'          => 'feedback',
            'settings'      => array(
                'voting'     => array('+' => '+', '-' => '-'),
                'commenting' => "1",
                'badges'     =>
                    array(
                        'archived'  => 'archived',
                        'pending'   => 'pending',
                        'confirmed' => 'confirmed',
                        'complete'  => 'complete',
                        'rejected'  => 'rejected',
                    ),
            ),
        ),
        'bugreport' => array(
            'glyph'         => 'bug',
            'category_name' => 'Bug reports',
            'name'          => 'Bug report',
            'description'   => 'Collect bug reports',
            'type'          => 'feedback',
            'settings'      => array(
                'voting'     => array('+' => '+', '-' => '-'),
                'commenting' => "1",
                'badges'     =>
                    array(
                        'archived'  => 'archived',
                        'pending'   => 'pending',
                        'confirmed' => 'confirmed',
                        'complete'  => 'complete',
                        'fixed'     => 'fixed',
                        'rejected'  => 'rejected',
                    ),
            ),
        ),
        'forum'     => array(
            'glyph'         => 'userpic',
            'category_name' => 'Discussions (forum)',
            'name'          => 'Discussion',
            'description'   => 'Discussions in classic forum threads',
            'type'          => 'forum',
            'settings'      => array(),
        ),
    ),
    'hub_params'  => array(
        'kudos'             => 1,
        'kudos_per_topic'   => 1,
        'kudos_per_comment' => 2,
        'kudos_per_answer'  => 3,
        'all_types'         => 1,
        'color'             => 'white',
    ),
);
