Drush Composer
==============

Drush wrapper for Composer.

Installation
------------

    $ drush dl composer-8.x-1.x

Usage
-----

    $ drush composer

Development
-----------

To update Drush Composer to the latest version of Composer, use this following:

    $ cd ~/.drush/composer
    $ drush composer update
    $ vendor/bin/composer list --xml > composer.drush.xml
