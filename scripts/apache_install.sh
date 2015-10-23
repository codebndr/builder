#!/bin/bash
set -x
set -e

PACKAGENAME=builder

<<<<<<< HEAD
sudo ln -s /opt/codebender/$PACKAGENAME/Symfony/web /var/www/$PACKAGENAME
sudo cp /opt/codebender/$PACKAGENAME/apache-config-2.4 /etc/apache2/sites-available/codebender-$PACKAGENAME
=======
sudo cp /opt/codebender/$PACKAGENAME/apache-config /etc/apache2/sites-available/codebender-$PACKAGENAME
>>>>>>> 1062d777a61b66db589845a8fb1a2c46aeb6fe56
cd /etc/apache2/sites-enabled
sudo ln -s ../sites-available/codebender-$PACKAGENAME 00-codebender.conf
sudo service apache2 restart
