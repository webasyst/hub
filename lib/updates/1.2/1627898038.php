<?php

$hub_category_og = <<<SQL
CREATE TABLE IF NOT EXISTS `hub_category_og` (
  `category_id` int(11) NOT NULL,
  `property` varchar(255) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`category_id`, `property`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
SQL;

$model = new waModel();
$model->exec($hub_category_og);

$hub_topic_og = <<<SQL
CREATE TABLE IF NOT EXISTS `hub_topic_og` (
  `topic_id` int(11) NOT NULL,
  `property` varchar(255) NOT NULL,
  `content` text NOT NULL,
  PRIMARY KEY (`topic_id`, `property`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
SQL;
$model->exec($hub_topic_og);


try {
    $model->query("SELECT meta_title FROM hub_category WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `hub_category` ADD `meta_title` VARCHAR(255)");
}

foreach (['meta_keywords', 'meta_description'] as $field) {
    try {
        $model->query("SELECT $field FROM hub_category WHERE 0");
    } catch (waDbException $e) {
        $model->exec("ALTER TABLE `hub_category` ADD $field TEXT");
    }
}