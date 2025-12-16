<% with $SearchResults %>
    <% if $MoreThanOnePage %>
        <ul class="forum__pagination">
        <% if $NotFirstPage %>
            <li class="forum__pagination-item forum__pagination-item--prev"><a href="$PrevLink">Prev</a></li>
        <% else %>
            <li class="forum__pagination-item forum__pagination-item--prev forum__pagination-item--disabled"><a href="#">Prev</a></li>
        <% end_if %>

        <% loop $PaginationSummary(4) %>
            <% if $CurrentBool %>
                <li class="forum__pagination-item forum__pagination-item--active"><a href="#">$PageNum</a></li>
            <% else %>
                <% if $Link %>
                    <li class="forum__pagination-item"><a href="$Link">$PageNum</a></li>
                <% else %>
                    <li class="forum__pagination-item"><a href="#">...</a></li>
                <% end_if %>
            <% end_if %>
        <% end_loop %>
        <% if $NotLastPage %>
            <li class="forum__pagination-item forum__pagination-item--next"><a href="$NextLink">Next</a></li>
        <% else %>
            <li class="forum__pagination-item forum__pagination-item--next forum__pagination-item--disabled"><a href="#">Next</a></li>
        <% end_if %>
        </ul>
    <% end_if %>
<% end_with %>
