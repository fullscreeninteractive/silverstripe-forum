(function($){

	/* Show or hide PosterGroups based on initial value */
	$('.ForumCanPostTypeSelector').entwine({
		onadd: function(){
			var state = this.find('[name=CanPostType]:checked').val();
			$('#PosterGroups').css({display: state == "OnlyTheseUsers" ? "block" : "none"});
			this._super();
		}
	});

	/* Any value of the PostTypeSelector hides the PosterGroups element */
	$('.ForumCanPostTypeSelector input[type=radio]').entwine({
		onclick: function(){
			$('#PosterGroups').css({display: 'none'});
		}
	});

	/* Except OnlyTheseUsers which shows it */
		$('.ForumCanPostTypeSelector input[type=radio][value=OnlyTheseUsers]').entwine({
		onclick: function(){
			$('#PosterGroups').css({display: 'block'});
		}
	});
})(jQuery);
