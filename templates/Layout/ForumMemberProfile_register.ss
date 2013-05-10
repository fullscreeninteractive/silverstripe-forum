<div class="column three" id="sidebar">
	<% include SideMenu %>
</div>
<div class="column nine" id="content">
	<% include ForumHeader %>
	$Content
	<div id="user-profile">
		<% if CurrentMember %>
			<p><% _t('PLEASELOGOUT', 'Please logout before you register') %> - <a href="Security/logout"><% _t('LOGOUT', 'Logout') %></a></p>
		<% else %>
			$RegistrationForm
		<% end_if %>
	</div>
	<% include ForumFooter %>
</div>