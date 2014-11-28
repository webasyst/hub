<?php

$model = new waModel();
$model->exec("ALTER TABLE hub_topic CHANGE badge badge ENUM('archived','answered','pending','accepted','confirmed','inprogress','complete','fixed','rejected') NULL DEFAULT NULL");