<% include ForumHeader %>
$Content
<% if ForumAdminMsg %>
	<p class="message info">$ForumAdminMsg</p>
<% end_if %>

<% if CurrentMember.isSuspended %>
	<p class="message error">
		$CurrentMember.ForumSuspensionMessage
	</p>
<% end_if %>

<% if ForumPosters = NoOne %>
	<p class="message error"><% _t('READONLYFORUM', 'This Forum is read only. You cannot post replies or start new threads') %></p>
<% end_if %>
<% if canPost %>
	<p>
	<a class="button" href="{$Link}starttopic" title="<% _t('NEWTOPIC','Click here to start a new topic') %>">
	<% _t('NEWTOPICIMAGE','Start new topic') %>
	</a>
	</p>
<% end_if %>


<table class="forum-table" summary="List of topics in this forum">	

	<% if getStickyTopics(1) %>
		<tbody>
			<tr class="head sticky first category special-header rowOne">
				<th colspan="3"><% _t('ANNOUNCEMENTS', 'Announcements') %></th>
			</tr>
			<tr class="head sticky first category-header special-header">
				<th class="odd"><% _t('TOPIC','Topic') %></th>
				<th class="odd center smallWidth"><% _t('POSTS','Posts') %></th>
				<th class="even last smallWidth lastPost"><% _t('LASTPOST','Last Post') %></th>
			</tr>
		</tbody>
		<tbody class="sticky">
			<% loop StickyTopics %>
				<% include TopicListing EvenOdd=$EvenOdd %>
			<% end_loop %>
		</tbody>		
	<% end_if %>
		<tbody>
		<tr class="head first category rowOne table-header">
			<th colspan="4"><% _t('THREADS', 'Threads') %></th>
		</tr>
		<tr class="head first category-header">
			<th class="odd"><% _t('TOPIC','Topic') %></th>
			<th class="even center smallWidth" ><% _t('POSTS','Posts') %></th>
			<th class="odd last lastPost smallWidth"><% _t('LASTPOST','Last Post') %></th>
		</tr>
	
	<% if Topics %>
		<% loop Topics %>
			<% include TopicListing EvenOdd=$EvenOdd %>
		<% end_loop %>
	<% else %>
		<tr class="special-header">
			<td colspan="3" class="forumCategory"><% _t('NOTOPICS','There are no topics in this forum, ') %><a href="{$Link}starttopic" title="<% _t('NEWTOPIC') %>"><% _t('NEWTOPICTEXT','click here to start a new topic') %>.</a></td>
		</tr>
	<% end_if %>
	</tbody>
</table>

<% if Topics.MoreThanOnePage %>
	<p>
		<% if Topics.PrevLink %><a style="float: left" href="$Topics.PrevLink">	&lt; <% _t('PREVLNK','Previous Page') %></a><% end_if %>
		<% if Topics.NextLink %><a style="float: right" href="$Topics.NextLink"><% _t('NEXTLNK','Next Page') %> &gt;</a><% end_if %>
		
		<% loop Topics.Pages %>
			<% if CurrentBool %>
				<strong>$PageNum</strong>
			<% else %>
				<a href="$Link">$PageNum</a>
			<% end_if %>
		<% end_loop %>
	</p>
<% end_if %>
	
<% include ForumFooter %>