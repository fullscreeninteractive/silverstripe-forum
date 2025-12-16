<% include ForumHeader %>
    <% if $Member.IsBanned %><h2>This user has been banned. Please contact us if you believe this is a mistake</h2>
    <% else_if $Member.isGhost %><h2>This user has been ghosted. Please contact us if you believe this is a mistake</h2>
    <% else %>
        <% with $Member %>
            <div id="forum__profile" class="forum__profile">
                <h2><% if $Nickname %>$Nickname<% else %>Anon<% end_if %>&#39;s <%t ForumMemberProfile_show_ss.PROFILE "Profile" %></h2>
                <% if $isSuspended %>
                    <p class="forum__message forum__message--warning forum__message--suspension">
                        <%t ForumMemberProfile_show_ss.ForumRole.SUSPENSIONNOTE "" %>
                    </p>
                <% end_if %>
                <div id="forum__profile-field--nickname" class="forum__profile-field forum__profile-field--nickname"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.NICKNAME "Nickname" %>:</label> <p class="forum__readonly"><% if $Nickname %>$Nickname<% else %>Anon<% end_if %></p></div>
                <% if $FirstNamePublic %>
                <div id="forum__profile-field--firstname" class="forum__profile-field forum__profile-field--firstname"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.FIRSTNAME "First Name" %>:</label> <p class="forum__readonly">$FirstName</p></div>
                <% end_if %>
                <% if $SurnamePublic %>
                <div id="forum__profile-field--surname" class="forum__profile-field forum__profile-field--surname"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.SURNAME "Surname" %>:</label> <p class="forum__readonly">$Surname</p></div>
                <% end_if %>
                <% if $EmailPublic %>
                <div id="forum__profile-field--email" class="forum__profile-field forum__profile-field--email"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.EMAIL "Email" %>:</label> <p class="forum__readonly"><a href="mailto:$Email">$Email</a></p></div>
                <% end_if %>
                <% if $OccupationPublic %>
                <div id="forum__profile-field--occupation" class="forum__profile-field forum__profile-field--occupation"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.OCCUPATION "Occupation" %>:</label> <p class="forum__readonly">$Occupation</p></div>
                <% end_if %>
                <% if $CompanyPublic %>
                <div id="forum__profile-field--company" class="forum__profile-field forum__profile-field--company"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.COMPANY "Company" %>:</label> <p class="forum__readonly">$Company</p></div>
                <% end_if %>
                <% if $CityPublic %>
                <div id="forum__profile-field--city" class="forum__profile-field forum__profile-field--city"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.CITY "City" %>:</label> <p class="forum__readonly">$City</p></div>
                <% end_if %>
                <% if $CountryPublic %>
                <div id="forum__profile-field--country" class="forum__profile-field forum__profile-field--country"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.COUNTRY "Country" %>:</label> <p class="forum__readonly">$FullCountry</p></div>
                <% end_if %>
                <div id="forum__profile-field--posts" class="forum__profile-field forum__profile-field--posts"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.POSTNO "Number of posts" %>:</label> <p class="forum__readonly">$NumPosts</p></div>
                <div id="forum__profile-field--rank" class="forum__profile-field forum__profile-field--rank"><label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.FORUMRANK "Forum ranking" %>:</label> <% if $ForumRank %><p class="forum__readonly">$ForumRank</p><% else %><p><%t ForumMemberProfile_show_ss.NORANK "No ranking" %></p><% end_if %></div>

                <div id="forum__profile-field--avatar" class="forum__profile-field forum__profile-field--avatar">
                    <label class="forum__label forum__label--left"><%t ForumMemberProfile_show_ss.AVATAR "Avatar" %>:</label>
                    <p><img class="forum__avatar" src="$FormattedAvatar" width="80" alt="<% if $Nickname %>$Nickname<% else %>Anon<% end_if %><%t ForumMemberProfile_show_ss.USERSAVATAR "&#39;s avatar" %>" /></p>
                </div>
            </div>
        <% end_with %>
        <% if $LatestPosts %>
            <div id="forum__latest-posts" class="forum__latest-posts">
                <h2><%t ForumMemberProfile_show_ss.LATESTPOSTS "Latest Posts" %></h2>
                <ul>
                    <% loop $LatestPosts %>
                        <li><a href="$Link#post$ID">$Title</a> (<%t ForumMemberProfile_show_ss.LASTPOST "Last post: {ago}" ago=$Created.Ago %>)</li>
                    <% end_loop %>
                </ul>
            </div>
        <% end_if %>
    <% end_if %>
<% include ForumFooter %>
