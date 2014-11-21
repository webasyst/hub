<?php

class hubOAuthController extends waOAuthController
{
    public function afterAuth($data)
    {
        $contact = parent::afterAuth($data);
        if ($contact && !$contact['is_user']) {
            $contact->addToCategory($this->getAppId());
        }

        wa('webasyst');
        $this->executeAction(new webasystOAuthAction());
    }
}
