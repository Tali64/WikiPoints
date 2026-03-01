<?php
namespace MediaWiki\Extension\WikiPoints;

use MediaWiki\Page\WikiPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IConnectionProvider;

class Hooks implements PageSaveCompleteHook {
	private ActorNormalization $an;
	private IConnectionProvider $dbProvider;

	public function __construct( ActorNormalization $an, IConnectionProvider $dbProvider ) {
		$this->an = $an;
		$this->dbProvider = $dbProvider;
	}

	public function onBlockIpComplete( $block, $user, $priorBlock ) {
		if ( !( $block->getType() == 'autoblock' ) ) {
			$dbw = $this->dbProvider->getPrimaryDatabase();
			$actorName = $block->getTargetName();
			$actorId = $this->an->findActorIdByName( $actorName, $dbw );
			$dbw->update(
				'wikipoints',
				[ 'blocked' => 1 ],
				[ 'actor' => $actorId ],
				__METHOD__
			);
		}
	}

	public function onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	): void {
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$actor = $revisionRecord->getUser()->getName();
		$actorId = $this->an->findActorIdByName( $actor, $dbw );
		$dbw->upsert(
			'wikipoints',
			[ 'actor' => $actorId, 'name' => $actor, 'points' => 0, 'blocked' => 0 ],
			[ 'actor' ],
			[ 'points' => 0, 'blocked' => 0 ],
			__METHOD__
		);
		$sql = "UPDATE wikipoints wp
		JOIN (
			SELECT r.rev_actor AS actor, SUM( r.rev_len - COALESCE( p.rev_len, 0 ) ) AS points
			FROM revision r
			LEFT JOIN revision p ON r.rev_parent_id = p.rev_id
			WHERE NOT EXISTS (
				SELECT 1
				FROM change_tag ct
				JOIN change_tag_def ctd ON ctd.ctd_id = ct.ct_tag_id
				WHERE ct.ct_rev_id = r.rev_id
				  AND ctd.ctd_name IN ( 'mw-reverted', 'mw-rollback', 'mw-undo' )
			)
			GROUP BY r.rev_actor
		) AS src
		ON wp.actor = src.actor
		SET wp.points = src.points
		WHERE wp.actor = " . $dbw->addQuotes( $actorId );

		$dbw->query( $sql, __METHOD__ );
	}
}

