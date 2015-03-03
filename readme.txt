== Installation ==

 * Unzip the files and upload the folder into your plugins folder (wp-content/plugins/)
 * Activate the plugin in your WordPress admin area.
 * Open the settings page for JigoShop and click the gateways tab
 * Configure your PayJunction settings.  See below how to  
 
== Important Note ==

You *must* enable SSL from the settings panel to use this plugin in live mode - this is for your customers safety and security.


== Where to find your PayJunction Credentials ==

To setup your PayJunction payment gateway you will need to enter your QuickLink API Login and QuickLink API Password.
1.  Get your QuickLink API Login and Password. Instructions are provided on the following support article:
       http://www.payjunction.com/trinity/support/view.action?knowledgeBase.knbKnowledgeBaseId=589

2.  Once you have your QuickLink API Login and Password, save this information to your JigoShop PayJunction settings section

3.  Save the settings.

== Changelog ==

= 1.0.0 = 

* Initial release

= 1.5.0 =

* Rewritten for PayJunction REST API
* Works with latest versions of Wordpress (4.1) and JigoShop (1.15.3).
* Dynamic Address Verification Security
* Dynamic Bypass Mode
* Authorization Only option
* Support for Tax and Shipping fields in PayJunction
* Improved information logging in order notes.
* On fraud declines, requests the user contact the merchant to try and prevent multiple resubmissions.

