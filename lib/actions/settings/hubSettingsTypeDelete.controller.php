<?php

class hubSettingsTypeDeleteController extends waJsonController
{
    public function execute()
    {
        try {
            $id = waRequest::post('id', 0, waRequest::TYPE_INT);
            //update topic types or delete topics
            $topic_model = new hubTopicModel();
            switch (waRequest::post('delete', 'safe', waRequest::TYPE_STRING_TRIM)) {
                case 'move':
                    $update = array(
                        'type_id' => waRequest::post('type_id', 0, waRequest::TYPE_INT),
                    );
                    $topic_model->updateByField('type_id', $id, $update);
                    break;
                case 'safe':
                    if ($topic_model->countByField('type_id', $id)) {
                        throw new waException('There are some topics with current type');
                    }
                    break;
            }

            $type_model = new hubTypeModel();
            $type_model->deleteById($id);
        } catch (waException $ex) {
            $this->errors[] = $ex->getMessage();
        }
    }
}
