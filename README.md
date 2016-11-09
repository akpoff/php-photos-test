Photo EXIF Data to Sqlite3 DB
=============================

A short project to download files from an S3 bucket and store the EXIF
data in an SQLite3 db.

Requirements
------------

+ php5.6
+ php5.6-curl
+ php5.6-sqlite3
+ php5.6-xml
+ make

Platforms
---------

Tested on:

+ Mac OS 10.11 with php 5.5.38

+ OpenBSD 6.0 -current with php 5.6.27

+ Ubuntu 16.04 with php 5.6.27

How to Run
----------

The Makefile has 7 targets of interest:

+ all - run the script and validate (default)
+ clean - remove db but leave photo cache
+ clean-all - remove db and photo cache
+ db_show - run the script and show the db schema
+ photos - run the script and show the names of all the images in the database
+ validate - run the script and validate the file counts
+ exif - show the names of all the images and one EXIF key and value
+ test - Runs the script with various concurrency levels and times them

Concurrency
-----------

The default concurrency setting is 10 simultaneous downloads. The
value can be changed on the command line:

```
CONCURRENCY=20 make all
```

test
----

The `test` target will run the script with the following concurrency
levels:

+ CONCURRENCY=1

+ CONCURRENCY=2

+ CONCURRENCY=5

+ CONCURRENCY=10

+ CONCURRENCY=20
