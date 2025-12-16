<% include ForumHeader %>
    <div class="forum__features">

        <table id="forum__members-list" class="forum__members-list">
            <tr class="forum__head">
                <th><a href="{$Link(memberlist/?order=name)}" title="<%t ForumHolder_memberlist_ss.ORDERBYNAME "Order by Name" %>"><%t ForumHolder_memberlist_ss.MEMBERNAME "Member Name" %>:</a></th>
                <th><a href="{$Link(memberlist/?order=country)}" title="<%t ForumHolder_memberlist_ss.ORDERBYCOUNTRY "Order by Country" %>"><%t ForumHolder_memberlist_ss.COUNTRY "Country" %>:</a></th>
                <th><a href="{$Link(memberlist/?order=posts)}" title="<%t ForumHolder_memberlist_ss.ORDERBYPOSTS "Order by Posts" %>"><%t ForumHolder_memberlist_ss.FORUMPOSTS "Forum Posts" %>:</a></th>
                <th><a href="{$Link(memberlist/?order=joined)}" title="<%t ForumHolder_memberlist_ss.ORDERBYJOINED "Order by Joined" %>"><%t ForumHolder_memberlist_ss.JOINED "Joined" %>:</a></th>
            </tr>

            <% loop $Members %>
                <tr class="$EvenOdd">
                    <td><a href="{$MemberProfileLink}" title="View Profile">$Nickname</a></td>
                    <td><% if $CountryPublic %>$FullCountry<% else %><%t ForumHolder_memberlist_ss.PRIVATE "Private" %><% end_if %></td>
                    <td class="forum__numeric-field"><% if $NumPosts %>$NumPosts<% end_if %></td>
                    <td><% loop $Created %><%t ForumHolder_memberlist_ss.DAYOFMONTH "Day of Month" %> $DayOfMonth <%t ForumHolder_memberlist_ss.SHORTMONTH "Short Month" %> $ShortMonth <%t ForumHolder_memberlist_ss.YEAR "Year" %> $Year<% end_loop %></td>
                </tr>
            <% end_loop %>
        </table>

        <% if $Members.MoreThanOnePage %>
            <div id="forum__members-pagination" class="forum__members-pagination">
                <p>
                    <% if $Members.NotFirstPage %>
                        <a class="forum__pagination-link--prev" href="$Members.PrevLink" title="View the previous page"><%t ForumHolder_memberlist_ss.PREV "Prev" %></a>
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
                        <a class="forum__pagination-link--next" href="$Members.NextLink" title="View the next page"><%t ForumHolder_memberlist_ss.NEXT "Next" %></a>
                    <% end_if %>
                </p>
            </div>
        <% end_if %>
    </div>

<% include ForumFooter %>
