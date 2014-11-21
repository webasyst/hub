<?php

class hubFrontendPageAction extends waPageAction
{
    public function execute()
    {
        $this->setLayout(new hubFrontendLayout());
        parent::execute();
    }

    public function display($clear_assign = true)
    {
        $this->view->assign('user', hubHelper::getAuthor($this->getUserId()));
        try {
            return parent::display(false);
        } catch (waException $e) {
            $code = $e->getCode();
            if ($code == 404) {
                $url = $this->getConfig()->getRequestUrl(false, true);
                if (substr($url, -1) !== '/' && substr($url, -9) !== 'index.php') {
                    wa()->getResponse()->redirect($url.'/', 301);
                }
            }

            $this->view->assign('error_message', $e->getMessage());
            $this->view->assign('error_code', $code);
            $this->getResponse()->setStatus($code ? $code : 500);
            $this->setThemeTemplate('error.html');
            return $this->view->fetch($this->getTemplate());
        }
    }
}
