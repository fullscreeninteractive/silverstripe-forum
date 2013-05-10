<td class="user-information">
	<% loop Author %>
		<img class="userAvatar" src="$FormattedAvatar" alt="Avatar" /><br />
		<a class="authorTitle" href="$Link" title="<% _t('GOTOPROFILE','Go to this Users Profile') %>">$Nickname</a><br />
		<% if ForumRank %><span class="rankingTitle expert">$ForumRank</span><br /><% end_if %>
		<% if NumPosts %><span class="postCount">$NumPosts posts</span><% end_if %>
	<% end_loop %>
</td>

<td class="posterContent" colspan="2">
	<h4><a href="$Link">$Title</a></h4>
	<div class="postType">
		$Content.Parse(BBCodeParser)
	</div>
	
	<% if Thread.DisplaySignatures %>
		<% loop Author %>
			<% if Signature %>
				<div class="signature">
					<p>$Signature</p>
				</div>
			<% end_if %>
		<% end_loop %>
	<% end_if %>

	<% if Attachments %>
		<div class="attachments">
			<strong><% _t('ATTACHED','Attached Files') %></strong> 
			<ul class="attachmentList">
			<% loop Attachments %>
				<li class="attachment">
					<a href="$Link"><img src="$Icon"></a>
					<a href="$Link">$Name</a><br />
					<% if ClassName = "Image" %>$Width x $Height - <% end_if %>$Size
				</li>
			<% end_loop %>
			</ul>
		</div>
	<% end_if %>

	<% if EditLink || DeleteLink %>
		<div class="post-modifiers">
		<% if DeleteLink %>
			<span class="icon delete">$DeleteLink</span>
		<% end_if %>
		
		<% if MarkAsSpamLink %>
			<span class="icon delete">$MarkAsSpamLink</span>
		<% end_if %>
		
		<% if EditLink %>
			<span class="icon edit">$EditLink</span>
		<% end_if %>
		
	<% end_if %>	
</td>
