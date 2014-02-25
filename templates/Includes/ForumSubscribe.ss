<div class="forum-subscribe">
	<% if CurrentMember %> 
		
		<% if Categories %> 
		
		<% else %> 
			<p> 
				<% if getHasSubscribed %> 
					You are subscribed to email notifications for this forum.<br /><a href="{$Link}forumUnsubscribe" class="unsubscribeToForumLink">Click here to unsubscribe.</a> 
				<% else %> 
					<a class="subscribeToForumLink" href="{$Link}forumSubscribe">Subscribe to email notifications for this forum.</a> 
				<% end_if %> 
			</p> 
		<% end_if %> 
	<% end_if %>
	
</div>