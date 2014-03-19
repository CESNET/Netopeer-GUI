#                                               -*- Autoconf -*-
# Process this file with autoconf to produce a configure script.

AC_PREREQ([2.63])
AC_INIT([netopeergui], [1.0.0], [cejkat@cesnet.cz])
AM_INIT_AUTOMAKE([foreign silent-rules tar-pax])

USERNAME=`git config --get user.name`
USERMAIL=`git config --get user.email`
AC_SUBST(USERNAME)
AC_SUBST(USERMAIL)

RELEASE=1
AC_SUBST(RELEASE)

AC_ARG_VAR(httpdmoduledir, [Installation directory for Apache module, leave unset to use Apache default.])
AC_SUBST(httpdmoduledir)

if test "x$httpdconfddir" == x; then
	httpdconfddir="/etc/httpd/conf.d"
fi
AC_ARG_VAR(httpdconfddir, [Directory for Apache configuration files [/etc/httpd/conf.d]])
AC_SUBST(httpdconfddir)

AC_ARG_WITH(modnetconf, AC_HELP_STRING([--without-modnetconf],[Do not compile and install mod_netconf.]), [], [with_modnetconf="yes"])
AM_CONDITIONAL([MODNETCONF_OPT], [test "x$with_modnetconf" != "xno"])

AC_ARG_WITH(pyang, AC_HELP_STRING([--without-pyang],[Do not install pyang plugins.]), [], [with_pyang="yes"])
AM_CONDITIONAL([PYANG_OPT], [test "x$with_pyang" != "xno"])

# Check for rpmbuild
AC_CHECK_PROG(RPMBUILD, [rpmbuild], [rpmbuild], [""])
AC_CHECK_PROG(HTTPD, [httpd], [yes], [""])
AC_PATH_PROGS(APACHECTL, apachectl apache2ctl, no, [$PATH:/usr/sbin])

AC_PATH_PROGS(PHP, php, [no], [$PATH])
if test "x$PHP" != xno; then
	for i in dom json xml pdo intl sysvsem; do
		AC_MSG_CHECKING([for php module $i])
		php -m | grep "$i" > /dev/null && AC_MSG_RESULT([yes]) || AC_MSG_ERROR([not found])
	done
else
AC_MSG_ERROR([php was not found])
fi
AC_SUBST(PHP)

AC_CHECK_PROG(PYANG, [pyang], [yes], [""])
AC_PATH_PROGS(PYTHON, [python], [no], [$PATH])
if test "x$PYTHON" == xno; then
	AC_MSG_ERROR(["python was not found."])
fi

AC_ARG_VAR(pyplugindir, [Installation directory for pyang plugin])
if test "x$pyplugindir" == x; then
pyplugindir=[$(${PYTHON} -c 'from distutils.sysconfig import get_python_lib; print get_python_lib()+"/pyang/plugins"')]
fi
AC_SUBST(pyplugindir)

# Check for rpmbuild
AC_CHECK_PROG(RPMBUILD, rpmbuild, rpmbuild, [""])
RPM_RELEASE=1
AC_SUBST(RPM_RELEASE)
AM_CONDITIONAL(MAKE_RPMS, test x$RPMBUILD != x)

# Checks for libraries.

# Checks for header files.

# Checks for typedefs, structures, and compiler characteristics.

# Checks for library functions.

AC_CONFIG_SUBDIRS(mod_netconf)
AC_CONFIG_FILES([Makefile
		pyang/Makefile])
AC_OUTPUT

echo
echo
echo "------------------------------------------------------------------------"
echo "$PACKAGE $VERSION"
echo "------------------------------------------------------------------------"
echo
echo
echo "Configuration Options Summary:"
echo
echo "Documentation..........: ${build_doc}"
echo
echo "Compilation............: make (or gmake)"
echo "  asxs.................: $ASXS"
echo "  LIBS.................: $LIBS"
echo "  CFLAGS...............: $CFLAGS"
echo
echo "Components"
echo "  mod_netconf..........: $with_modnetconf"
echo "  pyang plugins........: $with_pyang"
echo
echo "Installation...........: make install (as root if needed, with 'su' or 'sudo')"
echo "  prefix...............: $prefix"
echo "  python module dir....: $pyplugindir"
echo "  webgui dir...........: $webguidir"
echo "  webgui name..........: $webguiname"
echo "  httpdmoduledir.......: $(test -z "$httpdmoduledir" && echo "Apache default path" || echo "$httpdmoduledir")"
echo "  httpdconfddir........: $(test -z "$httpdconfddir" && echo "Apache default path" || echo "$httpdconfddir")"
echo "  run composer install.: $composerinstall"
echo

