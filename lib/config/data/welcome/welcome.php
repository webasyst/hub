<?php

return array(
    'topic_types' => array(
        'guides'    => array(
            'glyph'       => 'info',
            'name'        => 'Guides and instructions',
            'description' => 'Articles, instructions, tutorials',
            'type'        => 'page',
            'settings'    => array(
                'voting'     => array('+' => '+', '-' => '-'),
                'commenting' => "1",
            ),
        ),
        'questions' => array(
            'glyph'       => 'question',
            'name'        => 'Question',
            'description' => 'Q & A topics',
            'type'        => 'question',
            'settings'    => array(
                'voting'     => array('+' => '+', '-' => '-'),
                'commenting' => "1",
            ),
        ),
        'idea'      => array(
            'glyph'       => 'idea',
            'name'        => 'Ideas',
            'description' => 'Collect ideas and suggestions from your customers',
            'type'        => 'feedback',
            'settings'    => array(
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
            'glyph'       => 'bug',
            'name'        => 'Bug reports',
            'description' => 'Collect bug reports',
            'type'        => 'feedback',
            'settings'    => array(
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
            'glyph'       => 'userpic',
            'name'        => 'Discussion forum',
            'description' => 'Discussions in classic forum threads',
            'type'        => 'forum',
            'settings'    => array(),
        ),
    ),
);
