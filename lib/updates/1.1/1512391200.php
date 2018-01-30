<?php
$htm = new hubTopicModel();
$fields = $htm->getMetadata();

try {
    if (strtolower($fields['content']['type']) == 'text') {
        $htm->query('ALTER TABLE `hub_topic` MODIFY `content` LONGTEXT NOT NULL');
    }
} catch (Exception $e) {

}