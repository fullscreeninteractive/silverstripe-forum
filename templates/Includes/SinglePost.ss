<div id="post{$ID}" class="forum-post">
	<div class="user-info">
		<% with Author %>
			<a class="author-link" href="$Link" title="<%t GOTOPROFILE "Go to this User's Profile" %>">$Nickname</a><br />

			<img class="avatar" src="$FormattedAvatar" alt="Avatar" /><br />
			<% if $ForumRank %><span class="forum-rank">$ForumRank</span><br /><% end_if %>
			<% if $NumPosts %>
				<span class="post-count">$NumPosts
				<% if $NumPosts = 1 %>
					<%t POST "Post" %>
				<% else %>
					<%t POSTS "Posts" %>
				<% end_if %>
				</span>
			<% end_if %>
		<% end_with %>
	</div><!-- user-info. -->

	<div class="user-content">

		<div class="quick-reply">
			<% if $Thread.canPost %>
				<p>
				    <a href="$Top.ReplyLink" class="replyLink"><%t Post.REPLYLINK %></a>
				</p>
			<% end_if %>
		</div>
		<h4><a href="$Link">$Title <img src="forum/images/right.png" alt="Link to this post" title="Link to this post" /></a></h4>
		<p class="post-date">$Created.Long at $Created.Time
		<% if $Updated %>
			<strong><%t LASTEDITED "Last edited:" %> $Updated.Long <%t AT %> $Updated.Time</strong>
		<% end_if %></p>

		<% if $EditLink || $DeleteLink %>
			<div class="post-modifiers">
				<% if $EditLink %>
					<a href="$EditLink" class="editPostLink"><%t Post.EDIT %></a>
				<% end_if %>

				<% if $DeleteLink %>
					<a href="$DeleteLink" class="deleteLink<% if $IsFirstPost %> firstPost<% end_if %>"><%t Post.DELETE %></a>
				<% end_if %>

				<% if $MarkAsSpamLink %>
					<a href="$MarkAsSpamLink" class="markAsSpamLink<% if $IsFirstPost %> firstPost<% end_if %>" rel="$ID"><%t Post.MARKASSPAM %></a>
				<% end_if %>

				<% if $BanLink || $GhostLink %>
					|
					<% if $BanLink %>
					<a class="banLink" href="$BanLink" rel="$AuthorID"><%t Post.BANUSER %></a>
					<% end_if %>
					<% if $GhostLink %>
					<a class="ghostLink" href="$GhostLink" rel="$AuthorID"><%t Post.GHOSTUSER %></a>
					<% end_if %>
				<% end_if %>

			</div>
		<% end_if %>
		<div class="post-type">
			$Content.Parse(BBCodeParser)
		</div>

		<% if $Thread.DisplaySignatures %>
			<% with Author %>
				<% if $Signature %>
					<div class="signature">
						<p>$Signature</p>
					</div>
				<% end_if %>
			<% end_with %>
		<% end_if %>

		<% if $Attachments %>
			<div class="attachments">
				<strong><%t ATTACHED "Attached Files" %></strong>
				<ul class="post-attachments">
				<% loop $Attachments %>
					<li>
						<a href="$Link"><img src="$Icon"></a>
						<a href="$Link">$Name</a><br />
						<% if $ClassName = "Image" %>$Width x $Height - <% end_if %>$Size
					</li>
				<% end_loop %>
				</ul>
			</div>
		<% end_if %>
	</div>
	<div class="clear"><!-- --></div>
</div><!-- forum-post. -->
