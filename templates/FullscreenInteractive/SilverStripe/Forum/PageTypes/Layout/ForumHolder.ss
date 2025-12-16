<% include ForumHeader %>

<table class="forum__topics">
    <% if $GlobalAnnouncements %>
        <tr class="forum__category">
            <td colspan="4"><%t ForumHolder_ss.ANNOUNCEMENTS "Announcements" %></td>
        </tr>
        <% loop $GlobalAnnouncements %>
            <% include ForumHolder_List %>
        <% end_loop %>
    <% end_if %>

    <% if $ShowInCategories %>
        <% loop Forums %>
            <tr class="forum__category"><td colspan="4">$Title</td></tr>
            <tr class="forum__category">
                <th><% if Count = 1 %><%t ForumHolder_ss.FORUM "Forum" %><% else %><%t ForumHolder_ss.FORUMS "Forums" %><% end_if %></th>
                <th><%t ForumHolder_ss.THREADS "Threads" %></th>
                <th><%t ForumHolder_ss.POSTS "Posts" %></th>
                <th><%t ForumHolder_ss.LASTPOST "Last Post" %></th>
            </tr>
            <% loop $CategoryForums %>
                <% include ForumHolder_List %>
            <% end_loop %>
        <% end_loop %>
    <% else %>
        <tr class="forum__category">
            <td><%t ForumHolder_ss.FORUM "Forum" %></td>
            <td><%t ForumHolder_ss.THREADS "Threads" %></td>
            <td><%t ForumHolder_ss.POSTS "Posts" %></td>
            <td><%t ForumHolder_ss.LASTPOST "Last Post" %></td>
        </tr>
        <% loop $Forums %>
            <% include ForumHolder_List %>
        <% end_loop %>
    <% end_if %>
</table>

<% include ForumFooter %>
