<% with SearchResults %>
<% if MoreThanOnePage %>
	<ul class="page-numbers">
	<% if NotFirstPage %>
		<li class="prev"><a href="$PrevLink">Prev</a></li>
	<% else %>	
		<li class="prev disabled"><a href="">Prev</a></li>
	<% end_if %>
	
	<% loop PaginationSummary(4) %>
		<% if CurrentBool %>
			<li class="active"><a href="">$PageNum</a></li>
		<% else %>
			<% if Link %>
				<li><a href="$Link">$PageNum</a></li>
			<% else %>
				<li><a href="">...</a></li>						
			<% end_if %>
		<% end_if %>
	<% end_loop %>
	<% if NotLastPage %>
		<li class="next"><a href="$NextLink">Next</a></li>
	<% else %>
		<li class="next disabled"><a href="">Next</a></li>
	<% end_if %>
	</ul>
<% end_if %>
<% end_with %>