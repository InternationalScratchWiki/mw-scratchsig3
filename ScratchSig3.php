<?php
class ScratchSig {
	public static function onParserFirstCallInit (Parser $parser) {
		$parser->setHook('scratchsig', array ("ScratchSig", "onscratchsig"));
	}


public static function onscratchsig ($input, array $args, Parser $parser, PPFrame $frame) {
	if (!isset($GLOBALS["sigimageurl"][$input])) {
		$GLOBALS["sigimageurl"][$input] = "http://cdn.scratch.mit.edu/get_image/user/" . json_decode(file_get_contents("https://api.scratch.mit.edu/users/$input"), $assoc=true)["id"] . "_18x18.png";
	}
	return "<br/><img src=\"" . $GLOBALS["sigimageurl"][$input] . "\" width=\"18px\" height=\"18px\"> " . $parser->recursiveTagParse("[[User:$input|$input]] ([[User_talk:$input#top|talk]] {{!}} [[Special:Contributions/$input|contribs]])");
	}
}
