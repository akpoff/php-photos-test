DB=exif.db
PHOTOS="./photos"
CONCURRENCY?=10

.SUFFIXES: .db .php

.php.db:
	mkdir -p $(PHOTOS)/bad
	php -r "require 'exif.php'; main($(CONCURRENCY));"

all: db_show photos exif

db_show: $(DB)
	sqlite3 $(DB) '.schema'

photos: $(DB)
	sqlite3 $(DB) 'SELECT * FROM photos'

exif: $(DB)
	sqlite3 -header $(DB) 'SELECT p.name, e.key, e.value, e.base64_encoded FROM exif e JOIN photos p ON p.id = e.photo_id GROUP BY e.photo_id'
	sqlite3 -header $(DB) 'SELECT p.name, e.key, e.value, e.base64_encoded FROM exif e JOIN photos p ON p.id = e.photo_id'

clean:
	rm -f $(DB)

clean-all: clean
	rm -Rf $(PHOTOS)
