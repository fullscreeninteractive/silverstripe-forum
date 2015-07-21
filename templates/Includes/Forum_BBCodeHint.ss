<% if $BBTags %>
	<div id="BBTagsHolder" class="hide">
		<h2 class="bbcodeExamples"><% _t('Forum_BBCodeHint_ss.AVAILABLEBB','Available BB Code tags') %></h2>
		<ul class="bbcodeExamples">
			<% loop $BBTags %>
				<li class="$FirstLast">
					<strong>$Title</strong><% if $Description %>: $Description<% end_if %> <span class="example">$Example</span>
				</li>
			<% end_loop %>
		</ul>
	</div>
<% end_if %>
