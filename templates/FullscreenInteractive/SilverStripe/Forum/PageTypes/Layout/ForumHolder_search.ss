<% include ForumHeader %>
    <% if $SearchResults %>
        <div id="forum__search" class="forum__search">
            <p>$Abstract</p>
            <table class="forum__topics">
                <thead>
                <tr>
                    <th><%t ForumHolder_search_ss.THREAD "Thread" %></th>
                    <th><%t ForumHolder_search_ss.ORDER "Order:" %>
                        <a href="{$URLSegment}/search/?Search={$Query.ATT}&amp;order=newest" <% if $Order == 'newest' %>class="forum__pagination-link--current"<% end_if %> title="<%t ForumHolder_search_ss.ORDERBYNEWEST "Order by Newest. Most recent posts first" %>"><%t ForumHolder_search_ss.NEWEST "Newest" %></a> |
                            <a href="{$URLSegment}/search/?Search={$Query.ATT}&amp;order=oldest" <% if $Order == 'oldest' %>class="forum__pagination-link--current"<% end_if %> title="<%t ForumHolder_search_ss.ORDERBYOLDEST "Order by Oldest. Oldest posts First" %>"><%t ForumHolder_search_ss.OLDEST "Oldest" %></a> |
                            <a href="{$URLSegment}/search/?Search={$Query.ATT}&amp;order=title" <% if $Order == 'title' %>class="forum__pagination-link--current"<% end_if %>title="<%t ForumHolder_search_ss.ORDERBYTITLE "Order by Title" %>"><%t ForumHolder_search_ss.TITLE "Title" %></a>
                        </th>
                    <th>
                        <a href="$RSSLink"><%t ForumHolder_search_ss.RSSFEED "RSS Feed" %></a>
                    </th>
                </tr>
                </thead>

                <tbody>
                <% loop $SearchResults.setPageLength(10) %>
                <tr class="$EvenOdd">
                    <td class="forum__category" colspan="3">
                        <% loop $Thread %>
                            <a class="forum__topic-title" href="$Link" title="<%t Forum.ss.GOTOTHISTOPIC "Go to the {title} topic" title=$Title %>">$Title</a>
                        <% end_loop %>

                        <p>$Content.ContextSummary.RAW <span class="forum__date-info">$Created.Ago</span></p>
                    </td>
                </tr>
                <% end_loop %>
                </tbody>
                <tfoot>
                <% if $SearchResults.MoreThanOnePage %>
                <tr class="forum__category">
                    <td class="forum__pagination" colspan="3">
                        <% include ForumPagination %>
                    </td>
                </tr>
                <% end_if %>
                </tfoot>
            </table>
        </div>
    <% else %>
        <div id="forum__search" class="forum__search">
            <table class="forum__topics">
                <tr><td><%t ForumHolder_search_ss.NORESULTS "There are no results for those word(s)" %></td></tr>
            </table>
        </div>
    <% end_if %>

<% include ForumFooter %>
