<div id="RegisterLogin">
	<% if CurrentMember %>
		<p>
			<% _t('LOGGEDINAS','You are logged in as') %> 
			<% if CurrentMember.Nickname %>$CurrentMember.Nickname
			<% else %><% _t('ANONYMOUS','Anonymous') %><% end_if %> | 
			<a href="$ForumHolder.Link(logout)" title="<% _t('LOGOUTEXPLICATION','Click here to log out') %>"><% _t('LOGOUT','Log Out') %></a> | <a href="ForumMemberProfile/edit" title="<% _t('PROFILEEXPLICATION','Click here to edit your profile') %>"><% _t('PROFILE','Profile') %></a>
		</p>
	<% else %>
		<ul>
			<li><a href="$ForumHolder.Link(login)" title="<% _t('LOGINEXPLICATION','Click here to login') %>"><% _t('LOGIN','Login') %></a></li>
			<li><a href="Security/lostpassword" title="<% _t('LOSTPASSEXPLICATION','Click here to retrieve your password') %>"><% _t('LOSTPASS','Forgot password') %></a></li>
			<li><a href="ForumMemberProfile/register" title="<% _t('REGEXPLICATION','Click here to register') %>"><% _t('REGISTER','Register') %></a></li>
			<% if OpenIDAvailable %>
			<li><a href="ForumMemberProfile/registerwithopenid" title="<% _t('OPENIDEXPLICATION','Click here to register with OpenID') %>">Register with OpenID <% _t('OPENID','register with OpenID') %> <img src="sapphire/images/openid-small.gif" alt="<% _t('OPENIDEXPLICATION') %>"/></a>
				(<a href="#" id="ShowOpenIDdesc"><% _t('WHATOPENID','What is OpenID?') %></a>)</li>
			<% end_if %>
		</ul>
	<% end_if %>
</div>
