<% include ForumHeader %>
	<div class="forumHolderFeatures">

		<table id="MembersList">
			<tr class="head">
				<th><a href="{$URLSegment}/memberlist/?order=name" title="<% _t('ForumHolder_memberlist_ss.ORDERBYNAME', 'Order by Name') %>"><% _t('ForumHolder_memberlist_ss.MEMBERNAME', 'Member Name') %>:</a></th>
				<th><a href="{$URLSegment}/memberlist/?order=country" title="<% _t('ForumHolder_memberlist_ss.ORDERBYCOUNTRY', 'Order by Country') %>"><% _t('ForumHolder_memberlist_ss.COUNTRY', 'Country') %>:</a></th>
				<th><a href="{$URLSegment}/memberlist/?order=posts" title="<% _t('ForumHolder_memberlist_ss.ORDERBYPOSTS', 'Order by Posts') %>"><% _t('ForumHolder_memberlist_ss.FORUMPOSTS', 'Forum Posts') %>:</a></th>
				<th><a href="{$URLSegment}/memberlist/?order=joined" title="<% _t('ForumHolder_memberlist_ss.ORDERBYJOINED', 'Order by Joined') %>"><% _t('ForumHolder_memberlist_ss.JOINED', 'Joined') %>:</a></th>
			</tr>

			<% loop $Members %>
				<tr class="$EvenOdd">
					<td><a href="ForumMemberProfile/show/{$ID}" title="View Profile">$Nickname</a></td>
					<td><% if $CountryPublic %>$FullCountry<% else %>Private<% end_if %></td>
					<td class="numericField"><% if $NumPosts %>$NumPosts<% end_if %></td>
					<td><% loop $Created %>$DayOfMonth $ShortMonth $Year<% end_loop %></td>
				</tr>
			<% end_loop %>
		</table>

		<% if $Members.MoreThanOnePage %>
			<div id="ForumMembersPagination">
				<p>
					<% if $Members.NotFirstPage %>
						<a class="prev" href="$Members.PrevLink" title="View the previous page"><% _t('ForumHolder_memberlist_ss.PREV', 'Prev') %></a>
					<% end_if %>

					<span>
				    	<% loop $Members.PaginationSummary(4) %>
							<% if $CurrentBool %>
								$PageNum
							<% else %>
								<% if $PageNum %>
									<a href="$Link">$PageNum</a>
								<% else %>
									...
								<% end_if %>
							<% end_if %>
						<% end_loop %>
					</span>

					<% if $Members.NotLastPage %>
						<a class="next" href="$Members.NextLink" title="View the next page"><% _t('ForumHolder_memberlist_ss.NEXT', 'Next') %></a>
					<% end_if %>
				</p>
			</div>
		<% end_if %>
	</div>

<% include ForumFooter %>
