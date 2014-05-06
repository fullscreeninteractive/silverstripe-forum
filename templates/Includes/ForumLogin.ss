<div id="RegisterLogin">
	<% if CurrentMember %>
		<p>
			<%t ForumLogin.ss.LOGGEDINAS %> <% if CurrentMember.Nickname %>$CurrentMember.Nickname<% else %><% _t('ANONYMOUS','Anonymous') %><% end_if %> | 
			<a href="$ForumHolder.Link(logout)" title="<% _t('LOGOUTEXPLICATION','Click here to log out') %>"><% _t('LOGOUT','Log Out') %></a> | <a href="ForumMemberProfile/edit" title="<% _t('PROFILEEXPLICATION','Click here to edit your profile') %>"><% _t('PROFILE','Profile') %></a></p>
	<% else %>
		<p>
			<a href="$ForumHolder.Link(login)" title="<% _t('LOGINEXPLICATION','Click here to login') %>"><% _t('LOGIN','Login') %></a> |
			<a href="Security/lostpassword" title="<% _t('LOSTPASSEXPLICATION','Click here to retrieve your password') %>"><% _t('LOSTPASS','Forgot password') %></a> |
			<a href="ForumMemberProfile/register" title="<% _t('REGEXPLICATION','Click here to register') %>"><% _t('REGISTER','Register') %></a>
			<% if OpenIDAvailable %> |
				<a href="ForumMemberProfile/registerwithopenid" title="<% _t('OPENIDEXPLICATION','Click here to register with OpenID') %>">Register with OpenID <% _t('OPENID','register with OpenID') %> <img src="sapphire/images/openid-small.gif" alt="<% _t('OPENIDEXPLICATION') %>"/></a>
				(<a href="#" id="ShowOpenIDdesc"><% _t('WHATOPENID','What is OpenID?') %></a>)
			<% end_if %>
		</p>
		<div id="OpenIDDescription">
	  		<span><a href="#" id="HideOpenIDdesc">X</a></span>
			<h2><% _t('WHATOPENIDUPPER','What is OpenID?') %></h2>
			<p><% _t('OPENIDDESC1','OpenID is an Internet-wide identity system that allows you to sign in to many websites with a single account.') %></p>
			<p><% _t('OPENIDDESC2','With OpenID, your ID becomes a URL (e.g. http://<strong>username</strong>.myopenid.com/). You can get a free OpenID for example from <a href="http://www.myopenid.com">myopenid.com</a>.') %></p>
			<p><% _t('OPENIDDESC3','For more information visit the <a href="http://openid.net">official OpenID site.') %></a></p>
		</div>
	<% end_if %>
</div>
