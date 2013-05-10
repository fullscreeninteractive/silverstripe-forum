<tr class="$EvenOdd<% if IsGlobalSticky %> globalSticky stickyPost<% end_if %>">
	<td class="forumCategory odd">
		<h3><a class="topicTitle" href="$Link">$Title</a></h3>
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

	<td class="count even vertical">
		<div class="doubleSpeech"><div class="w1"><div class="w2"><em><strong>
			
		<% if IsGlobalSticky %>*<% else %>$NumTopics<% end_if %>

			</strong></em></div></div></div>
		
	</td>
	<td class="count odd vertical">
		<div class="singleSpeech"><div class="w1"><div class="w2"><em><strong>
		$NumPosts
		</strong></em></div></div></div>
	</td>
	<td class="even lastPost vertical">
		<% if LatestPost %>
			<% with LatestPost %>
				<p>
					<a class="topicTitle" href="$Link">
						$Title.LimitCharacters(20)			
					</a>
				</p>			
				<% with Author %>
					<p>by <% if Link %><a href="$Link"><% if Nickname %>$Nickname<% else %>Anon<% end_if %></a><% else %><span>Anon</span><% end_if %></p>
				<% end_with %>
				<p class="post-date">$Created.Ago</p>
			<% end_with %>
		<% end_if %>
	</td>
</tr>