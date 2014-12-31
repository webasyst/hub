<?php

$model = new waModel();
$model->exec('DELETE tc FROM hub_topic_categories tc JOIN hub_category c ON tc.category_id = c.id WHERE c.type = 1');