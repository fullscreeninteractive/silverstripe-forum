<div class="forum__container">
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
    <p><a href="{$Link(starttopic)}" title="<%t Forum_ss.NEWTOPIC "Click here to start a new topic" %>"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="bevel"><path d="M12 5v14"></path><path d="M5 12h14"></svg></a></p>
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
        <thead>
            <tr class="forum__category">
                <th colspan="4"><%t Forum_ss.THREADS "Threads" %></th>
            </tr>

            <tr>
                <th class="forum__header--odd"><%t Forum_ss.TOPIC "Topic" %></th>
                <th class="forum__header--odd"><%t Forum_ss.POSTS "Posts" %></th>
                <th class="forum__header--even"><%t Forum_ss.LASTPOST "Last Post" %></th>
            </tr>
        </thead>

        <% if $Topics %>
            <% loop $Topics %>
                <% include TopicListing %>
            <% end_loop %>
        <% else %>
            <tr>
                <td colspan="3" class="forum__category"><%t Forum_ss.NOTOPICS "There are no topics in this forum, " %><a href="{$Link(starttopic)}" title="<%t Forum_ss.NEWTOPIC "New Topic" %>"><%t Forum_ss.NEWTOPICTEXT "click here to start a new topic" %>.</a></td>
            </tr>
        <% end_if %>
    </table>

    <% if $Topics.MoreThanOnePage %>
        <div class="forum__pagination">
            <% if $Topics.PrevLink %><a href="{$Topics.PrevLink}" title="<%t Forum_ss.PREVTITLE "View the previous page" %>"><%t Forum_ss.PREVLNK "Previous Page" %></a><% end_if %>

            <% loop $Topics.Pages %>
                <% if $CurrentBool %>
                    <strong>$PageNum</strong>
                <% else %>
                    <a href="$Link">$PageNum</a>
                <% end_if %>
            <% end_loop %>

            <% if $Topics.NextLink %><a href="{$Topics.NextLink}" title="<%t Forum_ss.NEXTTITLE "View the next page" %>"><%t Forum_ss.NEXTLNK "Next Page" %></a><% end_if %>
        </div>
    <% end_if %>

</div><!-- forum-features. -->

<% include ForumFooter %>
</div>
