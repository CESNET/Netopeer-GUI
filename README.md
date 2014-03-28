NetopeerGUI
========================

NetopeerGUI is web graphical user interface for configuring devices based on protocol NETCONF. For more info visit [Public web section about Netopeer](https://www.liberouter.org/technologies/netconf/).

NetopeerGUI is developed as [Symfony2 app](http://symfony.com).

## Installation
**Install pyang** from https://code.google.com/p/pyang/

After tha, **run** the following commands:

	# go to apache web directory
	cd /var/www
	
    git clone --recursive https://github.com/cesnet/netopeergui.git
    # OR for already cloned repos or older GIT versions use
    #
    # git clone https://github.com/cesnet/netopeergui.git
    # cd netopeergui
    # git submodule update --init --recursive 
    
    cd netopeergui
    
    cd install
    ./bootstrap.sh
    
    # for change some variables, look at ./configure --help
    ./configure
    
    su # installation must be done as root
    make install
    
    cd ../
    # check server configuration and repair errors
    php app/check.php
    
    php ./composer.phar install

### Underhood - install folder
Install folder includes necesarry files for communicating with NETCONF devices. It contains also [mod_netconf](https://github.com/CESNET/mod_netconf) submodule. This causes, why `--recursive` in git clone is necessary. For **mod_netconf** update follow instructions on [mod_netconf](https://github.com/CESNET/mod_netconf) site.

Configure script check all dependencies and prepares all resources for install. 

Make install will also copy `netopeergui.conf` into `/etc/httpd/conf.d/` folder. Change this manually, if you need.

#### Note:
For a more detailed explanation of symfony2 installation, see the [Installation chapter](http://symfony.com/doc/current/book/installation.html) of the Symfony Documentation.

## First steps
1. Open site http://localhost/netopeergui
2. Login using **admin**, **pass** (this credentials were created during installation)
3. Connect to the device using SSH credentials
4. Click **Configure device**

### Setting custom user
For setting new user or edit current, use command line script. This script will create or update user in DB. There is no "GUI" for user settings.
#### Create user

	su
	php app/console app:user [--action=add] --user=username --pass=password

#### Remove user
	su
	php app/console app:user --action=add --user=username
	
#### Change password
	su
	php app/console app:user --action=edit --user=username --pass=newpass [--new-username=newusername]