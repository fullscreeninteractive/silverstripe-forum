<?php

/**
 * Basic Forum Database Search. For a better search try the {@link ForumSphinxSearch}
 *
 * @package forum
 */

class ForumDatabaseSearch implements ForumSearchProvider {
	
	/**
	 * Get the results from the database
	 *
	 * @param Int $forumHolderID ForumHolderID to limit it too
	 * @param String $query need to make real escape for data safe
	 * @param String $order
	 * @param Int Offset
	 * @param Int Limit
	 *
	 * @return DataSet Results of matching posts or empty DataSet if no results.
	 */
	public function getResults($forumHolderID, $query, $order=null, $offset = 0, $limit = 10) {

		//sanitise the query string to avoid XSS (Using the ORM will also help avoid this too).
		$query = Convert::raw2sql(trim($query));

		//sanitise the order/sorting as it can be changed by the user in quesry string.
		$order = Convert::raw2sql(trim($order));

		//explode the query into the multiple terms to search, supply as an array to pass into the ORM filter.
		$terms = explode(' ', $query);
		//Add the original full query as one of the keywords.
		$terms[] = $query;

		//Get  posts (limitation is that it picks up the whole phase rather than a FULLTEXT SEARCH. 
		//We are aiming to keep this as simple as possible). More complex impementations acheived with Solr.
		//Rquires the post be moderated, then Checks for any match of Author name or Content partial match.
		//Author name checks the full query whereas Content checks each term for matches.
		$posts = Post::get()
			->filter(array(
				'Status' => 'Moderated', //posts my be moderated/visible.
				'Forum.ParentID' => $forumHolderID //posts must be from a particular forum section.
				))
			->filterAny(array(
				'Author.Nickname:PartialMatch:nocase' => $query,
				'Author.FirstName:PartialMatch:nocase' => $query,
				'Author.Surname:PartialMatch:nocase' => $query,
				'Content:PartialMatch:nocase' => $terms
			))
			->leftJoin('ForumThread', 'Post.ThreadID = ForumThread.ID');

		// Work out what sorting method
		switch($order) {
			case 'newest':
				$posts = $posts->sort('Created', 'DESC');
				break;
			case 'oldest':
				break;
			case 'title':
				$posts = $posts->sort(array('Thread.Title'=>'ASC'));
				break;
			default:
				$posts = $posts->sort(array(
					'Thread.Title'=>'ASC',
					'Created' => 'DESC'
				));
				break;
		}
		
		return $posts ? $posts: new DataList();
	}
	
	/**
	 * Callback when this Provider is loaded. For dealing with background processes
	 */
	public function load() {
		return true;
	}
}
