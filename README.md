# Course migration #

The Course Migration Tool provides the capability to backup courses in a source
Moodle site and restore them in target Moodle site.

This is achieved by uploading a csv file containing a list of courses to be
migated from the source site and the category in the target Moodle site.

Backups are created as a scheduled/adhoc task combination on the source site. These
backups are placed in a configurable storage location. Currently, only a shared disk
is supported.

The source site communicates the availability of courses to migrate to the target
site via a web service end point.  A web service token from the target site is added
to the configuration on the source site.

The target site has a similar scheduled/adhoc task combination to consume the backup
files and restore them to the required category.

Activity, progress and results are logged with events and any processing errors. These
can be viewed via an included repots section.

## Branches

| Moodle version   | Branch            |
|------------------|-------------------|
| Moodle 4.4+      | MOODLE_404_STABLE |
| Moodle 3.9 - 4.1 | main              |

## Configuration ##
_Site administration > Plugins > Admin tools > Course migration_

**Backup**
* Destination URL - URL for web service end point on the target site
* Web service token - Authentication token created by the target site, used
for accessing the web service end point.

**Restore**
* Restore root category - Default/root category for restoring courses.
* Restore as a hidden course - Option for the restored course visibility.
* Delete successfully restored backups - Option for backup files in the storage
location to be deleted after a successful restore.
* Delete failed backups - Option for backup files in the storage location to be
deleted after a failed restore.

**Storage**
* Backup storage - Select one of a list of available storage types. Currently only
`Shared disk storage` is supported.
* Shared disk storage
  * Save to - the full path to the directory on the local file system where you want
  to save the backup files.
  * Restore from - the full path to the directory on the local file system where
  the backup files are restored from.


**Scheduled Tasks**

_Site administration > Server > Tasks > Scheduled tasks_

These two scheduled tasks are created and run every 1 minute by default.
* Create backup adhoc tasks for course migration
* Create restore adhoc tasks for course migration

## Quick start ##
* Install plugin on source and taget sites.
* Create a shared folder/disk accessible to both sites in their local file system.
* Create a web service _Site administration > Plugins > Web services > External services_
* Add function `tool_coursemigration_request_restore:Request a restore` to the service.
* Create a web service token _Site administration > Plugins > Web services > Manage tokens_
* Add the web service token to the source site configuration.
* Create a CSV file with course id and category id of courses to migrate.
  * An example csv file is available on the `Upload course list` page.
* Upload the csv file at _Site administration > Plugins > Admin tools > Course migration > Upload course list_

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/admin/tool/coursemigration

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

# Warm thanks #

Thanks to Monash University (https://www.monash.edu) for funding the development of this plugin.

# License #

2023 Catalyst IT

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
