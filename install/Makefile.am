if MODNETCONF_OPT
  MODNETCONF_OPT=mod_netconf
else
  MODNETCONF_OPT=
endif
if PYANG_OPT
  PYANG_OPT=pyang
else
  PYANG_OPT=
endif

SUBDIRS=$(MODNETCONF_OPT) $(PYANG_OPT)

RPMDIR = RPMBUILD

dist-hook:
	find "$(distdir)" -name .gitignore -exec rm {} \;

install-data-hook:
	[ -e "$(httpdconfddir)" ] || mkdir -p "$(httpdconfddir)"; \
	[ -e "$(httpdconfddir)/$(webguiname).conf" ] && mv "$(httpdconfddir)/$(webguiname).conf" "$(httpdconfddir)/$(webguiname).conf.back"; \
	cp netopeergui.conf $(httpdconfddir)/$(webguiname).conf;

if MAKE_RPMS

RPMFILENAME=$(PACKAGE_NAME)-$(VERSION)
.PHONY: rpm
rpm:
	rm -rf "$(RPMDIR)/SOURCES/$(RPMFILENAME)"
	mkdir -p $(RPMDIR)/BUILD/ $(RPMDIR)/SRPMS/ $(RPMDIR)/RPMS/ $(RPMDIR)/SOURCES
	make ${AM_MAKEFLAGS} distdir='$(RPMDIR)/SOURCES/$(RPMFILENAME)' distdir
	cp apache/src/mod_netconf.conf "$(RPMDIR)/SOURCES/$(RPMFILENAME)"
	find "$(RPMDIR)" -name '*.back' -exec rm -f {} \;
	( cd "$(RPMDIR)/SOURCES/"; tar -z -c -f $(RPMFILENAME)-$(RELEASE).tar.gz $(RPMFILENAME); rm -rf $(RPMFILENAME); )
	$(RPMBUILD) -ba $(PACKAGE_NAME).spec --define "_topdir `pwd`/$(RPMDIR)";

rpm-clean:
	rm -rf $(RPMDIR)
else
endif
