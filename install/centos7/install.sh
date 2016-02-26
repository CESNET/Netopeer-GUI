sudo yum install -y wget git gcc gcc-c++

sudo yum install -y zlib1g-devel libssl-devel openssl-devel cmake pcre-devel
wget https://red.libssh.org/attachments/download/177/libssh-0.7.2.tar.xz
tar -xJf libssh-0.7.2.tar.xz
mkdir -p libssh-0.7.2/build && cd libssh-0.7.2/build
cmake -DCMAKE_INSTALL_PREFIX:PATH=/usr -DLIB_INSTALL_DIR=/usr/lib64 .. && make -j2 && sudo make install
cd ../..

git clone https://github.com/CESNET/libyang.git
mkdir -p libyang/build && cd libyang/build
git checkout devel
cmake -DCMAKE_INSTALL_PREFIX=/usr -DLIB_INSTALL_DIR=lib64 -DENABLE_BUILD_TESTS=OFF .. && sudo make install && sudo make install

cd ../..
wget https://cmocka.org/files/1.0/cmocka-1.0.1.tar.xz
tar -xJf cmocka-1.0.1.tar.xz
mkdir -p cmocka-1.0.1/build && cd cmocka-1.0.1/build
cmake -DCMAKE_INSTALL_PREFIX:PATH=/usr .. && make -j2 && sudo make install

cd ../..
git clone git://github.com/cejkato2/libwebsockets lws
mkdir lws/b && cd lws/b
cmake .. && sudo make install


# libnetconf2
# 
cd ../..
git clone https://github.com/CESNET/libnetconf2.git
git checkout devel
mkdir -p libnetconf2/build && cd libnetconf2/build
cmake -DENABLE_TLS=ON -DENABLE_SSH=ON -DENABLE_BUILD_TESTS=OFF -DCMAKE_INSTALL_PREFIX=/usr -DLIB_INSTALL_DIR=lib64 .. && sudo make install


# libnetconf
# 
# cd ../..
# sudo yum install -y python-setuptools
# git clone https://github.com/mbj4668/pyang.git
# cd pyang
# python setup.py install

# cd ../..
# git clone --depth 1 https://github.com/CESNET/libnetconf.git
# sudo yum install -y libxml2-devel libxslt-devel curl-devel libtool
# cd libnetconf
# ./configure --prefix=/usr --libdir=/usr/lib64 -q


cd ../..
cd mod_netconf
# sudo yum install -y json-c-devel
./bootstrap.sh
./configure
make
make install

ldconfig

