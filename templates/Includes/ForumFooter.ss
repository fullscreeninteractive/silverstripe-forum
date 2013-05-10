<% with ForumHolder %>
<div id="forum-footer">
	<div id="forum-stats">
		<p>
			<strong><% _t('CURRENTLYON','Currently Online:') %></strong>
			<% if CurrentlyOnline %>
				<% loop CurrentlyOnline %>
					<% if Link %>
						<a href="$Link" title="<% if Nickname %>$Nickname<% else %>Anon<% end_if %><% _t('ISONLINE',' is online') %>">
							<% if Nickname %>$Nickname
							<% else %>Anon
							<% end_if %>
						</a>
					<% else %>
						<span>Anon</span>
					<% end_if %>
					<% if not Last %>,<% end_if %>
				<% end_loop %>
			<% else %>
					<span><% _t('NOONLINE','There is nobody online.') %></span>
			<% end_if %>
		</p>
		
			<% if Moderators %>
				<p>
					Moderators: 
					<% loop Moderators %>
						<a href="$Link">$Nickname</a>
						<% if not Last %>, <% end_if %>
					<% end_loop %>
				</p>
			<% end_if %>
	
			<strong><% _t('LATESTMEMBER','Welcome to our latest member:') %></strong>			
			<% if LatestMembers(1) %>
				<% loop LatestMembers(1) %>
					<% if Link %>
						<a href="$Link" <% if Nickname %>title="$Nickname<% _t('ISONLINE') %>"<% end_if %>><% if Nickname %>$Nickname<% else %>Anon<% end_if %></a><% if Last %><% else %>,<% end_if %> 
					<% else %>
						<span>Anon</span><% if not Last %>,<% end_if %> 
					<% end_if %>
				<% end_loop %>
			<% end_if %>
		
			<% if NumPosts %>
			<p class="forumStats">
				$NumPosts 
				<strong><% _t('POSTS','Posts') %></strong> 
				<% _t('IN','in') %> $NumTopics <strong><% _t('TOPICS','Topics') %></strong> 
				<% _t('BY','by') %> $NumAuthors <strong><% _t('MEMBERS','members') %></strong>
			</p>
		<% end_if %>
	</div>
	<span class="forum-search-dropdown-icon"></span>
	<div id="forum-find" style="overflow:hidden;" class="forum-search-bar">
		<form class="forum-search" action="$Link(search)" method="get">
			<fieldset>
				<input id="search-text" class="text active" type="text" name="Search" value="$Query.ATT" />
				<input class="submit action button" type="submit" value="<% _t('SEARCHBUTTON','L') %>"/>
			</fieldset>
		</form>
		<form id="forum-jump" action="#">
			<label for="forum-jump-select"><% _t('JUMPTO','Jump to:') %></label>
			<select id="forum-jump-select" onchange="if(this.value) location.href = this.value">
				<option value=""><% _t('JUMPTO','Jump to:') %></option>
				<% if ShowInCategories %>
					<% loop Forums %>
						<optgroup label="$Title">
							<% loop CategoryForums %>
								<% if can(view) %>
									<option value="$Link">$Title</option>
								<% end_if %>
							<% end_loop %>
						</optgroup>
					<% end_loop %>					
				<% else %>
					<% loop Forums %>
						<% if can(view) %>
							<option value="$Link">$Title</option>
						<% end_if %>
					<% end_loop %>
				<% end_if %>
			</select>
		</form>		
	</div>	
</div>
<% end_with %>
</div>