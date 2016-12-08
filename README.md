##Initial Setup

**Following operations are all on the server VM**

**Need to manually create a folder "/logs" in your project deploy folder, make sure "/logs" is writable.**

###Install PHP OAuth extension.

####Install PEAR
```
sudo apt-get update
```
Depends on which version of PHP you're using, in this case we use **php5.6**
```
sudo apt-get install php-pear php5.6-dev
```
####Install Oauth Extension
From this point, you can use pear to install oauth extension
```
sudo apt-get install gcc make autoconf libc-dev pkg-config
```
```
sudo pecl install oauth-1.2.3
```
####Modify Environment Setting
You should add "extension=oauth.so" to php.ini

@ base directory
```
cd etc/php/5.6/cli/
```
```
sudo vi php.ini
```

Add following line into php.ini file
```
extension=oauth.so

```

Restart apache service including php
```
sudo service apache2 restart
```

Check mod already enabled
```
php -m
```


**References:**

http://codecreations.weebly.com/blog/installing-oauth-on-ubuntu

https://serverpilot.io/community/articles/how-to-install-the-php-oauth-extension.html


###Install composer

- `curl -sS https://getcomposer.org/installer | php`
- `sudo mv composer.phar /usr/local/bin`
- `vi ~/.bash_profile` and add alias `composer="php /usr/local/bin/composer.phar"`
- Now you can call composer by `$ composer`

**Please run "YOUR_DOMAIN/controllers/getQBtoken.php" to set the Quickbooks access token before run the app first time.**

##Database creation

execute following sql statement in your database to create needed tables:

###1. customer table
```
CREATE TABLE `customer` (
  `customer_id` varchar(20) COLLATE utf8_bin NOT NULL,
  `quickbook_id` varchar(40) COLLATE utf8_bin DEFAULT NULL,
  `create_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`),
  KEY `quickbook` (`quickbook_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

```

###2. payment table
```
CREATE TABLE `payment` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `customer_id` varchar(20) COLLATE utf8_bin NOT NULL,
  `description` varchar(255) COLLATE utf8_bin DEFAULT NULL,
  `amount` decimal(12,2) unsigned NOT NULL,
  `is_recurring` enum('N','Y') COLLATE utf8_bin DEFAULT 'N',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `payment_method` enum('ONCE','PER_MONTH','PER_DAYS') COLLATE utf8_bin DEFAULT 'ONCE',
  `payment_day` int(5) DEFAULT NULL,
  `payment_duration` int(5) DEFAULT NULL,
  `create_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer` (`customer_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

```

###3. payment_response table
```
CREATE TABLE `payment_response` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `customer_id` varchar(20) COLLATE utf8_bin DEFAULT NULL,
  `payment_id` int(10) DEFAULT NULL,
  `response_data` text COLLATE utf8_bin,
  `is_send_quickbook` enum('N','Y') COLLATE utf8_bin DEFAULT 'N',
  `create_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;

```

##URL Parameter Description
The URL parameters:"```amtPayment```,```description```,```customerId```" is required when you are making a payment. 

```amtPayment```: payment amount

```description```:  payment description, eg: iphone 7 purchase
 
```customerId```: customer unique identifier from your system

##Config settings

In config folder, edit config.php

- fill in auth keys from sparrow and quickbook

- fill in database connection
