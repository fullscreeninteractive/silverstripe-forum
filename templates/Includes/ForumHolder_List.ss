<tr>
    <td>
        <a class="forum__topic-title" href="$Link">$Title</a>
        <% if $Content || $Moderators %>
            <div class="forum__summary">
                <p>$Content.LimitCharacters(80)</p>

                <% if $Moderators %>
                <p>Moderators: <% loop $Moderators %>
                <a href="$Link">$Nickname</a>
                <% if not $Last %>, <% end_if %><% end_loop %></p>
            <% end_if %>
            </div>
        <% end_if %>
    </td>

    <td class="forum__count">
        $NumTopics
    </td>

    <td class="forum__count">
        $NumPosts
    </td>

    <td class="forum__last-post">
        <% if $LatestPost %>
            <% with $LatestPost %>
                <a href="{$Link}" class="forum__topic-title">$Title</a>
                <p class="forum__post-date">$Created.Ago</p>

                <% with $Author %>
                    <p>by <% if $Link %><a href="$Link"><% if $Nickname %>$Nickname<% else %>Anon<% end_if %></a><% else %><span>Anon</span><% end_if %></p>
                <% end_with %>
            <% end_with %>
        <% end_if %>
    </td>
</tr>
