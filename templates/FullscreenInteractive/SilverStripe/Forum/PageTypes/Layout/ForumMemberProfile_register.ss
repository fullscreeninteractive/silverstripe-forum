<% include ForumHeader %>
$Content
<div id="forum__profile" class="forum__profile">
    <% if $CurrentMember %>
        <p><%t ForumMemberProfile_register_ss.PLEASELOGOUT "Please logout before you register" %> - <a href="Security/logout"><%t ForumMemberProfile_register_ss.LOGOUT "Logout" %></a></p>
    <% else %>
        $RegistrationForm
    <% end_if %>
</div>

<% include ForumFooter %>
