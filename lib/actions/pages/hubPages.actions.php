<?php

class hubPagesActions extends waPageActions
{
    protected $url = '#/pages/';
    protected $add_url = '#/pages/add';

    public function __construct()
    {
        $this->options['is_ajax'] = true;
    }

    public function preExecute()
    {
        parent::preExecute();
        if ($this->action != 'uploadimage' && !$this->getRights('pages')) {
            throw new waRightsException("Access denied");
        }
    }
}
