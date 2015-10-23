#!/bin/bash
set -x
set -e

PACKAGENAME=builder

echo "Configuring environment for Linux (Ubuntu 12.04)"

# Make sure we have up-to-date stuff
sudo apt-get update

# Install dependencies
sudo apt-get install -y apache2 libapache2-mod-php5 php-pear php5-curl php5-sqlite php5-mysql acl curl git
# Enable Apache configs
sudo a2enmod rewrite
sudo a2enmod alias
# Restart Apache
sudo service apache2 restart

HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`

sudo mkdir -p /opt/codebender
sudo cp -r . /opt/codebender/$PACKAGENAME
sudo chown -R `whoami`:$HTTPDUSER /opt/codebender/$PACKAGENAME
cd /opt/codebender/$PACKAGENAME

#Set permissions for app/cache and app/logs

rm -rf Symfony/app/cache/*
rm -rf Symfony/app/logs/*

# Need to create cache and logs directories, as they do not pre-exist in new deployments
mkdir -p `pwd`/Symfony/app/cache/
mkdir -p `pwd`/Symfony/app/logs/

cd Symfony

set +x
cat app/config/parameters.yml.dist | grep -iv "compiler:" | grep -iv "library:" > app/config/parameters.yml
echo "    compiler: '$COMPILER_URL'" >> app/config/parameters.yml

echo "    library: '$LIBRARY_URL'" >> app/config/parameters.yml
set -x


curl -s http://getcomposer.org/installer | php
php composer.phar install
