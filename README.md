NetopeerGUI
========================

NetopeerGUI is web graphical user interface for configuring devices based on protocol NETCONF. For more info visit [Public web section about Netopeer](https://www.liberouter.org/technologies/netconf/).

NetopeerGUI is developed as [Symfony2 app](http://symfony.com).

## NetopeerGUI demo - virtual machine

You can download VirtualBox image from https://www.liberouter.org/docs/2015-05-11-netopeer-demo.ova

To import this image into VirtualBox, just follow these steps:
1. Import *.ova file into VirtualBox: File > Import appliance
2. Run new virtual system. After automatic login, you will see README with next instructions.
3. It's recommended to install VirtualBox Guest Additions (for better system support).

## Installation
**Install pyang** from https://code.google.com/p/pyang/

After tha, **run** the following commands:

	# go to apache web directory
	cd /var/www
	
    git clone --recursive https://github.com/cesnet/netopeer-gui.git
    # OR for already cloned repos or older GIT versions use
    #
    # git clone https://github.com/cesnet/netopeer-gui.git
    # cd netopeergui
    # git submodule update --init --recursive 
    
    cd netopeer-gui
    
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
	
#### Using SAML
NetopeerGUI has implemented login using SAML and [SamlSPBundle](https://github.com/aerialship/SamlSPBundle/). For configuration, you must edit `/app/config/security.yml` file. Find section 

	saml:
            pattern: ^/(?!login_check)
            anonymous: true
            aerial_ship_saml_sp:
                login_path: /saml/sp/login
                check_path: /saml/sp/acs
                logout_path: /saml/sp/logout
                failure_path: /saml/sp/failure
                metadata_path: /saml/sp/FederationMetadata.xml
                discovery_path: /saml/sp/discovery
                local_logout_path: /logout/
                provider: saml_user_provider
                create_user_if_not_exists: true
                services:
                    openidp:
                    idp:
                            file: "@FITNetopeerBundle/Resources/saml/openidp.metadata.xml"
                        sp:
                            config:
                                # required
                                entity_id: netopeergui_sauvignon
                                # if different then url being used in request
                                # used for construction of assertion consumer and logout urls in SP entity descriptor
                                base_url: https://sauvignon.liberouter.org/netopeergui
                            signing:
                                # must implement SPSigningProviderInterface
                                # id: my.signing.provider.service.id

                                # or use built in SPSigningProviderFile with specific certificate and key files
                                cert_file: "@FITNetopeerBundle/Resources/saml/server.pem"
                                key_file: "@FITNetopeerBundle/Resources/saml/server.key"
                                key_pass: ""
                            meta:
                                # must implement SpMetaProviderInterface
                                # id: my.sp.provider.service.id

                                # or use builtin SpMetaConfigProvider
                                # any valid saml name id format or shortcuts: persistent or transient
                                name_id_format: persistent
                                binding:
                                    # any saml binding or shortcuts: post or redirect
                                    authn_request: redirect
                                    logout_request: post
                                    
and edit following lines:

	file: "@FITNetopeerBundle/Resources/saml/openidp.metadata.xml"
	entity_id: netopeergui_sauvignon
	base_url: https://sauvignon.liberouter.org/netopeergui
	
Configuration notes are described in [SamlSPBundle configuration](https://github.com/aerialship/SamlSPBundle/blob/master/src/AerialShip/SamlSPBundle/Resources/doc/configuration.md) doc.

This example service uses [https://openidp.feide.no](https://openidp.feide.no) user provider. For register this your netopeerGUI, generate FederationMetadata.xml file (located in /saml/sp/FederationMetadata.xml) and upload it into OpenIDP.