<?php
class hubTopicParamsModel extends waModel
{
    protected $table = 'hub_topic_params';

    public function assign($topic_id, $params)
    {
        $values = array();
        foreach ($params as $name => $value) {
            $values[] = array(
                'name' => $name,
                'value' => $value,
                'topic_id' => $topic_id,
            );
        }
        $this->deleteByField('topic_id', $topic_id);
        $values && $this->multipleInsert($values);
    }

    /**
     * @param int|array $topic_id single id or a list of ids
     * @return array topic_id => key => value (or just key => value when $topic_id is an int)
     */
    public function getByTopic($topic_id) {
        $result = array();
        foreach($this->getByField('topic_id', (array) $topic_id, true) as $row) {
            $result[$row['topic_id']][$row['name']] = $row['value'];
        }

        if (is_array($topic_id)) {
            foreach($topic_id as $id) {
                if(empty($result[$id])) {
                    $result[$id] = array();
                }
            }
            return $result;
        } else {
            return ifset($result[$topic_id], array());
        }
    }
}
