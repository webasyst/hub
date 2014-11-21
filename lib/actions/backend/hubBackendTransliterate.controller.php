<?php

class hubBackendTransliterateController extends waJsonController
{
    public function execute()
    {
        $this->response = hubHelper::transliterate(
            waRequest::get('str', '', waRequest::TYPE_STRING_TRIM),
            waRequest::get('strict', 1, waRequest::TYPE_INT)
        );
    }
}
