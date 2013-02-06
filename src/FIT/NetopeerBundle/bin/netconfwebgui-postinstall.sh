#!/bin/bash
#
# netconfwebgui-postinstall.sh: Configuration and initialization of DB
# Copyright (C) 2013
# Author(s): Tomas Cejka  <cejkato2@fit.cvut.cz>
#
# LICENSE TERMS
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in
#    the documentation and/or other materials provided with the
#    distribution.
# 3. Neither the name of the Company nor the names of its contributors
#    may be used to endorse or promote products derived from this
#    software without specific prior written permission.
#
# This software is provided ``as is'', and any express or implied
# warranties, including, but not limited to, the implied warranties of
# merchantability and fitness for a particular purpose are disclaimed.
# In no event shall the company or contributors be liable for any
# direct, indirect, incidental, special, exemplary, or consequential
# damages (including, but not limited to, procurement of substitute
# goods or services; loss of use, data, or profits; or business
# interruption) however caused and on any theory of liability, whether
# in contract, strict liability, or tort (including negligence or
# otherwise) arising in any way out of the use of this software, even
# if advised of the possibility of such damage.
#


read -p "Enter path to netconfwebgui app directory [/var/www/netconfwebgui/app/]: " NWGPATH
if [ -z "$NWGPATH" ]; then
	NWGPATH=/var/www/netconfwebgui/app/
fi
if [ ! -e "$NWGPATH" ]; then
	echo "Directory $NWGPATH does not exist." > /dev/stderr
	exit 1
fi
cd "$NWGPATH" || exit 2
echo -e "Please update DB connection settings...\nSelect editor to open The configuration file."
if [ -z "$EDITOR" ]; then
	select EDITOR in vi vim nano joe gedit; do
		if [ -n "$EDITOR" ]; then export EDITOR; break; fi
	done
fi

${EDITOR} config/parameters.ini

ANS="";
while [ -z "$ANS" ]; do
	read -n1 -p "Drop previous DB from configuration? [YyNn]: " ANS
done
if [ "$ANS" = "y" -o "$ANS" = "Y" ]; then
	php console doctrine:database:drop --force
else echo "Skipping"; fi
ANS="";
while [ -z "$ANS" ]; do
	read -n1 -p "Create new DB? [YyNn]: " ANS
done
if [ "$ANS" = "y" -o "$ANS" = "Y" ]; then
	php console doctrine:database:create
else echo "Skipping"; fi
ANS="";
while [ -z "$ANS" ]; do
	read -n1 -p "Update schema? [YyNn]: " ANS
done
if [ "$ANS" = "y" -o "$ANS" = "Y" ]; then
	php console doctrine:schema:update --force
else echo "Skipping"; fi

