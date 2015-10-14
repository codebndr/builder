#!/bin/bash
set -x
set -e

PACKAGENAME=builder

if [[ "$OSTYPE" == "linux-gnu" ]]; then
	echo "Configuring environment for Linux"
	# Make sure we have up-to-date stuff
    sudo apt-get update
	if [[ ! $TRAVIS ]]; then
		# Ubuntu Server (on AWS?) lacks UTF-8 for some reason. Give it that
		sudo locale-gen en_US.UTF-8
		sudo apt-get install -y php5-intl
	fi
	# Install dependencies
	sudo apt-get install -y apache2 libapache2-mod-php5 php-pear php5-curl php5-sqlite php5-mysql acl curl git
	# Enable Apache configs
	sudo a2enmod rewrite
    sudo a2enmod alias
    # Restart Apache
    sudo service apache2 restart
elif [[ "$OSTYPE" == "darwin"* ]]; then
	# is there something comparable to this on os x? perhaps Homebrew
	echo "Configuring environment for OS X (to be added..)"
fi

if [[ ! $TRAVIS ]]; then

	##### Enable/Install phpunit
	sudo apt-get install -y phpunit

	#### Set Max nesting lvl to something Symfony is happy with
	export ADDITIONAL_PATH=`php -i | grep -F --color=never 'Scan this dir for additional .ini files'`
	echo 'xdebug.max_nesting_level=256' | sudo tee ${ADDITIONAL_PATH:42}/symfony2.ini
fi

HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`

sudo mkdir -p /opt/codebender
sudo cp -r . /opt/codebender/$PACKAGENAME
sudo chown -R `whoami`:$HTTPDUSER /opt/codebender/$PACKAGENAME
cd /opt/codebender/$PACKAGENAME

#Set permissions for app/cache and app/logs

rm -rf Symfony/app/cache/*
rm -rf Symfony/app/logs/*

if [[ "$OSTYPE" == "linux-gnu" ]]; then

	if [[ ! $TRAVIS ]]; then
	    # Need to create cache and logs directories, as they do not pre-exist in new deployments
	    mkdir -p `pwd`/Symfony/app/cache/
		mkdir -p `pwd`/Symfony/app/logs/

        # Set access control for both apache and current user on cache and logs directories
		sudo setfacl -R -m u:www-data:rwX -m u:`whoami`:rwX `pwd`/Symfony/app/cache `pwd`/Symfony/app/logs
		sudo setfacl -dR -m u:www-data:rwx -m u:`whoami`:rwx `pwd`/Symfony/app/cache `pwd`/Symfony/app/logs
	fi

elif [[ "$OSTYPE" == "darwin"* ]]; then

	HTTPDUSER=`ps aux | grep -E '[a]pache|[h]ttpd|[_]www|[w]ww-data|[n]ginx' | grep -v root | head -1 | cut -d\  -f1`
	sudo chmod +a "$HTTPDUSER allow delete,write,append,file_inherit,directory_inherit" Symfony/app/cache Symfony/app/logs
	sudo chmod +a "`whoami` allow delete,write,append,file_inherit,directory_inherit" Symfony/app/cache Symfony/app/logs
fi

cd Symfony

# TODO: generate parameters.yml file somehow
## For reference, here's a command to replace a substring in a file
## cat kourades.sh  | sed 's/kourades/skata/g' | tee skata.sh  > /dev/null 2>&1

cp app/config/parameters.yml.dist app/config/parameters.yml

set +x
cat app/config/parameters.yml | grep -iv "compiler:" > app/config/parameters.yml
echo "    compiler: '$COMPILER_URL'" >> app/config/parameters.yml

cat app/config/parameters.yml | grep -v "library:" > app/config/parameters.yml
echo "    library: '$LIBRARY_URL'" >> app/config/parameters.yml
set -x


curl -s http://getcomposer.org/installer | php
php composer.phar install
