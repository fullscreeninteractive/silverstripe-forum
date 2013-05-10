<% include ForumHeader %>
	
<table class="forum-table">	
<% if GlobalAnnouncements %>
	<tr class="head sticky first category special-header rowOne">
		<th colspan="4"><% _t('ANNOUNCEMENTS', 'Announcements') %></th>
	</tr>
	<tr class="head sticky first category-header special-header">
		<th class="odd"><% if Count = 1 %><% _t('FORUM','Forum') %><% else %><% _t('FORUMS', 'Forums') %><% end_if %></th>
		<th class="even center smallWidth"><% _t('THREADS','Threads') %></th>
		<th class="odd center smallWidth"><% _t('POSTS','Posts') %></th>
		<th class="even last lastPost smallWidth"><% _t('LASTPOST','Last Post') %></th>
	</tr>
	<tbody class="sticky">
		<% loop GlobalAnnouncements %>
			<% include ForumHolder_List EvenOdd=$EvenOdd %>
		<% end_loop %>
	</tbody>
<% end_if %>
	<% if ShowInCategories %>
		<% loop Forums %>	
				<tr class="head $FirstLast category rowOne table-header">
				<th colspan="4">$Title</th></tr>
				<tr class="head $FirstLast category-header">
					<th class="odd"><% if Count = 1 %><% _t('FORUM','Forum') %><% else %><% _t('FORUMS', 'Forums') %><% end_if %></th>
					<th class="even center smallWidth"><% _t('THREADS','Threads') %></th>
					<th class="odd center smallWidth"><% _t('POSTS','Posts') %></th>
					<th class="even last lastPost smallWidth"><% _t('LASTPOST','Last Post') %></th>
				</tr>
				<tbody>
				<% loop CategoryForums %>
					<% include ForumHolder_List EvenOdd=$EvenOdd %>
				<% end_loop %>
				</tbody>
		<% end_loop %>
	<% else %>
		<tr class="head $FirstLast category-header">
			<th><% _t('FORUM','Forum') %></th>
			<th><% _t('THREADS','Threads') %></th>
			<th><% _t('POSTS','Posts') %></th>
			<th><% _t('LASTPOST','Last Post') %></th>
		</tr>
		<tbody>
		<% loop Forums %>
			<% include ForumHolder_List EvenOdd=$EvenOdd %>
		<% end_loop %>	
		</tbody>
	<% end_if %>
</table>

<% include ForumFooter %>