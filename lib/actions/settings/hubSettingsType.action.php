<?php

class hubSettingsTypeAction extends hubSettingsAction
{
    public function execute()
    {
        if ($id = waRequest::get('id', 0, waRequest::TYPE_INT)) {
            if (isset($this->types[$id])) {
                $type = $this->types[$id];
                $topic_model = new hubTopicModel();

                $type['topics_count'] = $topic_model->countByField('type_id', $id);
            } else {
                throw new waException('Type not found', 404);
            }
        } else {
            $types = hubHelper::getBaseTypes();
            reset($types);
            $type = array(
                'name'     => '',
                'id'       => 0,
                'type'     => key($types),
                'glyph'    => 'question',
                'settings' => array(
                    'voting'     => array('+' => '+', '-' => '-'),
                    'commenting' => "1",
                ),
            );
        }

        $this->view->assign('type', $type);
    }
}
