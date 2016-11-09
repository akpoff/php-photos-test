<?php

define('DB_FILE', 'photos.db');
define('S3_BUCKET', 'http://s3.amazonaws.com/waldo-recruiting');

main();

function main() {
    try {
        $db = initDb();
        $xml = getXml();

        foreach ($xml as $elem) {
            if (isset($elem->Key)) {
                $db->exec("INSERT INTO photos (name) VALUES ('$elem->Key')");
                $photo_id = $db->lastInsertRowID();
                $db->exec("INSERT INTO exif (photo_id, key) VALUES ($photo_id, 'test')");
            }
        }

        $db->close();
    } catch (Exception $e) {
        print("Caught error: " . $e);
        die("Terminating application.");
    }
}

/*
 * Create and initialize db if it doesn't already exist.
 * Otherwise return the existing db.
 *
 * @return $db
 */
function initDb() {
    $is_new_db = !file_exists(DB_FILE);
    $db = new SQLite3(DB_FILE);
    if ($is_new_db) {
        $db->exec('CREATE TABLE photos (id INTEGER PRIMARY KEY, name STRING)');
        // @TODO: Normalize the keys into another table
        $db->exec('CREATE TABLE exif (id INTEGER PRIMARY KEY, photo_id INTEGER, key STRING, value STRING)');
    }

    return $db;
}

/*
 * Get the XML from the S3 bucket.
 *
 * @return $xml
 */
function getXml() {
    // Throwing an error here would be more elegant
    // But we can't continue without data
    $bucketxml = file_get_contents(S3_BUCKET) or die('Error: Cannot create object');
    $xml = simplexml_load_string($bucketxml) or die('Error: Cannot create object');

    return $xml;
}

?>
