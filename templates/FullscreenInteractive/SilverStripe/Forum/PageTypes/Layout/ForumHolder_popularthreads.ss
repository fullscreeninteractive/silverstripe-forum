<% include ForumHeader %>
	<div class="forum__features">

		<div id="forum__sort-threads" class="forum__sort-threads">
			<p><%t ForumHolder_popularthreas_ss.SORTTHREADSBY "Sort threads by:" %> <a<% if $Method == 'posts' %> class="forum__pagination-link--current"<% end_if %> href="{$Link}popularthreads?by=posts"><%t ForumHolder_popularthreas_ss.POSTCOUNT "Post count" %></a> | <a<% if $Method == 'views' %> class="forum__pagination-link--current"<% end_if %> href="{$Link}popularthreads?by=views"><%t ForumHolder_popularthreas_ss.NUMVIEWS "Number of views" %></a></p>
		</div>

		<table id="forum__threads-list" class="forum__threads-list">
			<tr class="forum__head">
				<th><%t ForumHolder_popularthreas_ss.POSTS "Posts" %></th>
				<th><%t ForumHolder_popularthreas_ss.VIEWS "Views" %></th>
				<th><%t ForumHolder_popularthreas_ss.TITLE "Title" %></th>
				<th><%t ForumHolder_popularthreas_ss.DATECREATED "Date created" %></th>
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
			<div id="forum__threads-pagination" class="forum__threads-pagination">
				<p>
					<% if $Threads.NotFirstPage %>
						<a class="forum__pagination-link--prev" href="$Threads.PrevLink" title="View the previous page"><%t ForumHolder_popularthreas_ss.PREV "Prev" %></a>
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
						<a class="forum__pagination-link--next" href="$Threads.NextLink"><%t ForumHolder_popularthreas_ss.NEXT "Next" %></a>
					<% end_if %>
				</p>
			</div>
		<% end_if %>
	</div>

<% include ForumFooter %>
