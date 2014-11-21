<?php

class hubSettingsHubDeleteController extends waJsonController
{
    public function execute()
    {
        if ($hub_id = max(0, waRequest::post('id', 0, waRequest::TYPE_INT))) {
            $hub_model = new hubHubModel();
            switch (waRequest::post('delete')) {
                case 'move':

                    $target_hub_id = waRequest::post('target_hub_id', 0, waRequest::TYPE_INT);

                    /*
                     *  move topic with comments to new hub
                     */
                    $topic_model = new hubTopicModel();
                    $topic_model->changeHub($hub_id, $target_hub_id);
                    break;
            }

            /**
             * delete hub & related data (internal at model)
             */
            if (!$hub_model->deleteById($hub_id)) {
                $this->errors[] = 'Error during delete hub';

            }

        } else {
            $this->errors[] = 'Empty hub ID';
        }
    }
}
