<?php

use MediaWiki\Hook\ParserFirstCallInitHook;

const SCRATCHSIG_USERNAME_NOT_FOUND = '@SCRATCHSIG_USERNAME_NOT_FOUND';
const SCRATCHSIG_API_FAILURE = '@SCRATCHSIG_API_FAILURE';

/**
 * Convert a MediaWiki username to a Scratch username
 */
function wikiUsernameToScratchUsername(string $username): string {
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

	if (preg_match('%^HTTP/\d+(?:\.\d+)? 404%', $http_response_header[0])) {
		return SCRATCHSIG_USERNAME_NOT_FOUND;
	}

	if (!preg_match('%^HTTP/\d+(?:\.\d+)? 2\d{2}%', $http_response_header[0])) {
		return SCRATCHSIG_API_FAILURE;
	}

	return (string)json_decode($scratchApiResult, true)['id'];
}

/**
 * Get the cache key for storing the Scratch user ID corresponding to a given username
 */
function scratchUserIdCacheKey(string $username): string {
	return 'scratchsig::userIdByUsername[' . $username . ']';
}

/**
 * Get the Scratch ID corresponding to a given username, with the results cached
 * @return string Returns the ID if successful, will return '@SCRATCHSIG_USERNAME_NOT_FOUND' if no username corresponds to the given ID, and '@SCRATCHSIG_API_FAILURE' if the API failed to load
 */
function scratchUsernameToId(string $username): string {
	$cache = ObjectCache::getLocalClusterInstance();

	$userId = $cache->get(scratchUserIdCacheKey($username));

	//if the user ID is not cached, then get it from the API and cache it
	if (!$userId) {
		$userId = scratchUsernameToIdFromApi($username);

		//for an API failure, do NOT cache the result since the failure is probably transient
		if ($userId === SCRATCHSIG_API_FAILURE) {
			return SCRATCHSIG_API_FAILURE;
		}

		//cache the result
		$cache->set(scratchUserIdCacheKey($username), $userId);
	}

	return $userId;
}

/**
 * Determine if a given Scratch user ID result is a real ID or if it's a failure flag
 */
function isSuccessfulScratchId(string $id): string {
	return !in_array($id, [SCRATCHSIG_API_FAILURE, SCRATCHSIG_USERNAME_NOT_FOUND]);
}

/**
 * Get the avatar URL associated with a given username, or return a failure flag if there is no avatar associated with that username
 */
function scratchAvatarUrl(string $username): string {
	$userId = scratchUsernameToId($username);

	return isSuccessfulScratchId($userId) ? 'https://cdn2.scratch.mit.edu/get_image/user/' . $userId . '_18x18.png' : $userId;
}

class ScratchSig implements ParserFirstCallInitHook {
	public function onParserFirstCallInit($parser) {
		$parser->setHook('scratchsig', array("ScratchSig", "onScratchSig"));
	}

	public static function onScratchSig(?string $username, array $args, Parser $parser, PPFrame $frame): string {
		$parser->getOutput()->addModuleStyles(['ext.scratchSig3']);

		if (empty($username)) {
			return '';
		}

		$avatarUrl = scratchAvatarUrl($username);

		//handle the various failures that might happen
		if ($avatarUrl === SCRATCHSIG_API_FAILURE) {
			return '<br /><b>Scratch API Failure. Please try again later.</b>';
		}

		//if the username is not found, then display no avatar URL at all
		$avatarHtml = $avatarUrl === SCRATCHSIG_USERNAME_NOT_FOUND ? '' : Html::element('img', ['class' => 'scratchsigimage', 'src' => $avatarUrl]);

		return '<br/>' . $avatarHtml . ' ' . $parser->recursiveTagParse("[[User:$username|$username]] ([[User_talk:$username#top|talk]] {{!}} [[Special:Contributions/$username|contribs]])");
	}
}
