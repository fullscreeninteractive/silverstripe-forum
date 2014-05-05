<% include ForumHeader %>
	
	<% if SearchResults %>
		<div id="forum_search" class="forumHolderFeatures">
			<p>$Abstract</p>
			<table class="forum-topics">
				<tr class="rowOne category">
					<td><% _t('ForumHolder_search_ss.THREAD', 'Thread') %></td>
					<td><% _t('ForumHolder_search_ss.ORDER', 'Order:') %>
						<a href="{$URLSegment}/search/?Search={$Query.ATT}&amp;order=newest" <% if Order = newest %>class="current"<% end_if %> title="<% _t('ForumHolder_search_ss.ORDERBYNEWEST', 'Order by Newest. Most recent posts first') %>"><% _t('ForumHolder_search_ss.NEWEST', 'Newest') %></a> |
						<a href="{$URLSegment}/search/?Search={$Query.ATT}&amp;order=oldest" <% if Order = oldest %>class="current"<% end_if %> title="<% _t('ForumHolder_search_ss.ORDERBYOLDEST', 'Order by Oldest. Oldest posts First') %>"><% _t('ForumHolder_search_ss.OLDEST', 'Oldest') %></a> |
						<a href="{$URLSegment}/search/?Search={$Query.ATT}&amp;order=title" <% if Order = title %>class="current"<% end_if %>title="<% _t('ForumHolder_search_ss.ORDERBYTITLE', 'Order by Title') %>"><% _t('ForumHolder_search_ss.TITLE', 'Title') %></a>
					</td>
					<td>
						<a href="$RSSLink"><% _t('ForumHolder_search_ss.RSSFEED', 'RSS Feed') %></a>
					</td>
				</tr>
				<% loop SearchResults.setPageLength(10) %>
				<tr class="$EvenOdd">
					<td class="forumCategory" colspan="3">
						<% loop Thread %>
							<a class="topicTitle" href="$Link" title="<% sprintf(_t('Forum.ss.GOTOTHISTOPIC',"Go to the %s topic"),$Title) %>">$Title</a>
						<% end_loop %>
					
						<p>$Content.ContextSummary.RAW <span class="dateInfo">$Created.Ago</span></p>
					</td>
				</tr>
				<% end_loop %>
				<% if SearchResults.MoreThanOnePage %>
				<tr class="rowOne category">
					<td class="forum-pagination" colspan="3">
						<% include ForumPagination %>
					</td>
				</tr>
				<% end_if %>
			</table>
		</div>
	<% else %>
		<div class="forumHolderFeatures">
			<table id="TopicList">
				<tr><td><% _t('ForumHolder_search_ss.NORESULTS','There are no results for those word(s)') %></td></tr>
			</table>
		</div>
	<% end_if %>

<% include ForumFooter %>