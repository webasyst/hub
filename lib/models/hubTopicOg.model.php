<?php

class hubTopicOgModel extends waModel
{
    protected $table = 'hub_topic_og';

    public function get($id)
    {
        return $this->select('property, content')->where('topic_id = ?', (int)$id)->fetchAll('property', true);
    }

    public function set($id, $params = array())
    {
        $id = (int)$id;
        if (!$id) {
            return false;
        }

        $this->clear($id);

        $values = array();
        foreach ($params as $property => $content) {
            $values[] = array(
                'topic_id' => $id,
                'property' => $property,
                'content' => (string)$content,
            );
        }

        if ($values) {
            $this->multipleInsert($values);
        }
        return true;
    }

    public function clear($id)
    {
        return $this->deleteByField(array(
            'topic_id' => (int)$id,
        ));
    }

    public static function getEmptyData()
    {
        return [
            'title' => null,
            'image' => '',
            'video' => '',
            'description' => null,
        ];
    }
}

