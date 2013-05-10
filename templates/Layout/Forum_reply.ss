<% include ForumHeader %>
	$PostMessageForm
	
	<div id="PreviousPosts">
		<table id="posts" class="forum-table">

			<tbody>
			<% loop Posts(DESC) %>
				<tr id="post{$ID}" class="singlePost $EvenOdd post-item">
					<% include SinglePost %>
				</tr>
			<% end_loop %>
			</tbody>
		</table>
	</div>
	
<% include ForumFooter %>