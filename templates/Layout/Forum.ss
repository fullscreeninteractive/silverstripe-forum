<div id="Forum">
	<% include ForumHeader %>

	$Content
	<% if ForumAdminMsg %>
		<p id="ForumAdminMsg">$ForumAdminMsg</p>
	<% end_if %>
	
	<% if ForumPosters = NoOne %>
		<p class="message error"><% _t('READONLYFORUM', 'This Forum is read only. You cannot post replies or start new threads') %></p>
	<% end_if %>
	<% if canPost %>
		<p><a href="{$Link}starttopic" title="<% _t('NEWTOPIC','Click here to start a new topic') %>"><img src="forum/images/forum_startTopic.gif" alt="<% _t('NEWTOPICIMAGE','Start new topic') %>" /></a></p>
	<% end_if %>
	<div class="forumHolderFeatures">
		<% if getStickyTopics(0) %>
			<table id="StickyTopiclist" class="topicList" summary="List of sticky topics in this forum">
				<tr class="category"><td colspan="3"><% _t('ANNOUNCEMENTS', 'Announcements') %></td></tr>
				<% control getStickyTopics(0) %>
					<% include TopicListing %>
				<% end_control %>
			</table>
		<% end_if %>
		<table id="TopicList" class="topicList" summary="List of topics in this forum">
			<tr class="category"><td colspan="4"><% _t('THREADS', 'Threads') %></td></tr>
			<tr>
				<th class="odd"><% _t('TOPIC','Topic') %></th>
				<th class="odd"><% _t('POSTS','Posts') %></th>
				<th class="even"><% _t('LASTPOST','Last Post') %></th>
			</tr>
			<% if Topics %>
				<% control Topics %>
					<% include TopicListing %>
				<% end_control %>
			<% else %>
				<tr>
					<td colspan="3" class="forumCategory"><% _t('NOTOPICS','There are no topics in this forum, ') %><a href="{$Link}starttopic" title="<% _t('NEWTOPIC') %>"><% _t('NEWTOPICTEXT','click here to start a new topic') %>.</a></td>
				</tr>
			<% end_if %>
		</table>

		<% if Topics.MoreThanOnePage %>
			<div class="typography">
				<p>
					<% if Topics.PrevLink %><a style="float: left" href="$Topics.PrevLink">	&lt; <% _t('PREVLNK','Previous Page') %></a><% end_if %>
					<% if Topics.NextLink %><a style="float: right" href="$Topics.NextLink"><% _t('NEXTLNK','Next Page') %> &gt;</a><% end_if %>
					
					<% control Topics.Pages %>
						<% if CurrentBool %>
							<strong>$PageNum</strong>
						<% else %>
							<a href="$Link">$PageNum</a>
						<% end_if %>
					<% end_control %>
				</p>
			</div>
		<% end_if %>
		
	</div>
	<% include ForumFooter %>
</div>