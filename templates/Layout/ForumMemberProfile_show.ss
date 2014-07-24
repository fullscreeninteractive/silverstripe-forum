<% include ForumHeader %>
	<% if $Member.IsBanned %><h2>This user has been banned. Please contact us if you believe this is a mistake</h2>
	<% else_if $Member.isGhost %><h2>This user has been ghosted. Please contact us if you believe this is a mistake</h2>	
	<% else %>
		<% with Member %>		
			<div id="UserProfile">
				<h2><% if Nickname %>$Nickname<% else %>Anon<% end_if %>&#39;s <% _t('ForumMemberProfile_show_ss.PROFILE','Profile') %></h2>
				<% if isSuspended %>
					<p class="message warning suspensionWarning">
						<% _t('ForumMemberProfile_show_ss.ForumRole.SUSPENSIONNOTE') %>
					</p>
				<% end_if %>
				<div id="ForumProfileNickname"><label class="left"><% _t('ForumMemberProfile_show_ss.NICKNAME','Nickname') %>:</label> <p class="readonly"><% if Nickname %>$Nickname<% else %>Anon<% end_if %></p></div>
				<% if FirstNamePublic %>
				<div id="ForumProfileFirstname"><label class="left"><% _t('ForumMemberProfile_show_ss.FIRSTNAME','First Name') %>:</label> <p class="readonly">$FirstName</p></div>
				<% end_if %>
				<% if SurnamePublic %>
				<div id="ForumProfileSurname"><label class="left"><% _t('ForumMemberProfile_show_ss.SURNAME','Surname') %>:</label> <p class="readonly">$Surname</p></div>
				<% end_if %>
				<% if EmailPublic %>
				<div id="ForumProfileEmail"><label class="left"><% _t('ForumMemberProfile_show_ss.EMAIL','Email') %>:</label> <p class="readonly"><a href="mailto:$Email">$Email</a></p></div>
				<% end_if %>
				<% if OccupationPublic %>
				<div id="ForumProfileOccupation"><label class="left"><% _t('ForumMemberProfile_show_ss.OCCUPATION','Occupation') %>:</label> <p class="readonly">$Occupation</p></div>
				<% end_if %>
				<% if CompanyPublic %>
				<div id="ForumProfileCompany"><label class="left"><% _t('ForumMemberProfile_show_ss.COMPANY', 'Company') %>:</label> <p class="readonly">$Company</p></div>
				<% end_if %>
				<% if CityPublic %>
				<div id="ForumProfileCity"><label class="left"><% _t('ForumMemberProfile_show_ss.CITY','City') %>:</label> <p class="readonly">$City</p></div>
				<% end_if %>
				<% if CountryPublic %>
				<div id="ForumProfileCountry"><label class="left"><% _t('ForumMemberProfile_show_ss.COUNTRY','Country') %>:</label> <p class="readonly">$FullCountry</p></div>
				<% end_if %>
				<div id="ForumProfilePosts"><label class="left"><% _t('ForumMemberProfile_show_ss.POSTNO','Number of posts') %>:</label> <p class="readonly">$NumPosts</p></div>
				<div id="ForumProfileRank"><label class="left"><% _t('ForumMemberProfile_show_ss.FORUMRANK','Forum ranking') %>:</label> <% if ForumRank %><p class="readonly">$ForumRank</p><% else %><p><% _t('ForumMemberProfile_show_ss.NORANK','No ranking') %></p><% end_if %></div>

				<div id="ForumProfileAvatar">
					<label class="left"><% _t('ForumMemberProfile_show_ss.AVATAR','Avatar') %>:</label> 
					<p><img class="userAvatar" src="$FormattedAvatar" width="80" alt="<% if Nickname %>$Nickname<% else %>Anon<% end_if %><% _t('ForumMemberProfile_show_ss.USERSAVATAR','&#39;s avatar') %>" /></p>
				</div>
			</div>
		<% end_with %>
		<% if LatestPosts %>
			<div id="MemberLatestPosts">
				<h2><% _t('ForumMemberProfile_show_ss.LATESTPOSTS','Latest Posts') %></h2>
				<ul>
					<% loop LatestPosts %>
						<li><a href="$Link#post$ID">$Title</a> (<% sprintf(_t('ForumMemberProfile_show_ss.LASTPOST',"Last post: %s "),$Created.Ago) %>)</li>
					<% end_loop %>
				</ul>
			</div>
		<% end_if %>
	<% end_if %>
<% include ForumFooter %>
