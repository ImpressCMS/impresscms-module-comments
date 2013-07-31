<?php
/**
 * Comments search infomation
 *
 * This file holds the search function for the comments module
 *
 * @copyright	Copyright Madfish (Simon Wilkinson) 2013.
 * @license		http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License (GPL)
 * @since		1.0
 * @author		Madfish (Simon Wilkinson) <simon@isengard.biz>
 * @package		events
 * @version		$Id$
 */

defined("ICMS_ROOT_PATH") or die("ICMS root path not defined");

function comments_search($queryarray, $andor, $limit, $offset = 0, $userid = 0)
{
	// Initialise
	global $icmsConfigSearch;
	$commentsArray = $ret = array();
	$commentsCount = $number_to_process = $comments_left = '';
	$criteria = new icms_db_criteria_Compo();
	$comment_handler = icms::handler('icms_data_comment');
	
	// Get search results
	if ($userid != 0)
	{
		$criteria->add(new icms_db_criteria_Item('com_uid', $userid));
	}
	if ($queryarray)
	{
		$criteriaKeywords = new icms_db_criteria_Compo();
		for ($i = 0; $i < count($queryarray); $i++) {
			$criteriaKeyword = new icms_db_criteria_Compo();
			$criteriaKeyword->add(new icms_db_criteria_Item('com_title', '%' . $queryarray[$i] . '%',
				'LIKE'), 'OR');
			$criteriaKeyword->add(new icms_db_criteria_Item('com_text', '%' . $queryarray[$i]
				. '%', 'LIKE'), 'OR');
			$criteriaKeywords->add($criteriaKeyword, $andor);
			unset ($criteriaKeyword);
		}
		$criteria->add($criteriaKeywords);
	}
	// Only return comments marked as active (pending = 1, active = 2, hidden = 3)
	$criteria->add(new icms_db_criteria_Item('com_status', 2));
		
	// Count the number of search results WITHOUT actually retrieving the objects
	$commentsCount = $comment_handler->getCount($criteria);
	
	// Retrieve the subset of results that are actually required.
	$criteria->setStart($offset);
	if (!$limit) {
		global $icmsConfigSearch;
		$limit = $icmsConfigSearch['search_per_page'];
	}
	$criteria->setLimit($limit);
	$criteria->setSort('com_created');
	$criteria->setOrder('DESC'); // Change to ASC to reverse the comment sort order
	$commentsArray = $comment_handler->getObjects($criteria, FALSE, TRUE);
	
	// Pad the results array out to the counted length to preserve 'hits' and pagination controls.
	// This approach is not ideal, but it greatly reduces the load for queries with large result sets
	$commentsArray = array_pad($commentsArray, $commentsCount, 1);
	echo count($commentsArray);
	
	// The number of records actually containing event objects is <= $limit, the rest are padding
	$comments_left = ($commentsCount - ($offset + $icmsConfigSearch['search_per_page']));
	if ($comments_left < 0) {
		$number_to_process = $icmsConfigSearch['search_per_page'] + $comments_left; // $comments_left is negative
	} else {
		$number_to_process = $icmsConfigSearch['search_per_page'];
	}
	
	// Process the actual comments (not the padding)
	for ($i = 0; $i < $number_to_process; $i++) {
		if (is_object($commentsArray[$i])) { // Required to prevent crashing on profile view
			$item['image'] = "images/comments.png";
			$item['link'] = ICMS_URL . '/modules/system/admin.php?fct=comments&amp;op=jump'
					. '&amp;com_id=' . $commentsArray[$i]->getVar('com_id');		
			$item['title'] = $commentsArray[$i]->getVar("com_title");
			$item['time'] = $commentsArray[$i]->getVar("com_created");
			$item['uid'] = $commentsArray[$i]->getVar("com_uid");
			$ret[] = $item;
			unset($item);
		}
	}
	
	// Restore the padding (required for 'hits' information and pagination controls). The offset
	// must be padded to the left of the results, and the remainder to the right or else the search
	// pagination controls will display the wrong results (which will all be empty).
	// Left padding = -($limit + $offset)
	$ret = array_pad($ret, -($offset + $number_to_process), 1);

	// Right padding = $commentsCount
	$ret = array_pad($ret, $commentsCount, 1);
	
	return $ret;
}