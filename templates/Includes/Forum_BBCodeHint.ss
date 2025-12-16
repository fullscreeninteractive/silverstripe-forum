<% if $BBTags %>
	<div id="forum__bbcode-holder" class="forum__bbcode-holder forum__bbcode-holder--hide">
		<h2 class="forum__bbcode-examples"><%t Forum_BBCodeHint_ss.AVAILABLEBB "Available BB Code tags" %></h2>
		<ul class="forum__bbcode-examples">
			<% loop $BBTags %>
				<li class="forum__bbcode-item $FirstLast">
					<strong>$Title</strong><% if $Description %>: $Description<% end_if %> <span class="forum__bbcode-example">$Example</span>
				</li>
			<% end_loop %>
		</ul>
	</div>
<% end_if %>
