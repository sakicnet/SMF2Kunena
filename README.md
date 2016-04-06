# SMF to Kunena forum migration

* [Introduction]
* [Requirements]
* [Installation]
* [Usage]
  + [Migrate users]
  + [Manually sync users with Kunena]
  + [Migrate user profiles]
  + [Manually create categories]
  + [Migrate posts]
  + [Install the SMF authentication plugin]
  + [Purge the database]
* [Disclaimer]
* [Copyright]

## Introduction

**SMF2Kunena** consists of a **CLI** script for Joomla! with a set of methods that can be used for data migration of *Simple Machines Forum* to *Kunena* and an authentication plugin for authenticating imported users.

It has been successfully used to migrate existing users, boards, topics and forum posts of `SMF 2.0 RC2` to `Kunena 4.0.10` on `Joomla 3.5`.

Note that it may not work out of the box for you, depending on your setup (software versions and database state) but it can give you a head start for a successful data migration.

**For more information have a look at [this blog post](https://www.sakic.net/blog/migrating-data-from-smf-to-kunena/) on *[Sakic.Net](https://www.sakic.net/)*.**

## Requirements

* SMF 2.0
* Joomla! 3.4+
* Kunena 4+

## Installation

**forum_migrate.php** is a **CLI** (Command Line Interface) script which means that it is executed through a terminal, not web browser. It can be done on your local installation or on the server, if you have **SSH** access.

1) Make sure all your **SMF** tables are in the **same database** as Joomla! and Kunena. SMF tables will have prefix "*smf_*" and can be deleted after successful migration.

2) Copy the file **forum_migrate.php** to the **cli/** directory of your website.

## Usage

The data migration is not one-click process and needs to be done in several steps, including some manual work. At this point **make a database backup** so you can roll-back should something go wrong.

1. ### Migrate users

    The script assumes an empty Joomla! user base. All users from SMF will be added, none updated.
    In the file **forum_migrate.php** uncomment the following code:
    ```php
      $this->out('Migrating users...');
      $this->_migrateUsers();
      $this->out('Done migrating users.');
    ```

    Using your terminal cd to cli folder and execute the command:
    ```
        php forum_migrate.php
    ```
    All SMF users should be imported to Joomla! Note that this process can take some time depending on number of users. Verify that users are added in Joomla! users manager.
    
2. ### Manually sync users with Kunena
    
    Now manually sync imported users with Kunena. Go to **Components > Kunena > Tools > Synchronize Users**. Check **Add user profiles to everyone** and click on the **Sync button**. At this point Kunena should have created user profiles for all users and you can verify it by navigating to **Kunena > Users**.
    
3. ### Migrate user profiles

    Go back to **forum_migrate.php**, comment out the previously uncommented block for importing users and uncomment the next block:
    ```php
      $this->out('Migrating user profiles...');
      $this->_migrateUserProfiles();
      $this->out('Done migrating user profiles.');
    ```
    Again, execute the script and all user profiles (including gender, birthdate, signature etc.) will be imported to Kunena.
    
4. ### Manually create categories

    Usually there are not many categories/boards on forums so they can be created manually. They are all called categories in Kunena, create them **using the same titles** and choosing the required structure.

5. ### Migrate posts

    Go back to **forum_migrate.php**, comment out the previously uncommented block for importing user profiles and uncomment the next block:
    ```php
      $this->out('Migrating posts...');
      $this->_migratePosts();
      $this->out('Done migrating posts.');
    ```
    Execute the script. This will migrate all topics and forum posts, as well as update the categories you previously created with required data. Now you can go to Kunena and verify all topics and posts are there and correctly linked with users.
    
6. ### Install the SMF authentication plugin

    Install the **plg_smf** plugin (you can zip the folder and install it through Joomla! extension manager). Go to Extensions > Plugins and publish the plugin **Authentication - SMF**. Since the password hash algorithm is different in SMF, this plugin is required to authenticate the imported users. It will also convert their password hash to Joomla! standard hash first time they successfully login.
    
7. ### Purge the database
    
    If everything worked well, you can delete all tables starting with "**smf_**" so you keep your Joomla! database clean.
    
## Disclaimer

This script is provided as-is, without support. The author can not be hold liable for any damages caused by using or misusing this script including, but not limited to, loss of data on your website. You use it on your own risk.

Although no email support is given you can use [forum](https://www.sakic.net/forum/) on our site to address any issues you may have. You can also get paid support or hire us to make a data conversion on your site. Plase [contact us](https://www.sakic.net/contact/) for details.

## Copyright

Copyright © [Sakic.Net](https://www.sakic.net/), 2016. - All Rights Reserved.

[Introduction]: #introduction
[Requirements]: #requirements
[Installation]: #installation
[Usage]: #usage
[Migrate users]: #migrate-users
[Manually sync users with Kunena]: #manually-sync-users-with-kunena
[Migrate user profiles]: #migrate-user-profiles
[Manually create categories]: #manually-create-categories
[Migrate posts]: #migrate-posts
[Install the SMF authentication plugin]: #install-the-smf-authentication-plugin
[Purge the database]: #purge-the-database
[Disclaimer]: #disclaimer
[Copyright]: #copyright
