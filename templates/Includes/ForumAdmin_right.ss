<div class="title">
	<div style="background-image : url(cms/images/panels/EditPage.png)">
		<% _t('ForumAdmin_right_ss.EDITPAGE','Edit Page') %>
	</div>
</div>
<% include Editor_toolbar %>

<% if $EditForm %>
	$EditForm
<% else %>
	<form id="Form_EditForm" action="admin?executeForm=EditForm" method="post" enctype="multipart/form-data">
		<h2>$ApplicationName</h2>
		<p><% sprintf(_t('ForumAdmin_right_ss.WELCOME',"Welcome to %s! Please choose click on one of the entries on the left pane."),$ApplicationName) %></p>
	</form>
<% end_if %>


<p id="statusMessage" style="visibility:hidden"></p>
