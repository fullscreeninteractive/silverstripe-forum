<div id="forum__post-{$ID}" class="forum__post">
    <div class="forum__user-info">
        <% with $Author %>
            <% if $Author.MemberProfileLink %>
                <a class="forum__author-link" href="$MemberProfileLink" title="<%t SinglePost_ss.GOTOPROFILE "Go to this User's Profile" %>">$Nickname</a><br />
            <% else %>
                <span class="forum__author-link">$Nickname</span><br />
            <% end_if %>

            <% if $Author.FormattedAvatar %>
                <img class="forum__avatar" src="{$Author.FormattedAvatar}" alt="Avatar" /><br />
            <% end_if %>

            <% if $ForumRank %><span class="forum__rank">$ForumRank</span><br /><% end_if %>
            <% if $NumPosts %>
                <span class="forum__post-count">$NumPosts
                <% if $NumPosts == 1 %>
                    <%t SinglePost_ss.POST "Post" %>
                <% else %>
                    <%t SinglePost_ss.POSTS "Posts" %>
                <% end_if %>
                </span>
            <% end_if %>
        <% end_with %>
    </div><!-- user-info. -->

    <div class="forum__user-content">
        <h4 class="forum__post-title"><a href="$Link">$Title</a></h4>
        <p class="forum__post-date">$Created.Nice

        <% if $Updated %>
            <strong class="forum__post-edited"><%t SinglePost_ss.LASTEDITED "Last edited:" %> $Updated.Long <%t SinglePost_ss.AT "" %> $Updated.Time</strong>
        <% end_if %>
        </p>

        <% if $EditLink || $DeleteLink %>
            <div class="forum__post-modifiers">
                {$EditLink}
                {$DeleteLink}
                {$MarkAsSpamLink}
                {$BanLink}
                {$GhostLink}
            </div>
        <% end_if %>

        <div class="forum__post-type">
            $ParsedContent
        </div>

        <% if $Thread.DisplaySignatures %>
            <% with $Author %>
                <% if $Signature %>
                    <div class="forum__signature">
                        <p>$Signature</p>
                    </div>
                <% end_if %>
            <% end_with %>
        <% end_if %>

        <% if $Attachments %>
            <div class="forum__attachments">
                <strong><%t SinglePost_ss.ATTACHED "Attached Files" %></strong>
                <ul class="forum__post-attachments">
                <% loop $Attachments %>
                    <li>
                        <a href="$Link"><img src="$Icon"></a>
                        <a href="$Link">$Name</a><br />
                        <% if $ClassName == "Image" %>$Width x $Height - <% end_if %>$Size
                    </li>
                <% end_loop %>
                </ul>
            </div>
        <% end_if %>
    </div>
</div><!-- forum__post. -->
