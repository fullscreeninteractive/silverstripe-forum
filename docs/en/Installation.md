Installation
================================

1. Place this directory in the root of your SilverStripe installation. Make sure it is named forum and not forum-v2 or any other 
	combination

2. Rebuild your database. Visit http://www.yoursite.com/dev/build/ in your browser or via SAKE - sake dev/build flush=1

3. The CMS should now have "Forum Holder" and "Forum" page types in the page type dropdown. By default SilverStripe will create
a couple default forums and a forum holder.

You should make sure each ForumHolder page type only has Forum children and each forum has its parent as a forum holder. Eg not nested in 
another forum. The module supports multiple forum holders each with their own permissions. For more configuration information see 

	/forum/docs/Configuration.md
	
4. 	To enable a user to be able to subscribe to a forum so that they can receive emails when a new post is sent out, log into the CMS, click on your
forum holder page and choose the settings tab. Check the field "Allow users to subscribe to forums" and choose an email address that this should come from
note - that this only controls the Forum subscription and not the topic subscription.