<?php
/**
 * The `hub_author` table stores contact stats separately for each hub.
 *
 * - `topics_count`, `comments_count` and `answers_count` serve as a mere cache for 'count by contact_id'
 *   from `hub_topic` and `hub_comment`.
 *   - They are updated by $this->updateCounts(). They can not be incremented or decremented, only synced.
 *   - When topic or comment is deleted, these counts decrease.
 *   - Unpublished topics and comments are not taken into account.
 *
 * - `votes_up`, `votes_down` and `rate`, on the other hand, are treated differently.
 *   - They can only be incremented or decremented using $this->receiveVote().
 *   - When a topic or comment is deleted, these do not change.
 *   - When a comment is marked as a solution, they do not change, despite the fact that a solution may worth more.
 *   - These fields therefore should not be synchronized with actual comments and topics from hub_topic and hub_comment.
 *     They represent values gained over a period of time.
 */
class hubAuthorModel extends waModel
{
    protected $id = null;
    protected $table = 'hub_author';

    /**
     * Recalculate `topics_count`, `comments_count`, and `answers_count` of a given contact in given hub.
     *
     * Accepts one of:
     * - hub_id
     * - hub_id, contact_id
     * - hub_id, list of contact_ids
     * - list of pairs [hub_id, contact_id]
     * - list of hashes { 'hub_id' => hub_id, 'contact_id' => contact_id }
     *
     * @param $what_to_update   string      topics|comments|all
     * @param $hub_id           int|array
     * @param $contact_id       int|array|null
     */
    public function updateCounts($what_to_update, $hub_id, $contact_id=null)
    {
        if (is_array($hub_id)) {
            $pairs = $hub_id;

            // Group pairs by hub_id
            $hubs = array(); // hub_id => list of contact_ids
            foreach($pairs as $pair) {
                $hub_id = ifset($pair['hub_id'], ifset($pair[0]));
                $contact_id = ifset($pair['contact_id'], ifset($pair[1]));
                if ($hub_id && $contact_id) {
                    empty($hubs[$hub_id]) && $hubs[$hub_id] = array();
                    $hubs[$hub_id][$contact_id] = true;
                }
            }

            // Recalculate each hub_id separately
            foreach($hubs as $hub_id => $contacts) {
                $this->updateCounts($what_to_update, $hub_id, array_keys($contacts));
            }

            return;
        } else if (is_array($contact_id)) {
            $contacts = $contact_id;
        } else if (!empty($contact_id)) {
            $contacts = array($contact_id);
        } else {
            $contacts = array();
        }


        if (empty($contacts)) {
            $where_contacts = '';
        } else {
            $where_contacts = " AND contact_id IN (:contacts) ";
        }

        // Update topics_count
        if ($what_to_update == 'topics' || $what_to_update == 'all') {
            $sql = "SELECT contact_id, COUNT(*) AS topics_count
                    FROM hub_topic
                    WHERE hub_id=i:hub_id
                        {$where_contacts}
                        AND status=1
                    GROUP BY contact_id";
            $contacts_not_updated = array_fill_keys($contacts, true);
            foreach($this->query($sql, array('hub_id' => $hub_id, 'contacts' => $contacts)) as $row) {
                unset($contacts_not_updated[$row['contact_id']]);
                $this->ensureAuthorExists($hub_id, $row['contact_id']);
                $this->updateByField(array(
                    'contact_id' => $row['contact_id'],
                    'hub_id' => $hub_id,
                ), array(
                    'topics_count' => $row['topics_count'],
                ));
            }

            if ($contacts_not_updated) {
                $this->updateByField(array(
                    'contact_id' => array_keys($contacts_not_updated),
                    'hub_id' => $hub_id,
                ), array(
                    'topics_count' => 0,
                ));
            }
        }

        // Update comments_count and answers_count
        if ($what_to_update == 'comments' || $what_to_update == 'all') {
            $sql = "SELECT contact_id, COUNT(*) AS comments_count, SUM(IF(solution, 1, 0)) AS answers_count
                    FROM hub_comment
                    WHERE hub_id=i:hub_id
                        {$where_contacts}
                        AND status='approved'
                    GROUP BY contact_id";
            $contacts_not_updated = array_fill_keys($contacts, true);
            foreach($this->query($sql, array('hub_id' => $hub_id, 'contacts' => $contacts)) as $row) {
                unset($contacts_not_updated[$row['contact_id']]);
                $this->ensureAuthorExists($hub_id, $row['contact_id']);
                $this->updateByField(array(
                    'contact_id' => $row['contact_id'],
                    'hub_id' => $hub_id,
                ), array(
                    'comments_count' => $row['comments_count'],
                    'answers_count' => $row['answers_count'],
                ));
            }

            if ($contacts_not_updated) {
                $this->updateByField(array(
                    'contact_id' => array_keys($contacts_not_updated),
                    'hub_id' => $hub_id,
                ), array(
                    'comments_count' => 0,
                    'answers_count' => 0,
                ));
            }
        }
    }

    /**
     * Update comments_count of authors
     * Use SQL queries to update, no foreach`s as updateCounts, could to be more efficient
     * Also take into account cases when hub_author table record not exists yet, but there are hub_comment records
     * @param int|int[]|null $hub_id        Hub ID or list of hub ID or NULL (all hubs for which there are hub_comment records)
     * @param int|int[]|null $contact_id    Contact ID or list of contact ID or NULL (all contacts for which there are hub_comments records)
     * @param string         $type          How to update: only for existing records in hub_author OR with inserting new records.
     *                                          Variants 'existing', 'all'. Default is 'all'
     *
     * @return bool                         Invalid input param(params)
     */
    public function updateCommentCounts($hub_id = null, $contact_id=null, $type = 'all')
    {
        // check $type input parameter
        if ($type !== 'all' && $type !== 'existing') {
            return false;
        }

        // prepare SQL filter by hub IDs with checking $hub_id input parameter
        if ($hub_id === null) {
            $hub_filter = '';
            $hub_ids = array();
        } else {
            $hub_ids = waUtils::toIntArray($hub_id);
            $hub_ids = waUtils::dropNotPositive($hub_ids);
            if ($hub_ids) {
                $hub_filter = ' AND c.hub_id IN(:hub_ids)';
            } else {
                return false;
            }
        }

        // prepare SQL filter by contact IDS with checking $contact_id input parameter
        if ($contact_id === null) {
            $contact_filter = '';
            $contact_ids = array();
        } else {
            $contact_ids = waUtils::toIntArray($contact_id);
            $contact_ids = waUtils::dropNotPositive($contact_ids);
            if ($contact_ids) {
                $contact_filter = ' AND c.contact_id IN(:contact_ids)';
            } else {
                return false;
            }
        }

        // Repair comments_count counters
        // insert case - make extra LEFT JOIN to define which records need to be inserted

        if ($type === 'all') {
            $sql = "
                INSERT IGNORE INTO hub_author (`hub_id`, `contact_id`, `topics_count`, `comments_count`, `answers_count`, `votes_up`, `votes_down`, `rate`)
                
                SELECT c.hub_id, c.contact_id, 0, COUNT(*) AS comments_count, 0, 0, 0, 0
                    FROM hub_comment c
                    LEFT JOIN hub_author a ON a.contact_id = c.contact_id AND a.hub_id = c.hub_id
                    WHERE c.status='approved'
                        {$hub_filter}
                        {$contact_filter}
                GROUP BY c.contact_id, c.hub_id
            ";

            $this->exec($sql, array(
                'hub_ids' => $hub_ids,
                'contact_ids' => $contact_ids
            ));
        }

        // Repair comments_count counters
        // update case (when in hub_author proper record exists)
        // inner select get counters grouped by hub_id and contact_id
        // than do update in hub_author table for proper hub_id and contact_id

        $sql = "
                UPDATE hub_author a 
                JOIN (
                  SELECT c.contact_id, c.hub_id, COUNT(*) AS comments_count
                        FROM hub_comment c
                        WHERE c.status='approved' 
                            {$hub_filter}
                            {$contact_filter} 
                        GROUP BY c.contact_id, c.hub_id
                ) r
                ON a.contact_id = r.contact_id AND a.hub_id = r.hub_id
                SET a.comments_count = r.comments_count
        ";

        $this->exec($sql, array(
            'hub_ids' => $hub_ids,
            'contact_ids' => $contact_ids
        ));

        return true;

    }

    /**
     * Change `votes_up`, `votes_down` and `rate` stats of a given contact in given hub.
     *
     * @param $entity_type      string  'topic', 'solution' (or 'answer') or 'comment'
     * @param $hub_id           int
     * @param $contact_id       int
     * @param $number_of_votes  int     +1 for upvotes, -1 for downvotes
     * @param $new_voter        bool    true to increase `votes_up` or `votes_down`; false to subtract from one and add to the other.
     */
    public function receiveVote($entity_type, $hub_id, $contact_id, $number_of_votes, $new_voter=true)
    {
        // Are kudos turned on for this hub?
        $hub_params_model = new hubHubParamsModel();
        $hub_params = $hub_params_model->getParams($hub_id);
        if (empty($hub_params['kudos'])) {
            return;
        }

        // Make sure all the coefficients are present
        $hub_params += array(
            'kudos_per_topic'   => 0,
            'kudos_per_comment' => 0,
            'kudos_per_answer'  => 0,
        );

        $rate_change = 0;
        $votes_up_change = 0;
        $votes_down_change = 0;

        switch($entity_type) {
            case 'topic':
                $rate_change = $hub_params['kudos_per_topic']*$number_of_votes;
                break;
            case 'solution':
            case 'answer':
                $rate_change = $hub_params['kudos_per_answer']*$number_of_votes;
                break;
            case 'comment':
                $rate_change = $hub_params['kudos_per_comment']*$number_of_votes;
                break;
        }

        if (!$rate_change) {
            return;
        }

        if ($new_voter) {
            if ($rate_change > 0) {
                $votes_up_change = 1;
            } else {
                $votes_down_change = 1;
            }
        } else {
            $rate_change *= 2; // When user changed their mind, compensate for previous vote.
            if ($rate_change > 0) {
                $votes_up_change = 1;
                $votes_down_change = -1;
            } else {
                $votes_down_change = 1;
                $votes_up_change = -1;
            }
        }

        $this->ensureAuthorExists($hub_id, $contact_id);
        $sql = "UPDATE hub_author
                SET votes_down = votes_down + ({$votes_down_change}),
                    votes_up = votes_up + ({$votes_up_change}),
                    rate = rate + ({$rate_change})
                WHERE hub_id=?
                    AND contact_id=?";
        $this->exec($sql, array($hub_id, $contact_id));
    }

    public function getList($fields='*', $options=array(), &$total_count=null)
    {
        $offset = (int) ifset($options['offset'], 0);
        $limit = (int) ifset($options['limit'], wa('hub')->getConfig()->getOption('authors_per_page'));
        $fields = array_fill_keys(explode(',', $fields), true);

        $available_hub_ids = array_keys(wa('hub')->getConfig()->getAvailableHubs(hubRightConfig::RIGHT_READ));
        if (empty($available_hub_ids) || (!empty($options['hub_id']) && !in_array($options['hub_id'], $available_hub_ids))) {
            return array();
        }

        if (!empty($options['hub_id'])) {
            $hub_id = (int) $options['hub_id'];
            $sql = "SELECT SQL_CALC_FOUND_ROWS *
                    FROM hub_author AS a
                    WHERE hub_id={$hub_id}
                    ORDER BY rate DESC
                    LIMIT {$offset}, {$limit}";
        } else {
            $sql = "SELECT SQL_CALC_FOUND_ROWS
                            a.contact_id,
                            sum(a.topics_count) AS topics_count,
                            SUM(a.comments_count) AS comments_count,
                            SUM(a.answers_count) AS answers_count,
                            SUM(a.votes_up) AS votes_up,
                            SUM(a.votes_down) AS votes_down,
                            SUM(a.rate) AS rate
                    FROM hub_author AS a
                    WHERE hub_id IN (:hub_ids)
                    GROUP BY a.contact_id
                    ORDER BY rate DESC
                    LIMIT {$offset}, {$limit}";
        }

        $result = $this->query($sql, array('hub_ids' => $available_hub_ids))->fetchAll('contact_id');
        if (!$result) {
            return array();
        }

        $total_count = (int) $this->query('SELECT FOUND_ROWS()')->fetchField();

        // Data from wa_contact
        $contact_fields = 'id,name,firstname,middlename,lastname,photo,email,photo_url_50,photo_url_32,photo_url_20';
        $empty_contact = array_fill_keys(explode(',', $contact_fields), '');
        $contacts = hubHelper::getAuthor(array_keys($result));

        // Data from hub_staff
        if (!empty($options['hub_id']) && !empty($fields['badge'])) {
            $staff_model = new hubStaffModel();
            $staff = $staff_model->getByField(array(
                'hub_id' => $options['hub_id'],
                'contact_id' => array_keys($result),
            ), 'contact_id');
            foreach($result as &$a) {
                if (!empty($staff[$a['contact_id']])) {
                    $a['badge'] = $staff[$a['contact_id']]['badge'];
                    $a['badge_color'] = $staff[$a['contact_id']]['badge_color'];
                    if (!empty($staff[$a['contact_id']]['name'])) {
                        $a['name'] = $staff[$a['contact_id']]['name'];
                    }
                } else {
                    $a['badge'] = '';
                    $a['badge_color'] = '';
                }
            }
            unset($a);
        }

        // Stats from all hubs separately
        if (empty($options['hub_id']) && !empty($fields['stats_by_hub'])) {
            $empty_stats = $this->getEmptyRow();
            unset($empty_stats['contact_id']);
            $empty_stats = array_fill_keys($available_hub_ids, $empty_stats);
            foreach($available_hub_ids as $hid) {
                $empty_stats[$hid]['hub_id'] = $hid;
            }

            $sql = "SELECT * FROM hub_author WHERE contact_id IN (?)";
            foreach($this->query($sql, array(array_keys($result))) as $row) {
                $contact_id = $row['contact_id'];
                unset($row['contact_id']);
                empty($result[$contact_id]['stats_by_hub']) && $result[$contact_id]['stats_by_hub'] = array();
                $result[$contact_id]['stats_by_hub'][$row['hub_id']] = $row;
            }
        }

        // Combine all the data into one $result
        foreach($result as &$row) {
            $row += ifset($contacts[$row['contact_id']], $empty_contact);

            // Only keep requested fields in resulting set
            if (empty($fields['*'])) {
                $row = array_intersect_key($row, $fields);
            }

            // Make sure all hub keys exist in 'stats_by_hub' for all contacts
            if (empty($options['hub_id']) && !empty($fields['stats_by_hub'])) {
                $row['stats_by_hub'] = ifempty($row['stats_by_hub'], array()) + $empty_stats;
            }
        }
        unset($row);

        return $result;
    }

    protected function ensureAuthorExists($hub_id, $contact_id)
    {
        $sql = "INSERT IGNORE INTO hub_author SET hub_id=?, contact_id=?";
        $this->exec($sql, array($hub_id, $contact_id));
    }

    public function countAuthors()
    {
        return (int) $this->query("SELECT COUNT(DISTINCT contact_id) FROM hub_author")->fetchField();
    }
}

