<% include ForumHeader %>
	$PostMessageForm
	
	<div id="PreviousPosts">
		<ul id="Posts">
			<% loop Posts(DESC) %>
				<li class="$EvenOdd">
					<% include SinglePost %>
				</li>
			<% end_loop %>
		</ul>
		<div class="clear"><!-- --></div>
	</div>
	
<% include ForumFooter %>
