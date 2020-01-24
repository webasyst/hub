<?php
class hubRepairActions extends waViewActions
{
    protected function defaultAction()
    {
        echo "<b>Update kudos for one specific hub</b><br>\n";
        echo "?module=repair&action=kudos&hub_id=\n<br>\n<br>\n";

        echo "<b>Update authors counters (topics, comments, answers) for one specific hub</b><br>\n";
        echo "?module=repair&action=authorCounts&hub_id=\n<br>\n";

        echo "<br>\n";
        echo "<b>Update authors comment counts</b><br>\n";
        echo "&mdash;&nbsp;For <i>all hubs</i>:<br>\n";
        echo "&nbsp;&nbsp;?module=repair&action=authorCommentCounts<br>\n";
        echo "&mdash;&nbsp;For one <i>specific hub</i>:<br>\n";
        echo "&nbsp;&nbsp;?module=repair&action=authorCommentCounts&hub_id=<br>\n";
        echo "&mdash;&nbsp;For several <i>specific hubs</i>:<br>\n";
        echo "&nbsp;&nbsp;?module=repair&action=authorCommentCounts&hub_id[]=&hub_id[]=&hub_id[]=<br>\n";
        echo "&mdash;&nbsp;For several <i>specific hubs</i>:<br>\n";
        echo "&nbsp;&nbsp;?module=repair&action=authorCommentCounts&hub_id=hub_id_1,hub_id_2,hub_id_3<br>\n";
        echo "<br>\n";

        echo "<b>Update topic comment count for one specific hub</b><br>\n";
        echo "?module=repair&action=topicCommentCounts\n";
        exit;
    }

    protected function topicCommentCountsAction()
    {
        $sql = "UPDATE hub_topic t JOIN (
                    SELECT t.id, t.comments_count, COUNT(c.id) cnt
                    FROM hub_topic t
                    LEFT JOIN hub_comment c ON t.id = c.topic_id
                        AND c.status = 'approved'
                    GROUP BY t.id
                    HAVING comments_count != cnt
                ) c SET t.comments_count = c.cnt
                WHERE t.id = c.id";
        wao(new waModel())->query($sql);
        echo "done!";
        exit;
    }

    protected function kudosAction()
    {
        $hub_id = waRequest::get('hub_id', 0, 'int');
        if (!$hub_id) {
            die('no hub_id');
        }

        $m = $hub_params_model = new hubHubParamsModel();
        $hub_params = $hub_params_model->getParams($hub_id);
        if (empty($hub_params['kudos'])) {
            $sql = "UPDATE hub_author AS a
                    SET a.rate=0,
                        a.votes_up=0,
                        a.votes_down=0
                    WHERE a.hub_id=?";
            $m->exec($sql, $hub_id);
            echo 'all kudos are reset to 0 for hub_id='.$hub_id;
            exit;
        }

        $hub_params += array(
            'kudos_per_topic'   => 0,
            'kudos_per_comment' => 0,
            'kudos_per_answer'  => 0,
        );

        $sql = "UPDATE hub_author AS a
                SET a.rate=0,
                    a.votes_up=0,
                    a.votes_down=0
                WHERE a.hub_id=?
                    AND a.comments_count=0";
        $m->exec($sql, $hub_id);

        $sql = "UPDATE hub_author AS a
                    JOIN (
                        SELECT c.contact_id,
                            SUM(IF(c.solution, i:kudos_per_answer, i:kudos_per_comment)*v.vote*IF(v.contact_id != c.contact_id, 1, 0)) AS kudos,
                            SUM(IF(v.contact_id != c.contact_id AND v.vote > 0, 1, 0)) AS votes_up,
                            SUM(IF(v.contact_id != c.contact_id AND v.vote < 0, 1, 0)) AS votes_down
                        FROM hub_vote AS v
                            JOIN hub_comment AS c
                                ON v.comment_id=c.id
                        WHERE c.hub_id=i:hub_id
                        GROUP BY c.contact_id
                    ) AS k
                        ON k.contact_id=a.contact_id
                SET a.rate=k.kudos,
                    a.votes_up=k.votes_up,
                    a.votes_down=k.votes_down
                WHERE a.hub_id=i:hub_id";
        $m->exec($sql, array(
            'kudos_per_answer' => $hub_params['kudos_per_answer'],
            'kudos_per_comment' => $hub_params['kudos_per_comment'],
            'hub_id' => $hub_id,
        ));

        $sql = "UPDATE hub_author AS a
                    JOIN (
                        SELECT t.contact_id,
                            SUM(i:kudos_per_topic * v.vote * IF(v.contact_id != t.contact_id, 1, 0)) AS kudos,
                            SUM(IF(v.contact_id != t.contact_id AND v.vote > 0, 1, 0)) AS votes_up,
                            SUM(IF(v.contact_id != t.contact_id AND v.vote < 0, 1, 0)) AS votes_down
                        FROM hub_vote AS v
                            JOIN hub_topic AS t
                                ON v.topic_id=t.id
                                    AND v.comment_id IS NULL
                        WHERE t.hub_id=i:hub_id
                        GROUP BY t.contact_id
                    ) AS k
                        ON k.contact_id=a.contact_id
                SET a.rate = a.rate + k.kudos,
                    a.votes_up = a.votes_up + k.votes_up,
                    a.votes_down = a.votes_down + k.votes_down
                WHERE a.hub_id=i:hub_id";
        $m->exec($sql, array(
            'kudos_per_topic' => $hub_params['kudos_per_topic'],
            'hub_id' => $hub_id,
        ));

        echo "Author kudos recalculated for hub_id={$hub_id}\n<br>\n";
        $this->authorCountsAction();
    }

    protected function authorCountsAction()
    {
        $hub_id = waRequest::get('hub_id', 0, 'int');
        if (!$hub_id) {
            die('no hub_id');
        }

        $author_model = new hubAuthorModel();
        $author_model->updateByField(array(
            'hub_id' => $hub_id,
        ), array(
            'topics_count' => 0,
            'comments_count' => 0,
            'answers_count' => 0,
        ));

        $author_model->updateCounts('all', $hub_id);
        echo "Author topic anc comment counts recalculated for hub_id=".$hub_id;
        exit;
    }

    protected function authorCommentCountsAction()
    {
        $author_model = new hubAuthorModel();

        $hub_id_param = waRequest::get('hub_id');

        if (is_array($hub_id_param) || $hub_id_param === null) {
            $hub_id = $hub_id_param;
        } elseif (wa_is_int($hub_id_param) && $hub_id_param > 0) {
            $hub_id = $hub_id_param;
        } elseif (is_scalar($hub_id_param)) {
            $hub_id = array_map('trim', explode(',', $hub_id_param));
        } else {
            die("Invalid hub_id parameter");
        }

        $ok = $author_model->updateCommentCounts($hub_id);

        if (!$ok) {
            die("Invalid hub_id parameter");
        }

        if ($hub_id === null) {
            echo "Author comment counts recalculated for all hubs";
        } else {
            echo "Author comment counts recalculated for hub_id=" . join(',', (array)$hub_id);
        }
        exit;
    }

}

