<tr class="<% if $IsSticky || $IsGlobalSticky %>forum__row--sticky<% end_if %> <% if $IsGlobalSticky %>forum__row--global-sticky<% end_if %>">
    <td class="forum__topic">
        <a class="forum__topic-title" href="$Link">$Title</a>
        <p class="forum__topic-summary">
            <%t TopicListing_ss.BY "By" %>
            <% with $FirstPost %>
                <% with $Author %>
                    <% if $Link %>
                        <a href="{$Link}" title="<%t TopicListing_ss.CLICKTOUSER "Click here to view" %>{$Nickname}</a>
                    <% else %>
                        <span>{$Nickname}</span>
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
            <p class="forum__post-date">$Created.Ago</p>
            <p class="forum__post-author">
                <%t TopicListing_ss.BY "by" %>
                <% with $Author %>
                    <% if $Link %>
                        <a href="{$Link}" title="<%t TopicListing_ss.CLICKTOUSER "" %> {$Nickname.ATT}<%t TopicListing_ss.CLICKTOUSER2 "" %>">
                            {$Nickname}
                        </a>
                    <% else %>
                        <span>{$Nickname}</span>
                    <% end_if %>
                <% end_with %>
                <a href="$Link" title="<%t TopicListing_ss.GOTOFIRSTUNREAD "Go to the first unread post in the {title} topic." title=$Title.XML %>"><%t TopicListing_ss.READMORE "Read more" %></a>
            </p>
        <% end_with %>
    </td>
</tr>
