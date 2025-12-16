<div class="forum__header">
    <div class="forum__header-forms">
        <span class="forum__search-dropdown-icon"></span>

        <div class="forum__search-bar">
            <form class="forum__search" action="$Link('search')" method="get">
                <fieldset>
                    <label for="forum__search-text"><%t ForumHeader_ss.SEARCHBUTTON "Search" %></label>
                    <input id="forum__search-text" class="forum__input forum__input--active" type="text" name="Search" value="$Query.ATT" />
                    <input class="forum__submit forum__submit--action" type="submit" value="<%t ForumHeader_ss.SEARCHBUTTON "Search" %>"/>
                </fieldset>
            </form>
        </div>

        <form class="forum__jump" action="#">
            <label for="forum__jump-select"><%t ForumHeader_ss.JUMPTO "Jump to:" %></label>
            <select id="forum__jump-select" class="forum__select" onchange="if(this.value) location.href = this.value">
                <option value=""><%t ForumHeader_ss.JUMPTO "Jump to:" %></option>
                <!-- option value=""><%t ForumHeader_ss.SELECT "Select" %></option -->
                <% if $ShowInCategories %>
                    <% loop $Forums %>
                        <optgroup label="$Title">
                            <% loop $CategoryForums %>
                                <% if $can('view') %>
                                    <option value="$Link">$Title</option>
                                <% end_if %>
                            <% end_loop %>
                        </optgroup>
                    <% end_loop %>
                <% else %>
                    <% loop $Forums %>
                        <% if $can('view') %>
                            <option value="$Link">$Title</option>
                        <% end_if %>
                    <% end_loop %>
                <% end_if %>
            </select>
        </form>

        <% if $NumPosts %>
            <p class="forum__stats">
                $NumPosts
                <strong><%t ForumHeader_ss.POSTS "Posts" %></strong>
                <%t ForumHeader_ss.IN "in" %> $NumTopics <strong><%t ForumHeader_ss.TOPICS "Topics" %></strong>
                <%t ForumHeader_ss.BY "by" %> $NumAuthors <strong><%t ForumHeader_ss.MEMBERS "members" %></strong>
            </p>
        <% end_if %>
    </div><!-- forum-header-forms. -->


    <h1 class="forum__heading"><a name='Header'>$HolderSubtitle</a></h1>
    <p class="forum__breadcrumbs">$Breadcrumbs</p>
    <p class="forum__abstract">$ForumHolder.HolderAbstract</p>

    <% if $Moderators %>
        <p>
            Moderators:
            <% loop $Moderators %>
                <a href="$Link">$Nickname</a><% if not $Last %>, <% end_if %>
            <% end_loop %>
        </p>
    <% end_if %>

</div><!-- forum-header. -->
