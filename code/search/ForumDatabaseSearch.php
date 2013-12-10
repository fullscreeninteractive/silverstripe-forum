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
	 * @return DataObjectSet
	 */
	public function getResults($forumHolderID, $query, $order, $offset = 0, $limit = 10) {
		// we need to do escape for the $query string to avoid sql injection
		// since $query string has been used more than once below in searching out potential authors and posts in different ways,
		// we'd better make the escaping just before its defferent variations putting into a query.
		
		
		// Search for authors
		$SQL_queryParts = Convert::raw2sql(preg_split('/ +/', trim($query)));
		foreach($SQL_queryParts as $SQL_queryPart ) { 
			$SQL_clauses[] = "FirstName LIKE '%$SQL_queryPart%' OR Surname LIKE '%$SQL_queryPart' OR Nickname LIKE '%$SQL_queryPart'";
		}

		$potentialAuthors = DataObject::get('Member', implode(" OR ", $SQL_clauses), '"ID" ASC');
		$SQL_authorClause = '';
		$SQL_potentialAuthorIDs = array();
		
		if($potentialAuthors->exists()) {
			foreach($potentialAuthors as $potentialAuthor) {
				$SQL_potentialAuthorIDs[] = $potentialAuthor->ID;
			}
			$SQL_authorList = implode(", ", $SQL_potentialAuthorIDs);
			$SQL_authorClause = "OR Post.AuthorID IN ($SQL_authorList)";
		}
		
		// Work out what sorting method
		switch($order) {
			case 'date':
				$sort = "Post.Created DESC";
				break;
			case 'title':
				$sort = "ForumThread.Title ASC";
				break;
			default:
				$sort = "RelevancyScore DESC";
				break;
		}
		/*
		$baseSelect = "SELECT \"Post\".\"ID\", \"Post\".\"Created\", \"Post\".\"LastEdited\", \"Post\".\"ClassName\", \"ForumThread\".\"Title\", \"Post\".\"Content\", \"Post\".\"ThreadID\", \"Post\".\"AuthorID\", \"ForumThread\".\"ForumID\"";
		$baseFrom = "FROM \"Post\"
			JOIN \"ForumThread\" ON \"Post\".\"ThreadID\" = \"ForumThread\".\"ID\"
			JOIN \"" . ForumHolder::baseForumTable() . "\" \"ForumPage\" ON \"ForumThread\".\"ForumID\"=\"ForumPage\".\"ID\"";
		*/

		$baseSelect = "SELECT Post.ID, Post.Created, Post.LastEdited, Post.ClassName, ForumThread.Title, Post.Content, Post.ThreadID, Post.AuthorID, ForumThread.ForumID";
		$baseFrom = "FROM Post JOIN ForumThread ON Post.ThreadID = ForumThread.ID JOIN {ForumHolder::baseForumTable()} ForumPage ON ForumThread.ForumID=ForumPage.ID";
		
		$SQL_query = Convert::raw2sql(trim($query));
		// each database engine does its own thing 
		switch(DB::getConn()->getDatabaseServer()) {
			case 'postgresql':
				$queryString = "
					$baseSelect
					$baseFrom	
					, to_tsquery('english', '$SQL_query') AS q";
			
				$limitString = "LIMIT $limit OFFSET $offset;";
				break;
				
			case 'mssql':
				$queryString = "
					$baseSelect
					$baseFrom
					WHERE
						(CONTAINS(\"ForumThread\".\"Title\", '$SQL_query') OR CONTAINS(\"Post\".\"Content\", '$SQL_query')
						AND \"ForumPage\".\"ParentID\"='{$forumHolderID}'";
						
				// @todo fix this to use MSSQL's version of limit/offsetB
				$limitString = false;
				break;
				
			default:
				$queryString = "
					$baseSelect,
					MATCH (\"Post\".\"Content\") AGAINST ('$SQL_query') AS RelevancyScore
					$baseFrom
					WHERE
						MATCH (\"ForumThread\".\"Title\", \"Post\".\"Content\") AGAINST ('$SQL_query' IN BOOLEAN MODE)
						$SQL_authorClause
						AND ForumPage.ParentID='{$forumHolderID}'
					ORDER BY $sort";

				$limitString = " LIMIT $offset, $limit;";
		}

		// Find out how many posts that match with no limit
		$allPosts = DB::query($queryString);
		
		// Get the 10 posts from the starting record
		if($limitString) {
			$query = DB::query("
				$queryString
				$limitString
			");
		}
		else {
			$query = $allPosts;
		}
		
		$allPostsCount = $allPosts ? $allPosts->numRecords() : 0;
		
		$baseClass = new Post();
		$postsSet = $baseClass->buildDataObjectSet($query);
		
		if($postsSet) {
			$postsSet->setPageLimits($offset, $limit, $allPostsCount);
		}
		
		return $postsSet ? $postsSet: new DataObjectSet();
	}
	
	/**
	 * Callback when this Provider is loaded. For dealing with background processes
	 */
	public function load() {
		return true;
	}
}
