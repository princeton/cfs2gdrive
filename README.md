cfs2gdrive
==========

A PHP script meant to be run as a cron job that will upload all files placed in a local source directory to a target folder in a Google Drive account. 

Dependencies:

* Google Drive PHP API
* KLogger
* CronHelper

The Google Drive API and KLogger can be installed using the composer.json file included.

CronHelper is described here http://abhinavsingh.com/blog/2009/12/how-to-use-locks-in-php-cron-jobs-to-avoid-cron-overlaps/
Simply create a cronHelper directory and save the source file in that directory.
