elibdir = $(platformdir)/lib

SUBDIRS = rdf t

dist_elib_DATA = *.php

install-data-hook:
	rm -f common.install.php
	sed -e "s@^.*%%version%%.*\$$@define('__EREGANSU__', '$(VERSION)');@" < $(srcdir)/common.php > common.install.php
	$(INSTALL) -d $(DESTDIR)$(elibdir)
	$(INSTALL) -m 644 common.install.php $(DESTDIR)$(elibdir)/common.php
	rm -f common.install.php

