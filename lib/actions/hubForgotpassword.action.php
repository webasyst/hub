<?php

class hubForgotpasswordAction extends waForgotPasswordAction
{
    public function execute()
    {
        $this->setLayout(new hubFrontendLayout());
        $this->setThemeTemplate('forgotpassword.html');
        parent::execute();
    }
}
