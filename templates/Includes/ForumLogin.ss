<div id="forum__register" class="forum__register">
    <% if $CurrentMember %>
        <p>
            <%t ForumLogin_ss.LOGGEDINAS "You're logged in as" %> <% if $CurrentMember.Nickname %>$CurrentMember.Nickname<% else %><%t ForumLogin_ss.ANONYMOUS "Anonymous" %><% end_if %> |
            <a href="$ForumHolder.Link('logout')" title="<%t ForumLogin_ss.LOGOUTEXPLICATION "Click here to log out" %>"><%t ForumLogin_ss.LOGOUT "Log Out" %></a> | <a href="ForumMemberProfile/edit" title="<%t ForumLogin_ss.PROFILEEXPLICATION "Click here to edit your profile" %>"><%t ForumLogin_ss.PROFILE "Profile" %></a></p>
    <% else %>
        <p>
            <a href="$ForumHolder.Link('login')" title="<%t ForumLogin_ss.LOGINEXPLICATION "Click here to login" %>"><%t ForumLogin_ss.LOGIN "Login" %></a> |
            <a href="Security/lostpassword" title="<%t ForumLogin_ss.LOSTPASSEXPLICATION "Click here to retrieve your password" %>"><%t ForumLogin_ss.LOSTPASS "Forgot password" %></a> |
            <a href="ForumMemberProfile/register" title="<%t ForumLogin_ss.REGEXPLICATION "Click here to register" %>"><%t ForumLogin_ss.REGISTER "Register" %></a>
            <% if $OpenIDAvailable %> |
                <a href="ForumMemberProfile/registerwithopenid" title="<%t ForumLogin_ss.OPENIDEXPLICATION "Click here to register with OpenID" %>">Register with OpenID <%t ForumLogin_ss.OPENID "register with OpenID" %> <img src="sapphire/images/openid-small.gif" alt="<%t ForumLogin_ss.OPENIDEXPLICATION "" %>"/></a>
                (<a href="#" id="forum__openid-desc"><%t ForumLogin_ss.WHATOPENID "What is OpenID?" %></a>)
            <% end_if %>
        </p>
        <div id="forum__openid-description" class="forum__openid-description">
              <span><a href="#" id="forum__hide-openid-desc">X</a></span>
            <h2><%t ForumLogin_ss.WHATOPENIDUPPER "What is OpenID?" %></h2>
            <p><%t ForumLogin_ss.OPENIDDESC1 "OpenID is an Internet-wide identity system that allows you to sign in to many websites with a single account." %></p>
            <p><%t ForumLogin_ss.OPENIDDESC2 "With OpenID, your ID becomes a URL (e.g. http://<strong>username</strong>.myopenid.com/). You can get a free OpenID for example from <a href=\"http://www.myopenid.com\">myopenid.com</a>." %></p>
            <p><%t ForumLogin_ss.OPENIDDESC3 "For more information visit the <a href=\"http://openid.net\">official OpenID site." %></a></p>
        </div>
    <% end_if %>
</div>
