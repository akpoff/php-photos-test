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

How to Run
----------

The Makefile has 4 targets of interest:

+ all - db_show photos and exif in sequence
+ db_show - show the db scehma
+ photos - show the names of all the images in the database
+ exif - show the names of all the images and one EXIF key and value
