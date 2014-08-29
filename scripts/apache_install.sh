#!/bin/bash
set -x
set -e

PACKAGENAME=builder

ln -s /opt/codebender/$PACKAGENAME /var/www/$PACKAGENAME
sudo cp /opt/codebender/$PACKAGENAME/apache-config /etc/apache2/sites-available/codebender
cd /etc/apache2/sites-enabled
sudo ln -s ../sites-available/codebender 00-codebender
