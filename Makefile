DB=photos.db
PHOTOS="./photos"

all: db_show photos exif

$(DB):
	mkdir -p $(PHOTOS)
	php exif.php

db_show: $(DB)
	sqlite3 $(DB) '.schema'

photos: $(DB)
	sqlite3 $(DB) 'SELECT * FROM photos'

exif: $(DB)
	sqlite3 $(DB) 'SELECT p.name, e.key, e.value FROM exif e JOIN photos p ON p.id = e.photo_id GROUP BY e.photo_id'

clean:
	rm -f $(DB)

clean-all: clean
	rm -Rf $(PHOTOS)
