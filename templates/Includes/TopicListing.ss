<tr class="<% if $IsSticky || $IsGlobalSticky %>forum__row--sticky<% end_if %> <% if $IsGlobalSticky %>forum__row--global-sticky<% end_if %>">
    <td class="forum__topic">
        <a class="forum__topic-title" href="$Link">$Title</a>
        <p class="forum__topic-summary">
            <%t TopicListing_ss.BY "By" %>
            <% with $FirstPost %>
                <% with $Author %>
                    <% if $Link %>
                        <a href="$Link" title="<%t TopicListing_ss.CLICKTOUSER "Click here to view" %>"><% if $Nickname %>$Nickname<% else %>Anon<% end_if %></a>
                    <% else %>
                        <span>Anon</span>
                    <% end_if %>
                <% end_with %>
                <%t TopicListing_ss.ON "on" %> $Created.Long
            <% end_with %>
        </p>
    </td>
    <td class="forum__count">
        $NumPosts
    </td>
    <td class="forum__last-post">
        <% with $LatestPost %>
            <p>$Created.Ago</p>
            <p>
                <%t TopicListing_ss.BY "by" %>
                <% with $Author %>
                    <% if $Link %>
                        <a href="$Link" title="<%t TopicListing_ss.CLICKTOUSER "" %> <% if $Nickname %>$Nickname.XML<% else %>Anon<% end_if %><%t TopicListing_ss.CLICKTOUSER2 "" %>">
                            <% if $Nickname %>$Nickname<% else %>Anon<% end_if %>
                        </a>
                    <% else %>
                        <span>Anon</span>
                    <% end_if %>
                <% end_with %>
                <a href="$Link" title="<%t TopicListing_ss.GOTOFIRSTUNREAD "Go to the first unread post in the {title} topic." title=$Title.XML %>"><img src="forum/images/right.png" alt="" /></a>
            </p>
        <% end_with %>
    </td>
</tr>
