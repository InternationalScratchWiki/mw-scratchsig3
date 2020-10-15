<?php
const SCRATCHSIG_USERNAME_NOT_FOUND = '@SCRATCHSIG_USERNAME_NOT_FOUND';
const SCRATCHSIG_API_FAILURE = '@SCRATCHSIG_API_FAILURE';

/**
 * Convert a MediaWiki username to a Scratch username
 */
function wikiUsernameToScratchUsername(string $username) : string {
	return str_replace(' ', '_', $username);
}

/**
 * Get the Scratch ID corresponding to a given username directly from the API (note: NOT cached)
 * @return Returns the ID if successful, will return '@SCRATCHSIG_USERNAME_NOT_FOUND' if no username corresponds to the given ID, and '@SCRATCHSIG_API_FAILURE' if the API failed to load
 */
function scratchUsernameToIdFromApi(string $username) {
	$scratchApiResult = @file_get_contents('https://api.scratch.mit.edu/users/' . rawurlencode(wikiUsernameToScratchUsername($username)));
	
	//handle the various potential response failures
	if (!isset($http_response_header)) {
		return SCRATCHSIG_API_FAILURE;
	}
				
	if (strstr($http_response_header[0], '404 Not Found')) {
		return SCRATCHSIG_USERNAME_NOT_FOUND;
	}
			
	return json_decode($scratchApiResult, $assoc=true)['id'];
}
	
/**
 * Get the Scratch ID corresponding to a given username, with the results cached
 * @return Returns the ID if successful, will return '@SCRATCHSIG_USERNAME_NOT_FOUND' if no username corresponds to the given ID, and '@SCRATCHSIG_API_FAILURE' if the API failed to load
 */
function scratchUsernameToId(string $username) : string {
	global $wgScratchSigUserIdsByUsername;
	
	if (!isset($wgScratchSigUserIdsByUsername)) {
		$wgScratchSigUserIdsByUsername = [];
	}
	
	//if the user ID is not cached, then get it from the API and cache it
	if (!isset($wgScratchSigUserIdsByUsername[$username])) {
		$userId = scratchUsernameToIdFromApi($username);
		
		//for an API failure, do NOT cache the result since the failure is probably transient
		if ($userId == SCRATCHSIG_API_FAILURE) {
			return SCRATCHSIG_API_FAILURE;
		}
		
		//cache the result
		$wgScratchSigUserIdsByUsername[$username] = $userId;
	}
	
	return $wgScratchSigUserIdsByUsername[$username];
}

/**
 * Determine if a given Scratch user ID result is a real ID or if it's a failure flag
 */
function isSuccessfulScratchId(string $id) : string {
	return !in_array($id, [SCRATCHSIG_API_FAILURE, SCRATCHSIG_USERNAME_NOT_FOUND]);
}

/**
 * Get the avatar URL associated with a given username, or return a failure flag if there is no avatar associated with that username
 */
function scratchAvatarUrl(string $username) : string {
	$userId = scratchUsernameToId($username);
	
	return isSuccessfulScratchId($userId) ? 'http://cdn.scratch.mit.edu/get_image/user/' . $userId . '_18x18.png' : $userId;
}

class ScratchSig {
	public static function onParserFirstCallInit (Parser $parser) : void {
		$parser->setHook('scratchsig', array ("ScratchSig", "onScratchSig"));
	}

	public static function onScratchSig (string $username, array $args, Parser $parser, PPFrame $frame) : string {
		$avatarUrl = scratchAvatarUrl($username);
		
		//handle the various failures that might happen
		if ($avatarUrl == SCRATCHSIG_API_FAILURE) {
			return '<br /><b>Scratch API Failure. Please try again later.</b>';
		}
		if ($avatarUrl == SCRATCHSIG_USERNAME_NOT_FOUND) {
			return '<br /><b>Scratch username not found: ' . htmlspecialchars($username) . '</b>';
		}
		
		return '<br/><img src="' . htmlspecialchars($avatarUrl) . '" width="18px" height="18px"> ' . $parser->recursiveTagParse("[[User:$username|$username]] ([[User_talk:$username#top|talk]] {{!}} [[Special:Contributions/$username|contribs]])");
	}
}
