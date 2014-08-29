#!/bin/bash
set -x
set -e

PACKAGENAME=builder

sudo ln -s /opt/codebender/$PACKAGENAME /var/www/$PACKAGENAME
sudo cp /opt/codebender/$PACKAGENAME/apache-config /etc/apache2/sites-available/$PACKAGENAME
cd /etc/apache2/sites-enabled
sudo ln -s ../sites-available/$PACKAGENAME 00-codebender
