<% include ForumHeader %>
	<div class="forumHolderFeatures">

		<div id="SortThreadsBy">
			<p><% _t('ForumHolder_popularthreas_ss.SORTTHREADSBY', 'Sort threads by:') %> <a<% if $Method == 'posts' %> class="current"<% end_if %> href="{$Link}popularthreads?by=posts"><% _t('ForumHolder_popularthreas_ss.POSTCOUNT', 'Post count') %></a> | <a<% if $Method == 'views' %> class="current"<% end_if %> href="{$Link}popularthreads?by=views"><% _t('ForumHolder_popularthreas_ss.NUMVIEWS', 'Number of views') %></a></p>
		</div>

		<table id="ThreadsList">
			<tr class="head">
				<th><% _t('ForumHolder_popularthreas_ss.POSTS', 'Posts') %></th>
				<th><% _t('ForumHolder_popularthreas_ss.VIEWS', 'Views') %></th>
				<th><% _t('ForumHolder_popularthreas_ss.TITLE', 'Title') %></th>
				<th><% _t('ForumHolder_popularthreas_ss.DATECREATED', 'Date created') %></th>
			</tr>

			<% loop $Threads %>
				<tr class="$EvenOdd">
					<td>$Posts.Count</td>
					<td>$NumViews</td>
					<td><a href="$Link">$Title</a></td>
					<td>$Created.Nice</td>
				</tr>
			<% end_loop %>
		</table>

		<% if $Threads.MoreThanOnePage %>
			<div id="ThreadsPagination">
				<p>
					<% if $Threads.NotFirstPage %>
						<a class="prev" href="$Threads.PrevLink" title="View the previous page"><% _t('ForumHolder_popularthreas_ss.PREV', 'Prev') %></a>
					<% end_if %>

					<span>
				    	<% loop $Threads.PaginationSummary(4) %>
							<% if $CurrentBool %>
								$PageNum
							<% else %>
								<% if $PageNum %>
									<a href="$Link">$PageNum</a>
								<% else %>
									...
								<% end_if %>
							<% end_if %>
						<% end_loop %>
					</span>

					<% if $Threads.NotLastPage %>
						<a class="next" href="$Threads.NextLink"><% _t('ForumHolder_popularthreas_ss.NEXT', 'Next') %></a>
					<% end_if %>
				</p>
			</div>
		<% end_if %>
	</div>

<% include ForumFooter %>
