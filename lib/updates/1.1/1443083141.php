<?php

$model = new waModel();
$model->exec("
  CREATE TABLE IF NOT EXISTS `hub_topic_params` (
    `topic_id` int(11) NOT NULL,
    `name` varchar(64) NOT NULL,
    `value` varchar(255) NOT NULL,
    PRIMARY KEY (`topic_id`,`name`),
    KEY `name` (`name`)
  ) ENGINE=MyISAM DEFAULT CHARSET=utf8
");
