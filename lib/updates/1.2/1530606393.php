<?php

$model = new waModel();

try {
    $model->exec("ALTER TABLE hub_page MODIFY content mediumtext NOT NULL");
} catch (waException $e) {

}