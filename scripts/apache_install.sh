#!/bin/bash
set -x
set -e

PACKAGENAME=builder

sudo ln -s /opt/codebender/$PACKAGENAME/Symfony/web /var/www/$PACKAGENAME
sudo cp /opt/codebender/$PACKAGENAME/apache-config /etc/apache2/sites-available/codebender-$PACKAGENAME
cd /etc/apache2/sites-enabled
sudo ln -s ../sites-available/codebender-$PACKAGENAME 00-codebender
sudo service apache2 restart