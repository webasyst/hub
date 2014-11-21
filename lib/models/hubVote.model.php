<?php

class hubVoteModel extends waModel
{
    protected $table = 'hub_vote';

    public function getVote($contact_id, $type, $id)
    {
        $sql = "SELECT vote FROM {$this->table} WHERE contact_id=? AND ";
        if ($type == 'topic') {
            $sql .= 'topic_id=? AND comment_id IS NULL';
        } else {
            $sql .= 'comment_id=?';
        }
        return $this->query($sql, array($contact_id, $id))->fetchField();
    }

    public function getVotes($contact_ids, $comment_ids, $key = 'contact_id')
    {
        $contact_ids = (array) $contact_ids;
        $comment_ids = (array) $comment_ids;
        $where = $this->getWhereByField(array(
            'comment_id' => $comment_ids,
            'contact_id' => $contact_ids
        ));
        $sql = "SELECT * FROM `{$this->table}` WHERE ".$where;
        $key_fields = array('contact_id', 'comment_id');
        if ($key != 'contact_id') {
            $key_fields = array_reverse($key_fields);
        }
        $votes = array();
        foreach ($this->query($sql) as $item) {
            $key = array($item[$key_fields[0]], $item[$key_fields[1]]);
            $votes[$key[0]][$key[1]] = $item;
        }
        return $votes;
    }

    /**
     * Saves info about upvote or downvote to hub_vote table.
     * Does not update stats in hub_author
     *
     * @param $contact_id   int
     * @param $id           int     topic_id or comment_id
     * @param $type         string  'topic' or 'comment'
     * @param $vote         int     +1 for upvotes, -1 for downvotes
     * @return  int     total change in topic or comment `votes_sum`, can be -2 to +2.
     */
    public function vote($contact_id, $id, $type, $vote)
    {
        if (! ( $id = (int) $id) || ! ( $contact_id = (int) $contact_id)) {
            return;
        }

        if ($type == 'comment') {
            $comment_id = $id;
            $table = 'hub_comment';

            $topic_id = $this->query('SELECT topic_id FROM hub_comment WHERE id=?', $comment_id)->fetchField();
            if (!$topic_id) {
                return 0; // comment not found
            }
        } else {
            $topic_id = $id;
            $comment_id = null;
            $table = 'hub_topic';
            if (!$this->query('SELECT id FROM hub_topic WHERE id=?', $topic_id)->fetchField()) {
                return 0;
            }
        }

        $vote = array(
            'topic_id' => $topic_id,
            'comment_id' => $comment_id,
            'contact_id' => $contact_id,
            'vote' => $vote >= 0 ? 1 : -1,
            'datetime' => date('Y-m-d H:i:s'),
            'ip' => waRequest::getIp(true),
        );

        $previous_vote = $this->getByField(array(
            'contact_id' => $contact_id,
            'comment_id' => $comment_id,
            'topic_id' => $topic_id,
        ));
        if ($previous_vote) {
            if ($vote['vote'] == $previous_vote['vote']) {
                return 0;
            }
            $this->deleteById($previous_vote['id']);
        }

        $vote['id'] = $this->insert($vote);

        $set = array();
        $votes_sum_change = 0;
        if ($previous_vote) {
            if ($vote['vote'] > 0) {
                $votes_sum_change = 2;
                $set[] = 'votes_sum = votes_sum + 2';
                $set[] = 'votes_up = votes_up + 1';
                $set[] = 'votes_down = votes_down - 1';
            } else {
                $votes_sum_change = -2;
                $set[] = 'votes_sum = votes_sum - 2';
                $set[] = 'votes_up = votes_up - 1';
                $set[] = 'votes_down = votes_down + 1';
            }
        } else {
            $set[] = 'votes_count = votes_count + 1';
            if ($vote['vote'] > 0) {
                $votes_sum_change = 1;
                $set[] = 'votes_sum = votes_sum + 1';
                $set[] = 'votes_up = votes_up + 1';
            } else {
                $votes_sum_change = -1;
                $set[] = 'votes_sum = votes_sum - 1';
                $set[] = 'votes_down = votes_down + 1';
            }
        }

        if ($set) {
            $sql = "UPDATE {$table}
                    SET ".implode(', ', $set)."
                    WHERE id=?";
            $this->exec($sql, (int) $id);
        }

        return $votes_sum_change;
    }
}
