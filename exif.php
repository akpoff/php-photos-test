<?php

define('DB_FILE', 'exif.db');
define('S3_BUCKET', 'http://s3.amazonaws.com/waldo-recruiting');
define('PHOTO_CACHE', 'photos');

/*
 * Download data from s3 bucket and work through it
 *
 * @TODO Implement AWS pagination
 * AWS only returns 1000 elemnts per query
 * http://docs.aws.amazon.com/AmazonS3/latest/dev/ListingKeysUsingAPIs.html
 *
 */
function main($concurrency) {
    print("Running with $concurrency sessions.". PHP_EOL);

    try {
        $db = initDb();
        $xml = getXml();
        echo "Found " . count($xml->Contents) . " 'Contents' entries" . PHP_EOL;

        $count = 0;
        $photo_ids = [];
        $files = [];
        foreach ($xml as $elem) {
            if ($count == 0) {
                $photo_ids = [];
                $files = [];
            }
            if (isset($elem->Key)) {
                $file = $elem->Key->__toString();
                $db->exec("INSERT INTO photos (name) VALUES ('$file')");
                $photo_ids["$file"] = $db->lastInsertRowID();

                $files[] = $file;
            }

            // Call getPhotos when count of $files matches CONCURRENCY
            if (count($files) == $concurrency) {
                $filepaths = getPhotos($files);

                foreach ($filepaths as $file=>$filepath) {
                    handleExif($db, $photo_ids[$file], $filepath);
                }
                $count = 0;
            } else {
                $count++;
            }
        }

        if (!empty($files)) {
            $filepaths = getPhotos($files);

            foreach ($filepaths as $file=>$filepath) {
                handleExif($db, $photo_ids[$file], $filepath);
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
 * Download requested photos and verify each is
 * an image of type jpeg or tiff
 *
 * @return $filepaths
 */
function getPhotos($files) {
    if (empty($files)) return [];

    $filepaths = [];
    $chs = array();
    $cmh = curl_multi_init();
    foreach ($files as $file) {
        $filepath = PHOTO_CACHE . "/$file";
        if (file_exists($filepath)) {
            $filepaths[$file] = $filepath;
            break;
        }

        $ch = curl_init(S3_BUCKET . "/$file");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_multi_add_handle($cmh, $ch);
        $chs[$file] = $ch;
    }

    $running=null;
    do {
        curl_multi_exec($cmh, $running);
    } while ($running > 0);

    foreach ($chs as $file=>$ch) {
        $filepath = PHOTO_CACHE . "/$file";
        file_put_contents($filepath, curl_multi_getcontent($ch));
        curl_multi_remove_handle($cmh, $ch);
        curl_close($ch);

        // Check whether we have a valid image file
        $mime = mime_content_type($filepath);
        if ($mime != "image/jpeg" && $mime != "image/tiff") {
            trigger_error("Bad file from server moved to: photos/bad/$file", E_USER_WARNING);
            rename($filepath, PHOTO_CACHE . "/bad/$file");

        } else {
            $filepaths[$file] = $filepath;
        }

    }
    curl_multi_close($cmh);

    return $filepaths;
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
