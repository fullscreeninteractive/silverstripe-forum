<tr class="$EvenOdd <% if IsSticky || IsGlobalSticky %>stickyPost<% end_if %> <% if IsGlobalSticky %>globalSticky<% end_if %>">
	<td class="topicName">
		<h3><a class="topicTitle" href="$Link">$Title</a></h3>
		<p class="summary">
			<% _t('BY','By') %>
			<% loop Author %>
				<% if Link %>
					<a href="$Link" title="<% _t('CLICKTOUSER','Click here to view') %>"><% if Nickname %>$Nickname<% else %>Anon<% end_if %></a>
				<% else %>
					<span>Anon</span>
				<% end_if %>
			<% end_loop %>
			on $Created.Long
		</p>
	</td>
	<td class="count vertical">
		<span class="singleSpeech"><div class="w1"><div class="w2"><em><strong>$NumPosts</strong></em></div></div></span>
	</td>

	<td class="lastPost vertical">
		<% loop LatestPost %>
			<p class="user-info">$Created.Ago</p>
			<p class="user-info">
				<% _t('BY','by') %> <% loop Author %><% if Link %><a href="$Link" title="<% _t('CLICKTOUSER') %> <% if Nickname %>$Nickname.EscapeXML<% else %>Anon<% end_if %><% _t('CLICKTOUSER2') %>"><% if Nickname %>$Nickname<% else %>Anon<% end_if %></a><% else %><span>Anon</span><% end_if %><% end_loop %> <a href="$Link" title="<% sprintf(_t('GOTOFIRSTUNREAD',"Go to the first unread post in the %s topic."),$Title.EscapeXML) %>"><img src="forum/images/right.png" alt="" /></a>
			</p> 
		<% end_loop %>
	</td>
</tr>