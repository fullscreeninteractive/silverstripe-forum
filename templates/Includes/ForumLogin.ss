<div id="forum__register" class="forum__register">
    <% if $CurrentMember %>
        <p>
            <%t ForumLogin_ss.LOGGEDINAS "You're logged in as" %> <% if $CurrentMember.Nickname %>$CurrentMember.Nickname<% else %><%t ForumLogin_ss.ANONYMOUS "Anonymous" %><% end_if %> |
            <a href="$ForumHolder.Link('logout')" title="<%t ForumLogin_ss.LOGOUTEXPLICATION "Click here to log out" %>"><%t ForumLogin_ss.LOGOUT "Log Out" %></a> | <a href="{$CurrentMember.MemberProfileLink}/edit" title="<%t ForumLogin_ss.PROFILEEXPLICATION "Click here to edit your profile" %>"><%t ForumLogin_ss.PROFILE "Profile" %></a></p>
    <% else %>
        <p>
            <a href="$ForumHolder.Link('login')" title="<%t ForumLogin_ss.LOGINEXPLICATION "Click here to login" %>"><%t ForumLogin_ss.LOGIN "Login" %></a> |
            <a href="Security/lostpassword" title="<%t ForumLogin_ss.LOSTPASSEXPLICATION "Click here to retrieve your password" %>"><%t ForumLogin_ss.LOSTPASS "Forgot password" %></a> |

            <% if $ForumHolder.canRegister %>
                <a href="{$Form.Link('register')}" title="<%t ForumLogin_ss.REGEXPLICATION "Click here to register" %>"><%t ForumLogin_ss.REGISTER "Register" %></a>
            <% end_if %>
        </p>
    <% end_if %>
</div>
