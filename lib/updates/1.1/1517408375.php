<?php

$m = new waModel();

// If there is at least one comment deleted since last update,
// we need to fix DB
$sql = "SELECT 1
        FROM wa_log AS l
          JOIN hub_comment AS c
            ON l.params=c.id
        WHERE c.status = 'deleted'
          AND l.app_id = 'hub'
          AND l.action = 'comment_delete'
          AND l.datetime > '2018-01-31 00:00:01'
        LIMIT 1";
if (!$m->query($sql)->fetchField()) {
    waLog::log('Hub update '.__FILE__.': no comments to restore.');
    return;
}

try {

    //
    // Select all comments properly deleted (all time)
    // and fetch ids into temporary table.
    //
    $m->exec("
        CREATE TEMPORARY TABLE hub_comment_ids (
            `id` int(11) NOT NULL PRIMARY KEY
        )"
    );
    $m->exec("
        INSERT IGNORE INTO hub_comment_ids
        SELECT DISTINCT c.id
        FROM wa_log AS l
          JOIN hub_comment AS c
            ON l.params=c.id
        WHERE c.status = 'deleted'
          AND l.app_id = 'hub'
          AND l.action = 'comment_delete'
    ");

    $count = $m->query('SELECT COUNT(*) FROM hub_comment_ids')->fetchField();
    waLog::log('Hub update '.__FILE__.': '.$count.' comments found properly deleted.');
    $count = $m->query("SELECT COUNT(*) FROM hub_comment WHERE status='deleted'")->fetchField();
    waLog::log('Hub update '.__FILE__.': '.$count.' comments found deleted total.');

    //
    // Restore all comments not properly deleted (i.e. without record in wa_log
    // and therefore not present in `hub_comment_ids`).
    //

    $result = $m->query("
        UPDATE hub_comment AS c
          LEFT JOIN hub_comment_ids AS ci
            ON ci.id=c.id
        SET c.status='approved'
        WHERE c.status = 'deleted'
          AND ci.id IS NULL
    ");
    waLog::log('Hub update '.__FILE__.': '.$result->affectedRows().' comments restored from deleted to approved status.');
} catch (Exception $e) {

}
