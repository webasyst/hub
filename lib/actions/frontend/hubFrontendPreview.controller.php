<?php

class hubFrontendPreviewController extends waJsonController
{
    public function execute()
    {
        $content = waRequest::post('content');
        if ($content) {
            $content = hubHelper::sanitizeHtml($content);
        }
        $this->response = $content;
    }
}
