# Hub app for Webasyst #

Hub is a perfect app for managing customer feedback, discussions, and your company knowledge base.

http://www.webasyst.com

## System Requirements ##

  * Web Server
		* e.g. Apache or IIS
		
	* PHP 5.2+
		* spl extension
		* mbstring
		* iconv
		* json

	* MySQL 4.1+

## Installing Webasyst Framework ##

Install Webasyst Framework via http://github.com/webasyst/webasyst-framework/ or http://www.webasyst.com/framework/

## Installing Hub app ##

1. Once Webasyst Framework is installed, get Hub app code into your /PATH_TO_WEBASYST/wa-apps/hub/ folder:

	via GIT:

		cd /PATH_TO_WEBASYST/wa-apps/hub/
		git clone git://github.com/webasyst/hub.git ./

	via SVN:
	
		cd /PATH_TO_WEBASYST/wa-apps/hub/
		svn checkout http://svn.github.com/webasyst/hub.git ./

2. Add the following line into the /wa-config/apps.php file (this file lists all installed apps):

		'hub' => true,
		
3. Done. Run Webasyst backend in a web browser and click on Hub app icon in the main app list.

## Updating Webasyst Framework ##

Staying with the latest version of Hub app is easy: simply update your files from the repository and login into Webasyst, and all required meta updates will be applied to Webasyst and its apps automatically.
