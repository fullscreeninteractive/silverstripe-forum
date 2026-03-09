<p><%t ForumMember_NotifyModerator_ss.HI "Hi {name}," name=$Author.Nickname %></p>

<% if $NewThread %>
    <p><%t ForumMember_NotifyModerator_ss.MODERATORNEWTHREADMESSAGE "New forum thread has been started" %>.</p>
<% else %>
    <p><%t ForumMember_NotifyModerator_ss.MODERATORNEWPOSTMESSAGE "A forum post has been added or edited" %>.</p>
<% end_if %>

<h3>Content</h3>
<blockquote>
    <p>
        <strong>$Post.Title</strong><br/>
        <% if $Author %> <%t ForumMember_NotifyModerator_ss.BY "by" %> <em>$Author.Nickname</em><% end_if %>
        <%t ForumMember_NotifyModerator_ss.DATEON "on" %> {$Post.LastEdited.Nice}.
    </p>
    <% with $Post %>
        $ParsedContent
    <% end_with %>
</blockquote>

<h3>Actions</h3>
<ul>
    <li><a href="$Post.Link"><%t ForumMember_NotifyModerator_ss.MODERATORMODERATE "Moderate the thread" %></a></li>
</ul>

<p>
    <%t ForumMember_NotifyModerator_ss.MODERATORSIGNOFF "Yours truly,\nThe Forum Robot." %>
</p>

<p>
    <%t ForumMember_NotifyModerator_ss.MODERATORNOTE "NOTE: This is an automated email sent to all moderators of this forum." %>
</p>

