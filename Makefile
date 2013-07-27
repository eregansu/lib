prefix ?= /usr/local
phpincdir ?= /usr/share/php

all: tests docs

tests:
	cd t && $(MAKE)

docs:
	@if test -d ../docs ; then \
		rm -rf ../docs/lib ; \
		mkdir ../docs/lib ; \
		echo "Generating documentation in ../docs/lib" >&2 ; \
		php -f ../gendoc/gendoc.php -- -o ../docs/lib . || exit $? ; \
	else \
		echo "Warning: not generating docs because ../docs does not exist" >&2 ; \
	fi

install: all
	mkdir -p $(DESTDIR)$(phpincdir)/eregansu
	cp -Rf . $(DESTDIR)$(phpincdir)/eregansu

clean:

.PHONY: all tests docs clean install
