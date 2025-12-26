#!/bin/bash
# cPanel Plugin Installation Script
mkdir -p /usr/local/cpanel/3rdparty/flexpbx
cp -r ./* /usr/local/cpanel/3rdparty/flexpbx/
chmod +x /usr/local/cpanel/3rdparty/flexpbx/flexpbx.pl
/usr/local/cpanel/scripts/install_plugin /usr/local/cpanel/3rdparty/flexpbx
