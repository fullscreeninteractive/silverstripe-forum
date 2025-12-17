<% include ForumHeader %>


<table class="forum__topics">
    <thead>
    <tr class="forum__category">
        <td class="forum__pagination">
            <span><strong><%t Forum_show_ss.PAGE "Page:" %></strong></span>
            <% loop $Posts.Pages %>
                <% if $CurrentBool %>
                    <span><strong>$PageNum</strong></span>
                <% else %>
                    <a href="$Link">$PageNum</a>
                <% end_if %>
                <% if not $Last %>,<% end_if %>
            <% end_loop %>
        </td>
        <td class="forum__goto-button--end">
            <a href="#Footer" title="<%t Forum_show_ss.CLICKGOTOEND "Click here to go the end of this post" %>"><%t Forum_show_ss.GOTOEND "Go to End" %></a>
        </td>
        <td class="forum__reply-button">
            <% if $ForumThread.canCreate %>
                <a href="$ReplyLink" title="<%t Forum_show_ss.CLICKREPLY "Click here to reply to this topic" %>"><%t Forum_show_ss.REPLY "Reply" %></a>
            <% end_if %>

            <% if $CurrentMember %>
                <% include ForumThreadSubscribe %>
            <% end_if %>
        </td>
    </tr>
    <tr class="forum__author">
        <td class="forum__name">
            <span><%t Forum_show_ss.AUTHOR "Author" %></span>
        </td>
        <td class="forum__topic">
            <span><strong><%t Forum_show_ss.TOPIC "Topic:" %></strong> $ForumThread.Title</span>
        </td>
        <td class="forum__views">
            <span><strong>$ForumThread.NumViews <%t Forum_show_ss.VIEWS "Views" %></strong></span>
        </td>
    </tr>
    </thead>
</table>

<% loop $Posts %>
    <% include SinglePost %>
<% end_loop %>

<table class="forum__topics">
    <tr class="forum__author">
        <td class="forum__author">&nbsp;</td>
        <td class="forum__topic">&nbsp;</td>
        <td class="forum__views">
            <span><strong>$ForumThread.NumViews <%t Forum_show_ss.VIEWS "Views" %></strong></span>
        </td>
    </tr>
    <tr class="forum__category">
        <td class="forum__pagination">
            <% if $Posts.MoreThanOnePage %>
                <% if $Posts.NotFirstPage %>
                    <a class="forum__pagination-link--prev" href="$Posts.PrevLink" title="<%t Forum_show_ss.PREVTITLE "View the previous page" %>"><%t Forum_show_ss.PREVLINK "Prev" %></a>
                <% end_if %>
            <% end_if %>
        </td>
        <td class="forum__goto-button--top">
            <a href="#Header" title="<%t Forum_show_ss.CLICKGOTOTOP "Click here to go the top of this post" %>"><%t Forum_show_ss.GOTOTOP "Go to Top" %></a>
        </td>
        <td class="forum__reply-button">
            <% if $ForumThread.canCreate %>
                <a href="$ReplyLink" title="<%t Forum_show_ss.CLICKREPLY "Click to Reply" %>"><%t Forum_show_ss.REPLY "Reply" %></a>
            <% end_if %>

            <% if $Posts.MoreThanOnePage %>
                <% if Posts.NotLastPage %>
                    <a class="forum__pagination-link--next" href="$Posts.NextLink" title="<%t Forum_show_ss.NEXTTITLE "View the next page" %>"><%t Forum_show_ss.NEXTLINK "Next" %> &gt;</a>
                <% end_if %>
            <% end_if %>
        </td>
    </tr>
</table>

<% if $AdminFormFeatures %>
<div class="forum__admin-features">
    <h3 class="forum__admin-features-title">Forum Admin Features</h3>
    $AdminFormFeatures
</div>
<% end_if %>

<% include ForumFooter %>
