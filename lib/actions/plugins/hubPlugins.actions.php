<?php

class hubPluginsActions extends waPluginsActions
{
    protected $shadowed = true;

    public function preExecute()
    {
        if (!$this->getUser()->isAdmin('site')) {
            throw new waRightsException(_ws('Access denied'));
        }
    }
}