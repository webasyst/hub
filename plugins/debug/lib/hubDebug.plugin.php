<?php

class hubDebugPlugin extends waPlugin
{
    public function frontendHead()
    {
        return '<!-- DEBUG -->';
    }

    public function frontendHeader()
    {
        return __METHOD__;
    }

    public function frontendHomepage()
    {
        return __METHOD__;
    }

    public function frontendFooter()
    {
        return __METHOD__;
    }

    public function frontendSearch()
    {
        return __METHOD__;
    }

    public function frontendNav()
    {
        return __METHOD__;
    }

    public function frontendCategory()
    {
        return __METHOD__;
    }

    public function frontendAuthor()
    {
        return __METHOD__;
    }

    public function frontendComments(&$comments)
    {
        if (!$comments) {
            return;
        }
        foreach ($comments as &$c) {
            $c['plugins'][$this->id] = __METHOD__;
        }
        unset($c);
    }

    public function frontendTopic($topic)
    {
        $result = array();
        foreach (array(
            'title_suffix',
            'body',
            'comments'
        ) as $k ) {
            $result[$k] = __METHOD__.'.'.$k;
        }
        return $result;
    }

    public function frontendTopicAdd()
    {
        $result = array();
        foreach (array(
                     'top_block',
                     'bottom_block',
                 ) as $k ) {
            $result[$k] = __METHOD__.'.'.$k;
        }
        return $result;
    }

    public function frontendTopicEdit($topic)
    {
        $result = array();
        foreach (array(
                     'top_block',
                     'bottom_block',
                 ) as $k ) {
            $result[$k] = __METHOD__.'.'.$k;
        }
        return $result;
    }

}