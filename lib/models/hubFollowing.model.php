<?php

class hubFollowingModel extends waModel
{
    protected $table = 'hub_following';

    public function getFollowers($topic_id, $for_email = false)
    {
        $settings_join = '';
        $settings_where = '';
        if ($for_email) {
            $settings_join = "JOIN wa_contact_settings AS cs ON c.id=cs.contact_id AND cs.app_id='hub' AND cs.name='email_following'";
            $settings_where = "AND cs.value='1'";
        }

        $sql = "SELECT c.*, ce.email
                FROM {$this->table} f
                    JOIN wa_contact c
                        ON f.contact_id = c.id
                    JOIN wa_contact_emails ce
                        ON c.id = ce.contact_id AND sort = 0
                    {$settings_join}
                WHERE f.topic_id = i:0
                    {$settings_where}";

        $result = $this->query($sql, $topic_id)->fetchAll('id');

        // Format contact names
        foreach($result as &$c) {
            $c['name'] = waContactNameField::formatName($c);
        }
        unset($c);

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

    public function countTopics($contact_id, $hub_id)
    {
        if (!$hub_id || !$contact_id) {
            return 0;
        }

        $sql = "SELECT count(*)
                FROM hub_following AS f
                    JOIN hub_topic AS t
                        ON t.id=f.topic_id
                WHERE f.contact_id=?
                    AND t.hub_id IN (?)";
        return (int) $this->query($sql, array(
            (int) $contact_id,
            (array) $hub_id)
        )->fetchField();
    }
}
