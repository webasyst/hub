<?php
$model = new waModel();
$model->query("
    UPDATE hub_topic t JOIN (
        SELECT t.id, t.comments_count, COUNT(c.id) cnt 
        FROM hub_topic t 
        LEFT JOIN hub_comment c ON t.id = c.topic_id
            AND c.status = 'approved'
        GROUP BY t.id
        HAVING comments_count != cnt
    ) c SET t.comments_count = c.cnt
    WHERE t.id = c.id
");