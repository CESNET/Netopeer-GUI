# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure(2) do |config|
  config.vm.box = "centos/7"

  config.vm.network "forwarded_port", guest: 80, host: 2280
  config.vm.network "forwarded_port", guest: 5555, host: 5555

  config.vm.provision "shell", inline: <<-SHELL
	./install/centos7/install.sh
#     sudo rpm -ivh https://homeproj.cesnet.cz/rpm/liberouter/devel/x86_64/liberouter-devel-1.0.0-1.noarch.rpm
SHELL
end
