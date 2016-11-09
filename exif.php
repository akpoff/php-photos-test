<?php

define('DB_FILE', 'photos.db');
define('S3_BUCKET', 'http://s3.amazonaws.com/waldo-recruiting');
define('PHOTO_CACHE', 'photos');

main();

function main() {
    try {
        $db = initDb();
        $xml = getXml();

        foreach ($xml as $elem) {
            if (isset($elem->Key)) {
                $db->exec("INSERT INTO photos (name) VALUES ('$elem->Key')");
                $photo_id = $db->lastInsertRowID();
                $filepath = getPhoto("$elem->Key");
                handleExif($db, $photo_id, $filepath);
            }
        }

        $db->close();
    } catch (Exception $e) {
        print("Caught error: " . $e);
        die("Terminating application.");
    }
}

/*
 * Get the EXIF data and put in db
 *
 */
function handleExif($db, $photo_id, $filepath) {
    if ($filepath) {
        $exif = exif_read_data($filepath, 'EXIF', true);
        if (isset($exif['EXIF'])) {
            foreach ($exif['EXIF'] as $key=>$value) {
                $pre = $value;

                // serialize value if it's an array
                if (is_array($value)) {
                    $pre = serialize($value);
                }

                if (ctype_print($pre) || is_numeric($pre)) {
                    $value_escape = $db->escapeString($pre);
                    $values[] = "($photo_id, '$key', '$value_escape', 0)";
                } elseif (!empty($pre)) {
                    // Base64 encode non-printable characters
                    // No need to escape the string since we're encoding it
                    $enc = base64_encode($pre);
                    $values[] = "($photo_id, '$key', '$enc', 1)";
                }
            }
            if (!empty($values)) {
                // Using a prepared statement here might be a bit safer than string encoding
                $sql = "INSERT INTO exif (photo_id, key, value, base64_encoded) VALUES " . implode(',',$values);
                $db->exec($sql);
            } else {
                print("No EXIF data found for: " . $filepath . PHP_EOL);
            }
        } else {
            print("No EXIF data found for: " . $filepath. PHP_EOL);
        }
    }
}

/*
 * Download requested photo and verify is
 * an image of type jpeg or tiff
 *
 * @return $filepath || False
 */
function getPhoto($file) {
    $filepath = PHOTO_CACHE . "/" . $file;

    if (!file_exists($filepath)) {
        $ch = curl_init(S3_BUCKET . "/$file");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        $data = curl_exec($ch);

        if ($data) {
            $fp = fopen($filepath, 'w');
            fwrite($fp, $data);
            fclose($fp);

            // Check whether we have a valid image file
            $mime = mime_content_type($filepath);
            if ($mime != "image/jpeg" && $mime != "image/tiff") {
                print("Bad file from server: $file" . PHP_EOL);
                unlink($filepath);

                return false;
            } else {
                return $filepath;
            }
        } else {
            return false;
        }
    } else {
        // Found in cache
        return $filepath;
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
        $db->exec('CREATE TABLE exif (id INTEGER PRIMARY KEY, photo_id INTEGER, key STRING, value TEXT, base64_encoded INTEGER)');
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
