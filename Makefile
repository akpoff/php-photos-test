DB=exif.db
PHOTOS="./photos"
CONCURRENCY?=10
ERROR_LEVEL?=E_WARNING

.SUFFIXES: .db .php

.php.db:
	mkdir -p $(PHOTOS)/bad
	php -r "error_reporting($(ERROR_LEVEL)); require 'exif.php'; main($(CONCURRENCY));"

all: validate

clean:
	@rm -f $(DB)

clean-all: clean
	@rm -Rf $(PHOTOS)

db_show: $(DB)
	sqlite3 $(DB) '.schema'

photos: $(DB)
	sqlite3 $(DB) 'SELECT * FROM photos'

exif: $(DB)
	sqlite3 -header $(DB) 'SELECT p.name, e.key, e.value, e.base64_encoded FROM exif e JOIN photos p ON p.id = e.photo_id GROUP BY e.photo_id'
	sqlite3 -header $(DB) 'SELECT p.name, e.key, e.value, e.base64_encoded FROM exif e JOIN photos p ON p.id = e.photo_id'

validate: $(DB)
	@echo Count files for accuracy
	@find photos -type f | wc -l

test: test-1 test-2 test-5 test-10 test-20

test-1:
	@make clean-all > /dev/null
	@CONCURRENCY=1 ERROR_LEVEL=E_ERROR time make -s validate
	@echo ""

test-2:
	@make clean-all > /dev/null
	@CONCURRENCY=2 ERROR_LEVEL=E_ERROR time make -s validate
	@echo ""

test-5:
	@make clean-all > /dev/null
	@CONCURRENCY=5 ERROR_LEVEL=E_ERROR time make -s validate
	@echo ""

test-10:
	@make clean-all > /dev/null
	@CONCURRENCY=10 ERROR_LEVEL=E_ERROR time make -s validate
	@echo ""

test-20:
	@make clean-all > /dev/null
	@CONCURRENCY=20 ERROR_LEVEL=E_ERROR time make -s validate
	@echo ""
