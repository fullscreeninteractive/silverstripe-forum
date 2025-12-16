<% include ForumHeader %>

<% if ForumAdminMsg %>
    <p class="forum__message forum__message--admin">$ForumAdminMsg</p>
<% end_if %>

<% if CurrentMember.isSuspended %>
    <p class="forum__message forum__message--suspended">
        $CurrentMember.ForumSuspensionMessage
    </p>
<% end_if %>

<% if ForumPosters = NoOne %>
    <p class="forum__message forum__message--error"><%t Forum_ss.READONLYFORUM "This Forum is read only. You cannot post replies or start new threads" %></p>
<% end_if %>
<% if canPost %>
    <p><a href="{$Link}starttopic" title="<%t Forum_ss.NEWTOPIC "Click here to start a new topic" %>"><img src="forum/images/forum_startTopic.gif" alt="<%t Forum_ss.NEWTOPICIMAGE "Start new topic" %>" /></a></p>
<% end_if %>

<div class="forum__features">
    <% if $getStickyTopics(0) %>
        <table class="forum__sticky-topics" summary="List of sticky topics in this forum">
            <tr class="forum__category">
                <td colspan="3"><%t Forum_ss.ANNOUNCEMENTS "Announcements" %></td>
            </tr>
            <% loop $getStickyTopics(0) %>
                <% include TopicListing %>
            <% end_loop %>
        </table>
    <% end_if %>

    <table class="forum__topics" summary="List of topics in this forum">
        <tr class="forum__category">
            <td colspan="4"><%t Forum_ss.THREADS "Threads" %></td>
        </tr>
        <tr>
            <th class="forum__header--odd"><%t Forum_ss.TOPIC "Topic" %></th>
            <th class="forum__header--odd"><%t Forum_ss.POSTS "Posts" %></th>
            <th class="forum__header--even"><%t Forum_ss.LASTPOST "Last Post" %></th>
        </tr>
        <% if $Topics %>
            <% loop $Topics %>
                <% include TopicListing %>
            <% end_loop %>
        <% else %>
            <tr>
                <td colspan="3" class="forum__category"><%t Forum_ss.NOTOPICS "There are no topics in this forum, " %><a href="{$Link}starttopic" title="<%t Forum_ss.NEWTOPIC "" %>"><%t Forum_ss.NEWTOPICTEXT "click here to start a new topic" %>.</a></td>
            </tr>
        <% end_if %>
    </table>

    <% if $Topics.MoreThanOnePage %>
        <p>
            <% if $Topics.PrevLink %><a style="float: left" href="$Topics.PrevLink">	&lt; <%t Forum_ss.PREVLNK "Previous Page" %></a><% end_if %>
            <% if $Topics.NextLink %><a style="float: right" href="$Topics.NextLink"><%t Forum_ss.NEXTLNK "Next Page" %> &gt;</a><% end_if %>

            <% loop $Topics.Pages %>
                <% if $CurrentBool %>
                    <strong>$PageNum</strong>
                <% else %>
                    <a href="$Link">$PageNum</a>
                <% end_if %>
            <% end_loop %>
        </p>
    <% end_if %>

</div><!-- forum-features. -->

<% include ForumFooter %>
