<% include ForumHeader %>
	<table id="showTopic" class="forum-table">
		<thead class="first">
			<tr class="category table-header rowOne">
				<th class="page-numbers">
					<span><strong><% _t('PAGE','Page:') %></strong></span>
					<% loop Posts.Pages %>
						<% if CurrentBool %>
							<span><strong>$PageNum</strong></span>
						<% else %>
							<a href="$Link">$PageNum</a>
						<% end_if %>
						<% if Last %><% else %>,<% end_if %>
					<% end_loop %>
				</th>
				<th class="gotoButtonEnd">
					<a class="icon arrow-down-skip" href="#forum-footer" title="<% _t('CLICKGOTOEND','Click here to go the end of this post') %>"><% _t('GOTOEND','Go to End') %></a>
				</th>
				<th class="last replyButton">
					<% if CurrentMember %>
						<% include ForumThreadSubscribe %>
					<% end_if %>
					<% if ForumThread.canCreate %>
						<a class="reply icon" href="$ReplyLink" title="<% _t('CLICKREPLY','Click here to reply to this topic') %>"><% _t('REPLY','Reply') %></a>
					<% end_if %>
				</th>
			</tr>
			<tr class="category-header rowTwo">
				<th class="author name">
					<span><% _t('AUTHOR','Author') %></span>
				</th>
				<th class="topicTitle">
					<span><strong><% _t('TOPIC','Topic:') %></strong> $ForumThread.Title</span>
				</th>
				<th class="views reads last"<% if FlatThreadedDropdown %> rowspan="2"<% end_if %>>
					<span><strong>$ForumThread.NumViews <% _t('VIEWS','Views') %></strong></span>
				</th>
			</tr>
		</thead>
		<tfoot>
			<tr class="category-footer rowTwo">
			<td class="author">&nbsp;</td>
			<td class="topicTitle">&nbsp;</td>
			<td class="views reads last">
				<span><strong>$ForumThread.NumViews <% _t('VIEWS','Views') %></strong></span>
			</td>
		</tr>
		<tr class="table-footer rowOne">
			<td class="page-numbers">
				<% if Posts.MoreThanOnePage %>
					<% if Posts.NotFirstPage %>
						<a class="prev" href="$Posts.PrevLink" title="<% _t('PREVTITLE','View the previous page') %>"><% _t('PREVLINK','Prev') %></a>
					<% end_if %>
				<% end_if %>
			</td>
			<td class="gotoButtonTop">
				<a class="icon arrow-up-skip" href="#forum-header" title="<% _t('CLICKGOTOTOP','Click here to go the top of this post') %>"><% _t('GOTOTOP','Go to Top') %></a>
			</td>
			<td class="last">
				<% if ForumThread.canCreate %>
					<a class="icon reply" href="$ReplyLink" title="<% _t('CLICKREPLY', 'Click to Reply') %>"><% _t('REPLY', 'Reply') %></a>
				<% end_if %>
				
				<% if Posts.MoreThanOnePage %>
					<% if Posts.NotLastPage %>
						<a class="next" href="$Posts.NextLink" title="<% _t('NEXTTITLE','View the next page') %>"><% _t('NEXTLINK','Next') %> &gt;</a>
					<% end_if %>
				<% end_if %>
			</td>
		</tr>
			
		</tfoot>
		
		<% loop Posts %>
			<tr id="post{$ID}" class="singlePost $EvenOdd post-item">
				<% include SinglePost %>
			</tr>
		<% end_loop %>
	</table>
	
	
	<% if AdminFormFeatures %>
		<div class="forum-admin-features">
			<h3>Forum Admin Features</h3>
			$AdminFormFeatures
		</div>
	<% end_if %>
<% include ForumFooter %>