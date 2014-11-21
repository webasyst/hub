<?php

class hubFollowingModel extends waModel
{
    protected $table = 'hub_following';

    public function getFollowers($topic_id)
    {
        $sql = 'SELECT c.*, ce.email FROM '.$this->table.' f JOIN wa_contact c ON f.contact_id = c.id
        JOIN wa_contact_emails ce ON c.id = ce.contact_id AND sort = 0
        WHERE f.topic_id = i:0';

        $result = $this->query($sql, $topic_id)->fetchAll('id');

        // Own record always comes first
        $me = ifempty($result[wa()->getUser()->getId()]);
        unset($result[wa()->getUser()->getId()]);
        $result = array_values($result);
        $me && array_unshift($result, $me);

        return $result;
    }

    public function addFollower($topic_id, $contact_id = null)
    {
        if (!$contact_id) {
            $contact_id = wa()->getUser()->getId();
        }
        return $this->insert(array(
            'topic_id' => $topic_id,
            'contact_id' => $contact_id,
            'datetime' => date('Y-m-d H:i:s')
        ), 2);
    }

    /** Total number of new comments to all topics current user is following. */
    public function countNewComments()
    {
        $sql = "SELECT COUNT(id) AS cnt
                FROM `{$this->table}` AS f
                    JOIN hub_comment AS c
                        ON f.topic_id = c.topic_id
                WHERE f.contact_id = ?
                    AND c.status = 'approved'
                    AND c.datetime > ?";

        return $this->query($sql, array(
            wa()->getUser()->getId(),
            date('Y-m-d H:i:s', wa('hub')->getConfig()->getLastDatetime()),
        ))->fetchField('cnt');
    }
}
