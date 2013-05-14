<% include ForumHeader %>


<table class="forum-topics">
	<tr class="category">
		<td class="page-numbers">
			<span><strong><% _t('PAGE','Page:') %></strong></span>
			<% loop Posts.Pages %>
				<% if CurrentBool %>
					<span><strong>$PageNum</strong></span>
				<% else %>
					<a href="$Link">$PageNum</a>
				<% end_if %>
				<% if not Last %>,<% end_if %>
			<% end_loop %>
		</td>
		<td class="gotoButtonEnd" >
			<a href="#Footer" title="<% _t('CLICKGOTOEND','Click here to go the end of this post') %>"><% _t('GOTOEND','Go to End') %></a>
		</td>
		<td class="replyButton">
			<% if ForumThread.canCreate %>
				<a href="$ReplyLink" title="<% _t('CLICKREPLY','Click here to reply to this topic') %>"><% _t('REPLY','Reply') %></a>
			<% end_if %>
			<% if CurrentMember %>
				<% include ForumThreadSubscribe %>
			<% end_if %>
		</td>
	</tr>
	<tr class="author">
		<td class="name">
			<span><% _t('AUTHOR','Author') %></span>
		</td>
		<td class="topic">
			<span><strong><% _t('TOPIC','Topic:') %></strong> $ForumThread.Title</span>
		</td>
		<td class="views">
			<span><strong>$ForumThread.NumViews <% _t('VIEWS','Views') %></strong></span>
		</td>
	</tr>
</table>

<% loop Posts %>
	<% include SinglePost %>
<% end_loop %>

<table class="forum-topics">
	<tr class="author">
		<td class="author">&nbsp;</td>
		<td class="topic">&nbsp;</td>
		<td class="views">
			<span><strong>$ForumThread.NumViews <% _t('VIEWS','Views') %></strong></span>
		</td>
	</tr>
	<tr class="category">
		<td class="page-numbers">
			<% if Posts.MoreThanOnePage %>
				<% if Posts.NotFirstPage %>
					<a class="prev" href="$Posts.PrevLink" title="<% _t('PREVTITLE','View the previous page') %>"><% _t('PREVLINK','Prev') %></a>
				<% end_if %>
			<% end_if %>
		</td>
		<td class="gotoButtonTop" >
			<a href="#Header" title="<% _t('CLICKGOTOTOP','Click here to go the top of this post') %>"><% _t('GOTOTOP','Go to Top') %></a>
		</td>
		<td class="replyButton">
			<% if ForumThread.canCreate %>
				<a href="$ReplyLink" title="<% _t('CLICKREPLY', 'Click to Reply') %>"><% _t('REPLY', 'Reply') %></a>
			<% end_if %>
			
			<% if Posts.MoreThanOnePage %>
				<% if Posts.NotLastPage %>
					<a class="next" href="$Posts.NextLink" title="<% _t('NEXTTITLE','View the next page') %>"><% _t('NEXTLINK','Next') %> &gt;</a>
				<% end_if %>
			<% end_if %>
		</td>
	</tr>
</table>

<% if AdminFormFeatures %>
<div class="forum-admin-features">
	<h3>Forum Admin Features</h3>
	$AdminFormFeatures
</div>
<% end_if %>

<% include ForumFooter %>