<p>
	<% sprintf(_t('ForumMember_Notification_ss.HI',"Hi %s,"),$Nickname) %>
</p>

<p>
	<% _t('ForumMember_Notification_ss.NEWPOSTMESSAGE',"A new post has been added to the forum you've subscribed to") %> - '$Title' 
		<% if Author.Nickname %>
			<% _t('BY', "by") %> $Author.Nickname
		<% else %>
			<% _t('BY', "by") %> Anonymous
		<% end_if %>
		
	</p>
<ul>
	<li><a href="$Link"><% _t('ForumMember_Notification_ss.REPLYLINK', "View the topic") %></a></li>
	<li><a href="$UnsubscribeLink"><% _t('ForumMember_Notification_ss.UNSUBSCRIBETEXT',"Unsubscribe from the forum") %></a></li>
</ul>

<p>
	Thanks,<br />
	The Forum Team.
</p>

<p>NOTE: This is an automated email, any replies you make may not be read.</p>