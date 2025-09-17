## WikiPoints
WikiPoints is a MediaWiki extension that provides a scoreboard for wiki contributors to view their overall impact on the wiki.

## How does it work?
WikiPoints takes the amount of bytes added/removed in every edit a user has made and adds all of them up to get a total WikiPoints score. For example, if User makes an edit that adds 100 bytes to a page, 100 points are added to their WikiPoints score; if User then makes an edit that removes 5 bytes from a page, 5 bytes are removed from the WikiPoints score.

## What does it add?
WikiPoints adds two special pages to MediaWiki:
- `Special:WikiPoints` - allows users to get the WikiPoints a particular contributor has.
- `Special:MostWikiPoints` - lists the 20 users with the most WikiPoints.

## How do I install it?
1. Download this repository.
2. Extract it to your wiki's extension directory.
3. Add `wfLoadExtension(' WikiPoints ');` to LocalSettings.php.
4. You're done!
