<% include ForumHeader %>

$PostMessageForm

<div id="forum__previous-posts" class="forum__previous-posts">
    <ul id="forum__posts" class="forum__posts">
        <% loop $Posts('DESC') %>
            <li class="$EvenOdd">
                <% include SinglePost %>
            </li>
        <% end_loop %>
    </ul>
</div>

<% include ForumFooter %>
