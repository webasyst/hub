<?php
//1417431415
$model = new waModel();
try {
    $model->query("SELECT sort FROM hub_type WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `hub_type` ADD `sort` INT NOT NULL DEFAULT '0'");
}
try {
    $model->query("SELECT sort FROM hub_hub WHERE 0");
} catch (waDbException $e) {
    $model->exec("ALTER TABLE `hub_hub` ADD `sort` INT NOT NULL DEFAULT '0'");
}
