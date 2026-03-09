<% include ForumHeader %>

$PostMessageForm

<div id="forum__previous-posts" class="forum__previous-posts">
    <h2>Replying to: $Topic.Title</h2>

    <ul id="forum__posts" class="forum__posts">
        <% loop $Posts('DESC').Limit(3) %>
            <li class="$EvenOdd">
                <% include SinglePost %>
            </li>
        <% end_loop %>
    </ul>
        <p>
        <a href="{$Thread.Link}" class="btn btn-secondary" target="_blank">View topic</a>
    </p>
</div>

<% include ForumFooter %>
