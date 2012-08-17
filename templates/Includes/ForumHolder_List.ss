<tr>
	<td>
		<a class="topic-title" href="$Link">$Title</a>
		<% if Content || Moderators %>
			<div class="summary">
				<p>$Content.LimitCharacters(80)</p>
			<% if Moderators %>
				<p>Moderators: <% loop Moderators %>
				<a href="$Link">$Nickname</a>
				<% if not Last %>, <% end_if %><% end_loop %></p>
			<% end_if %>
			</div>
		<% end_if %>
	</td>
	<td class="count">
		$NumTopics
	</td>
	<td class="count">
		$NumPosts
	</td>
	<td class="">
		<% if LatestPost %>
			<% with LatestPost %>
				<p class="post-date">$Created.Ago</p>
				<% with Author %>
					<p>by <% if Link %><a href="$Link"><% if Nickname %>$Nickname<% else %>Anon<% end_if %></a><% else %><span>Anon</span><% end_if %></p>
				<% end_with %>
			<% end_with %>
		<% end_if %>
	</td>
</tr>