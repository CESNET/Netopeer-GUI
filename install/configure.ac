#                                               -*- Autoconf -*-
# Process this file with autoconf to produce a configure script.

AC_PREREQ([2.63])
AC_INIT([netopeergui], [2.0.0], [cejkat@cesnet.cz])
AM_INIT_AUTOMAKE([foreign silent-rules tar-pax])

USERNAME=`git config --get user.name`
USERMAIL=`git config --get user.email`
AC_SUBST(USERNAME)
AC_SUBST(USERMAIL)

RELEASE=1
AC_SUBST(RELEASE)

if test "x$webguidir" == x; then
	webguidir=$(cd ..; dirname $PWD)
fi
AC_ARG_VAR(webguidir, [Installation directory for webgui [/var/www]])
AC_SUBST(webguidir)

if test "x$webguiname" == x; then
	webguiname=$(cd ..; basename $PWD)
fi
AC_ARG_VAR(webguiname, [Installation directory name for webgui [netopeergui]])
AC_SUBST(webguiname)

if test "x$httpdconfddir" == x; then
	httpdconfddir="/etc/httpd/conf.d"
fi
AC_ARG_VAR(httpdconfddir, [Directory for Apache configuration files [/etc/httpd/conf.d]])
AC_SUBST(httpdconfddir)

AC_ARG_WITH(netopeerguid, AC_HELP_STRING([--without-netopeerguid],[Do not compile and install netopeerguid.]), [], [with_netopeerguid="yes"])
AM_CONDITIONAL([NETOPEERGUID_OPT], [test "x$with_netopeerguid" != "xno"])

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

# Check for rpmbuild
AC_CHECK_PROG(RPMBUILD, rpmbuild, rpmbuild, [""])
RPM_RELEASE=1
AC_SUBST(RPM_RELEASE)
AM_CONDITIONAL(MAKE_RPMS, test x$RPMBUILD != x)

# Checks for libraries.

# Checks for header files.

# Checks for typedefs, structures, and compiler characteristics.

# Checks for library functions.

AC_CONFIG_SUBDIRS(netopeerguid)
AC_CONFIG_FILES([Makefile
		netopeergui.conf])
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
echo "  netopeerguid.........: $with_netopeerguid"
echo
echo "Installation...........: make install (as root if needed, with 'su' or 'sudo')"
echo "  prefix...............: $prefix"
echo "  python module dir....: $pyplugindir"
echo "  webgui dir...........: $webguidir"
echo "  webgui name..........: $webguiname"
echo "  httpdconfddir........: $(test -z "$httpdconfddir" && echo "Apache default path" || echo "$httpdconfddir")"
echo

