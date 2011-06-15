<p><% sprintf(_t('HI',"Hi %s,"),$Author.Nickname) %>,</p>

<% if NewThread %>
	<p><% _t('MODERATORNEWTHREADMESSAGE', "New forum thread has been started") %>.</p>
<% else %>
	<p><% _t('MODERATORNEWPOSTMESSAGE',"A forum post has been added or edited") %>.</p>
<% end_if %>

<h3>Content</h3>
<blockquote>
	<p>
		<strong>$Post.Title</strong><br/>
		<% if Author %> <% _t('BY', "by") %> <em>$Author.Nickname</em><% end_if %>
		<% _t('DATEON', "on") %> $Post.LastEdited.Nice.
	</p>
	<% control Post %>
		<p>$Content.Parse(BBCodeParser)</p>
	<% end_if %>
</blockquote>

<h3>Actions</h3>
<ul>
	<li><a href="$Post.Link"><% _t('MODERATORMODERATE', "Moderate the thread") %></a></li>
</ul>

<p>
	<% _t('MODERATORSIGNOFF', "Yours truly,\nThe Forum Robot.") %>
</p>

<p>
	<% _t('MODERATORNOTE', "NOTE: This is an automated email sent to all moderators of this forum.") %>
</p>

